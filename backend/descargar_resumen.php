<?php
// --- Archivo: descargar_resumen.php (Integrado y Funcional) ---

// 🚨 DEBUG: Puedes descomentar estas líneas para ver errores de PHP si falla.
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

date_default_timezone_set('America/Argentina/Buenos_Aires');
setlocale(LC_TIME, 'es_AR.UTF-8', 'es_ES.UTF-8', 'es.UTF-8', 'Spanish');

// 1. INCLUSIÓN DE LIBRERÍAS
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

require_once 'conexion.php'; 

$fecha = $_GET['fecha'] ?? date('Y-m-d');

// Inicialización de variables para evitar errores
$totalIngresos = 0.0;
$totalEgresos = 0.0;
$ultimosIngresos = []; 
$ultimosEgresos = []; 
//file_put_contents("A.txt", $fecha);

try {
    // 2. OBTENER LOS DATOS DEL DÍA (Consulta COMPLETA de get_resumen_dia.php)
    
    $sql = "SELECT * FROM (
        -- INGRESOS: Pagos individuales
        SELECT 'in-pi' as tipo_raw, pr.id, (pr.monto +
            COALESCE((
                SELECT SUM(p.precio * m.cantidad)
                FROM movimiento m
                JOIN compra co ON m.id = co.idMovimiento
                JOIN producto p ON m.idProducto = p.id
                WHERE co.idCliente = c.id -- <-- Correlacionado con el 'c.id' de AFUERA
                AND m.motivo = 'venta_individual'
            ), 0) +
            COALESCE((SELECT SUM(
                p.precio * ic.cantidad /
                NULLIF((
                    SELECT COUNT(*)
                    FROM item_compartido_cliente icc
                    WHERE icc.idItemCompartido = ic.id
                ), 0)
            ) FROM item_compartido ic
              JOIN producto p ON ic.idProducto = p.id
              JOIN item_compartido_cliente icc ON ic.id = icc.idItemCompartido
              WHERE ic.idReserva = r.id AND c.id = icc.idCliente), 0)
            ) as monto, CONCAT('Pago de ', c.nombre, ' (', u.nombre_usuario, ')') as descripcion, NULL as detalle, pr.fecha as fecha_movimiento, r.id as reserva_id
        FROM pago_reserva pr
        JOIN cliente c ON pr.idCliente = c.id
        JOIN reservas r ON c.idReserva = r.id
        JOIN usuarios u ON u.id = r.idEmpleado 
        WHERE DATE(pr.fecha) = ? AND c.pagado = 1 AND (
            -- Contamos el TOTAL de participantes de esta reserva
            (SELECT COUNT(*) FROM cliente c_total WHERE c_total.idReserva = r.id)
            !=
            -- Contamos los participantes que tienen un pago EXACTAMENTE a la misma hora/fecha que r.pago_fecha
            (SELECT COUNT(*)
            FROM pago_reserva pr
            JOIN cliente c_paid ON pr.idCliente = c_paid.id
            WHERE c_paid.idReserva = r.id
                AND pr.fecha = r.pago_fecha -- Compara el DATETIME exacto
            )
        )

        UNION ALL

        -- INGRESOS: Reservas completas (agregamos NULL as detalle)
        SELECT 'in-rc' as tipo_raw, r.id, (
            r.costo_reserva +
            COALESCE((SELECT SUM(p.precio * m.cantidad) FROM movimiento m JOIN compra co ON m.id = co.idMovimiento JOIN cliente c ON co.idCliente = c.id JOIN producto p ON m.idProducto = p.id WHERE c.idReserva = r.id AND m.motivo = 'venta_individual'), 0) +
            COALESCE((SELECT SUM(p.precio * ic.cantidad) FROM item_compartido ic JOIN producto p ON ic.idProducto = p.id WHERE ic.idReserva = r.id), 0)
        ) as monto, CONCAT('Reserva Pagada (', COALESCE(u.nombre_usuario, 'Cliente Online'), ')') as descripcion, NULL as detalle, r.pago_fecha as fecha_movimiento, r.id as reserva_id
        FROM reservas r
        LEFT JOIN usuarios u ON r.idEmpleado = u.id
        WHERE DATE(r.pago_fecha) = ? AND (
            -- Contamos el TOTAL de participantes de esta reserva
            (SELECT COUNT(*) FROM cliente c_total WHERE c_total.idReserva = r.id)
            =
            -- Contamos los participantes que tienen un pago EXACTAMENTE a la misma hora/fecha que r.pago_fecha
            (SELECT COUNT(*)
            FROM pago_reserva pr
            JOIN cliente c_paid ON pr.idCliente = c_paid.id
            WHERE c_paid.idReserva = r.id
                AND pr.fecha = r.pago_fecha -- Compara el DATETIME exacto
            )
        )

        UNION ALL

        -- INGRESOS: Ventas directas (con detalle)
        SELECT 'in-vta' as tipo_raw, v.id, v.monto_total as monto, 
            COALESCE(
                (SELECT GROUP_CONCAT(CONCAT(vd.cantidad, 'x ', p.nombre) SEPARATOR ', ') FROM venta_detalle vd JOIN producto p ON vd.id_producto = p.id WHERE vd.id_venta = v.id),
                'Venta General'
            ) as descripcion, 
            NULL as detalle, v.fecha as fecha_movimiento, NULL as reserva_id
        FROM ventas v
        WHERE DATE(v.fecha) = ?

        UNION ALL

        -- EGRESOS: Atenciones
        SELECT 'eg-at' as tipo_raw, a.id, (a.cantidad * p.precio) as monto, CONCAT('Atención: ', a.cantidad, 'x ', p.nombre) as descripcion, 'Atención' as detalle, a.fecha as fecha_movimiento, NULL as reserva_id
        FROM atenciones a 
        JOIN producto p ON a.idProducto = p.id
        WHERE DATE(a.fecha) = ?

        UNION ALL

        -- EGRESOS: Gastos generales
        SELECT 'eg-gasto' as tipo_raw, g.id, g.monto, g.descripcion, COALESCE(g.categoria, 'Gasto General') as detalle, g.fecha as fecha_movimiento, NULL as reserva_id
        FROM gastos g
        WHERE DATE(g.fecha) = ?
    ) as todos_los_movimientos
    ORDER BY fecha_movimiento DESC";
    
    $stmt = $conn->prepare($sql);
    // 5 placeholders (sssss)
    $stmt->bind_param("sssss", $fecha, $fecha, $fecha, $fecha, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();

    // 3. PROCESAR RESULTADOS
    while ($row = $result->fetch_assoc()) {
        $tipo = substr($row['tipo_raw'], 0, 2); 
        
        $movimiento = [
            "id" => $row['id'],
            "monto" => floatval($row['monto']),
            "descripcion" => $row['descripcion'],
            "detalle" => $row['detalle'] // Incluimos detalle para que no falte en egresos
        ];

        if ($tipo === 'in') {
            $totalIngresos += $movimiento['monto'];
            $movimiento['reserva_id'] = $row['reserva_id'];
            $ultimosIngresos[] = $movimiento;
        } else {
            $totalEgresos += $movimiento['monto'];
            $ultimosEgresos[] = $movimiento;
        }
    }
    
    // 4. GENERAR EL HTML
    $balance_neto = $totalIngresos - $totalEgresos;
    $balance_clase = $balance_neto >= 0 ? 'ingreso' : 'egreso';

    $timestamp = strtotime($fecha); 
    // 2. Usamos strftime() para formatear ese timestamp usando el locale español
    //    %d = día (02)
    //    %B = nombre completo del mes (noviembre)
    //    %Y = año (2025)
    $fecha_linda = strftime('%d de %B de %Y', $timestamp);

    $html_content = '
    <html>
    <head><style>
        body { font-family: sans-serif; }
        h1 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .ingreso { color: #28a745; font-weight: bold; }
        .egreso { color: #dc3545; font-weight: bold; }
        .resumen-total { font-size: 1.2em; }
    </style></head>
    <body>
        <h1>Resumen del Día: ' . htmlspecialchars($fecha_linda) . '</h1>

        <h2>Balance General</h2>
        <div class="resumen-total">
            <table>
                <tr><th>Total Ingresos</th><th>Total Egresos</th><th>Balance Neto</th></tr>
                <tr>
                    <td class="ingreso">$' . number_format($totalIngresos, 2, ',', '.') . '</td>
                    <td class="egreso">$' . number_format($totalEgresos, 2, ',', '.') . '</td>
                    <td class="' . $balance_clase . '">$' . number_format($balance_neto, 2, ',', '.') . '</td>
                </tr>
            </table>
        </div>

        <h3>Detalle de Ingresos</h3>
        <table>
            <thead><tr><th>Monto</th><th>Descripción</th></tr></thead>
            <tbody>';
            foreach ($ultimosIngresos as $ingreso) {
                $html_content .= '<tr><td class="ingreso">$' . number_format($ingreso['monto'], 2, ',', '.') . '</td><td>' . htmlspecialchars($ingreso['descripcion']) . '</td></tr>';
            }
            $html_content .= '</tbody>
        </table>
        
        <h3>Detalle de Egresos</h3>
        <table>
            <thead><tr><th>Monto</th><th>Descripción</th><th>Categoría</th></tr></thead>
            <tbody>';
            foreach ($ultimosEgresos as $egreso) {
                // Usamos $egreso['detalle'] que ahora está garantizado que existe
                $html_content .= '<tr><td class="egreso">$' . number_format($egreso['monto'], 2, ',', '.') . '</td><td>' . htmlspecialchars($egreso['descripcion']) . '</td><td>' . htmlspecialchars($egreso['detalle'] ?? 'Gasto') . '</td></tr>';
            }
            $html_content .= '</tbody>
        </table>
    </body>
    </html>';

    // 5. GENERACIÓN Y FORZADO DE DESCARGA
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($html_content);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = "resumen_dia_" . $fecha . ".pdf";

    // Forzar la descarga
    $dompdf->stream($filename, ["Attachment" => true]);

} catch (Exception $e) {
    // Si falla el código, muestra el error de PHP/Dompdf
    echo "<h1>Error Fatal al Generar PDF</h1>";
    echo "<p>Detalles: " . $e->getMessage() . "</p>";
    error_log("Error PDF: " . $e->getMessage()); // Para debug en logs del servidor
}
// FIN DEL SCRIPT (No cerramos la conexión si Dompdf ya la va a cerrar, o si la clase Dompdf no fue instanciada)
if (isset($conn)) {
    $conn->close();
}
?>
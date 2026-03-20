<?php
// --- Archivo: descargar_resumen_anual.php ---

// 🚨 DEBUG: Puedes descomentar para ver errores
// ini_set('display_errors', 1); error_reporting(E_ALL);

date_default_timezone_set('America/Argentina/Buenos_Aires');
setlocale(LC_TIME, 'es_AR.UTF-8', 'es_ES.UTF-8', 'es.UTF-8', 'Spanish');

require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;
require_once 'conexion.php';

$anio = $_GET['anio'] ?? ''; // Espera 'YYYY'

if (empty($anio) || !is_numeric($anio)) {
    die("Error: Año inválido.");
}

// Inicialización
$datos_mensuales = [];
$totalIngresosAnio = 0.0;
$totalEgresosAnio = 0.0;

try {
    // 1. OBTENER DATOS MENSUALES DEL AÑO (Consulta de get_movimiento_anual.php)
    $mes_espanol = "ELT(MONTH(fecha_movimiento), 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre')";
    $sql = "SELECT
                CONCAT($mes_espanol, ' ', YEAR(fecha_movimiento)) as fecha,
                SUM(ingreso) as ingreso,
                SUM(egreso) as egreso
            FROM (
                -- (Copia todos los UNION ALL de la consulta mensual aquí)
                -- INGRESOS: Pagos individuales
                SELECT pr.fecha as fecha_movimiento, pr.monto as ingreso, 0 as egreso FROM pago_reserva pr JOIN cliente c ON pr.idCliente = c.id JOIN reservas r ON c.idReserva = r.id WHERE r.pagado = 0 AND c.pagado = 1
                UNION ALL
                -- INGRESOS: Reservas completas
                SELECT r.pago_fecha as fecha_movimiento, ( r.costo_reserva + COALESCE((SELECT SUM(p.precio * m.cantidad) FROM movimiento m JOIN compra co ON m.id = co.idMovimiento JOIN cliente c ON co.idCliente = c.id JOIN producto p ON m.idProducto = p.id WHERE c.idReserva = r.id AND m.motivo = 'venta_individual'), 0) + COALESCE((SELECT SUM(p.precio * ic.cantidad) FROM item_compartido ic JOIN producto p ON ic.idProducto = p.id WHERE ic.idReserva = r.id), 0) ) as ingreso, 0 as egreso FROM reservas r WHERE r.pagado = 1 AND r.pago_fecha IS NOT NULL
                UNION ALL
                -- INGRESOS: Ventas directas
                SELECT fecha as fecha_movimiento, monto_total as ingreso, 0 as egreso FROM ventas
                UNION ALL
                -- EGRESOS: Atenciones
                SELECT a.fecha as fecha_movimiento, 0 as ingreso, (a.cantidad * p.precio) as egreso FROM atenciones a JOIN producto p ON a.idProducto = p.id
                UNION ALL
                -- EGRESOS: Gastos generales
                SELECT fecha as fecha_movimiento, 0 as ingreso, monto as egreso FROM gastos
            ) as todos_los_movimientos
            WHERE YEAR(fecha_movimiento) = ?
            GROUP BY YEAR(fecha_movimiento), MONTH(fecha_movimiento)
            ORDER BY YEAR(fecha_movimiento) ASC, MONTH(fecha_movimiento) ASC"; // Ordenamos por mes ascendente

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $anio);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos_mensuales = $result->fetch_all(MYSQLI_ASSOC);

    // Calcular totales del año
    foreach($datos_mensuales as $mes) {
        $totalIngresosAnio += $mes['ingreso'] ?? 0;
        $totalEgresosAnio += $mes['egreso'] ?? 0;
    }

    // 2. GENERAR EL HTML
    $balance_neto_anio = $totalIngresosAnio - $totalEgresosAnio;
    $balance_clase_anio = $balance_neto_anio >= 0 ? 'ingreso' : 'egreso';

    $html_content = '
    <html><head><style>
    body { font-family: sans-serif; }
    h1 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    .ingreso { color: #28a745; font-weight: bold; }
    .egreso { color: #dc3545; font-weight: bold; }
    .resumen-total { font-size: 1.2em; }
</style></head>
    <body>
        <h1>Resumen Anual: ' . htmlspecialchars($anio) . '</h1>

        <h2>Balance General del Año</h2>
        <div class="resumen-total"><table>
            <tr><th>Total Ingresos</th><th>Total Egresos</th><th>Balance Neto</th></tr>
            <tr>
                <td class="ingreso">$' . number_format($totalIngresosAnio, 2, ',', '.') . '</td>
                <td class="egreso">$' . number_format($totalEgresosAnio, 2, ',', '.') . '</td>
                <td class="' . $balance_clase_anio . '">$' . number_format($balance_neto_anio, 2, ',', '.') . '</td>
            </tr>
        </table></div>

        <h3>Detalle Mensual</h3>
        <table>
            <thead><tr><th>Mes</th><th>Ingresos</th><th>Egresos</th><th>Balance Mensual</th></tr></thead>
            <tbody>';
            foreach ($datos_mensuales as $mes) {
                $ingreso_mes = $mes['ingreso'] ?? 0;
                $egreso_mes = $mes['egreso'] ?? 0;
                $balance_mes = $ingreso_mes - $egreso_mes;
                $balance_clase_mes = $balance_mes >= 0 ? 'ingreso' : 'egreso';
                $html_content .= '<tr>
                    <td>' . htmlspecialchars($mes['fecha']) . '</td>
                    <td class="ingreso">$' . number_format($ingreso_mes, 2, ',', '.') . '</td>
                    <td class="egreso">$' . number_format($egreso_mes, 2, ',', '.') . '</td>
                    <td class="' . $balance_clase_mes . '">$' . number_format($balance_mes, 2, ',', '.') . '</td>
                </tr>';
            }
            $html_content .= '</tbody>
        </table>
    </body></html>';

    // 3. GENERACIÓN Y FORZADO DE DESCARGA
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html_content);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $filename = "resumen_anual_" . $anio . ".pdf";
    $dompdf->stream($filename, ["Attachment" => true]);

} catch (Exception $e) {
    echo "<h1>Error Fatal al Generar PDF</h1><p>Detalles: " . $e->getMessage() . "</p>";
    error_log("Error PDF Anual: " . $e->getMessage());
} finally {
    if (isset($conn)) $conn->close();
}
?>
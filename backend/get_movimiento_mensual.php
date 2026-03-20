<?php

// --- Archivo: get_movimiento_mensual.php ---

// --- Cabeceras CORS y de Contenido (igual que en tu script diario) ---
// $origenes_permitidos = [
//     'http://192.168.1.39:5173',
//     'http://192.168.100.9:5173',
//     'http://localhost:5173',
//     'http://10.171.50.47:5173',
//     'http://10.111.15.47:5173'
// ];
// $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
// if (in_array($origen, $origenes_permitidos)) {
//     header("Access-Control-Allow-Origin: $origen");
// }
// header("Content-Type: application/json; charset=UTF-8");
// header("Access-Control-Allow-Methods: GET, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";
require_once 'conexion.php';

// Recibimos el período que nos manda el frontend (ej: "2025-10")
$periodo = $_GET['mes'] ?? '';

if (empty($periodo)) {
    http_response_code(400); // Bad Request
    echo json_encode(["success" => false, "error" => "No se especificó el período."]);
    exit;
}

try {
    // ESTA CONSULTA ES LA CLAVE:
    // 1. Junta TODAS las fuentes de ingresos y egresos con UNION ALL.
    // 2. Filtra solo los movimientos del mes que le pedimos con WHERE.
    // 3. Agrupa todo por día y suma los totales con GROUP BY.
    $sql = "SELECT
                DATE(fecha_movimiento) as fecha,
                SUM(ingreso) as ingreso,
                SUM(egreso) as egreso
            FROM (
                -- INGRESOS: Pagos individuales de reservas abiertas
                SELECT pr.fecha as fecha_movimiento, pr.monto as ingreso, 0 as egreso
                FROM pago_reserva pr
                JOIN cliente c ON pr.idCliente = c.id
                JOIN reservas r ON c.idReserva = r.id
                WHERE r.pagado = 0 AND c.pagado = 1

                UNION ALL

                -- INGRESOS: Reservas completas pagadas
                SELECT
                    r.pago_fecha as fecha_movimiento,
                    (
                        r.costo_reserva +
                        COALESCE((SELECT SUM(p.precio * m.cantidad) FROM movimiento m JOIN compra co ON m.id = co.idMovimiento JOIN cliente c ON co.idCliente = c.id JOIN producto p ON m.idProducto = p.id WHERE c.idReserva = r.id AND m.motivo = 'venta_individual'), 0) +
                        COALESCE((SELECT SUM(p.precio * ic.cantidad) FROM item_compartido ic JOIN producto p ON ic.idProducto = p.id WHERE ic.idReserva = r.id), 0)
                    ) as ingreso,
                    0 as egreso
                FROM reservas r
                WHERE r.pagado = 1 AND r.pago_fecha IS NOT NULL

                UNION ALL

                -- INGRESOS: Ventas directas
                SELECT fecha as fecha_movimiento, monto_total as ingreso, 0 as egreso FROM ventas

                UNION ALL

                -- EGRESOS: Atenciones
                SELECT a.fecha as fecha_movimiento, 0 as ingreso, (a.cantidad * p.precio) as egreso 
                FROM atenciones a 
                JOIN producto p ON a.idProducto = p.id

                UNION ALL

                -- EGRESOS: Gastos generales
                SELECT fecha as fecha_movimiento, 0 as ingreso, monto as egreso 
                FROM gastos

            ) as todos_los_movimientos
            -- El filtro clave: solo movimientos del mes y año que nos piden
            WHERE DATE_FORMAT(fecha_movimiento, '%Y-%m') = ?
            GROUP BY DATE(fecha_movimiento)
            ORDER BY fecha ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $periodo);
    $stmt->execute();
    $result = $stmt->get_result();
    $datos_mensuales = $result->fetch_all(MYSQLI_ASSOC);

    // Devolvemos el array con la clave "datos" que espera el frontend
    echo json_encode(["success" => true, "datos" => $datos_mensuales]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>
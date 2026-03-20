<?php
// --- Archivo: get_detalle_reserva.php ---

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
// header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";
require_once 'conexion.php';

$reservaId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($reservaId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ID de reserva no válido."]);
    exit;
}

$detalle = [];

try {
    // 1. OBTENER INFO PRINCIPAL DE LA RESERVA
    $sql_main = "SELECT 
                    DATE(r.fecha) as fecha, r.hora_inicio, r.hora_fin, r.costo_reserva,
                    ca.nombre_cancha as cancha,
                    COALESCE(u_gestion.nombre_usuario, 'Cliente') as empleado
                 FROM reservas r
                 JOIN canchas ca ON r.cancha_id = ca.id
                 LEFT JOIN usuarios u_gestion ON r.idEmpleado = u_gestion.id
                 WHERE r.id = ?";
    $stmt_main = $conn->prepare($sql_main);
    $stmt_main->bind_param("i", $reservaId);
    $stmt_main->execute();
    $main_data = $stmt_main->get_result()->fetch_assoc();
    
    $detalle['empleado'] = $main_data['empleado'];
    $detalle['cancha'] = $main_data['cancha'];
    $detalle['fecha'] = $main_data['fecha'];
    $detalle['horario'] = $main_data['hora_inicio'] . ' - ' . $main_data['hora_fin'];
    $detalle['costos']['cancha'] = floatval($main_data['costo_reserva']);

    // 2. OBTENER PAGOS RECIBIDOS
    $sql_pagos = "SELECT p.id, c.nombre as participante, p.monto, c.metodoPago
                  FROM pago_reserva p 
                  JOIN cliente c ON p.idCliente = c.id 
                  WHERE c.idReserva = ? AND c.pagado = 1";
    $stmt_pagos = $conn->prepare($sql_pagos);
    $stmt_pagos->bind_param("i", $reservaId);
    $stmt_pagos->execute();
    $pagos_result = $stmt_pagos->get_result();
    $pagos = [];
    while ($row = $pagos_result->fetch_assoc()) {
        $pagos[] = [
            'id' => $row['id'],
            'participante' => $row['participante'],
            'monto' => floatval($row['monto']),
            'metodo' => $row['metodoPago'], // La tabla pago_reserva no tiene método
        ];
    }
    $detalle['pagos'] = $pagos;

    // 3. OBTENER CONSUMOS (PRODUCTOS Y SERVICIOS)
    $sql_consumos = "SELECT p.nombre, SUM(cantidad) as cantidad, p.precio 
                     FROM (
                         -- Compras individuales
                         SELECT m.idProducto, m.cantidad 
                         FROM movimiento m
                         JOIN compra co ON m.id = co.idMovimiento
                         JOIN cliente c ON co.idCliente = c.id
                         JOIN pago_reserva pr ON pr.idCliente = c.id
                         WHERE c.idReserva = ? AND m.motivo = 'venta_individual' AND c.pagado = 1
                         UNION ALL
                         -- Items compartidos
                         SELECT idProducto, cantidad FROM item_compartido WHERE idReserva = ?
                     ) as consumos_unidos
                     JOIN producto p ON consumos_unidos.idProducto = p.id
                     GROUP BY p.nombre, p.precio";
    $stmt_consumos = $conn->prepare($sql_consumos);
    $stmt_consumos->bind_param("ii", $reservaId, $reservaId);
    $stmt_consumos->execute();
    $consumos_result = $stmt_consumos->get_result();
    $productos_consumidos = [];
    $total_costo_productos = 0;
    while ($row = $consumos_result->fetch_assoc()) {
        $productos_consumidos[] = ['cantidad' => intval($row['cantidad']), 'nombre' => $row['nombre']];
        $total_costo_productos += intval($row['cantidad']) * floatval($row['precio']);
    }
    $detalle['consumos']['productos'] = $productos_consumidos;
    $detalle['consumos']['servicios'] = []; // Asumimos que no hay servicios por ahora
    $detalle['costos']['productos'] = $total_costo_productos;
    $detalle['costos']['servicios'] = 0;

    // 4. OBTENER ATENCIONES (REGALOS)
    $sql_atenciones = "SELECT a.cantidad, p.nombre 
                       FROM atenciones a 
                       JOIN producto p ON a.idProducto = p.id 
                       WHERE a.idReserva = ?";
    $stmt_atenciones = $conn->prepare($sql_atenciones);
    $stmt_atenciones->bind_param("i", $reservaId);
    $stmt_atenciones->execute();
    $atenciones_result = $stmt_atenciones->get_result();
    $atenciones = [];
    while ($row = $atenciones_result->fetch_assoc()) {
        $atenciones[] = ['cantidad' => intval($row['cantidad']), 'nombre' => $row['nombre']];
    }
    $detalle['atenciones'] = $atenciones;

    echo json_encode(["success" => true, "detalle" => $detalle]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>
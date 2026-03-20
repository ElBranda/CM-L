<?php
// --- Archivo: get_detalle_cierre.php ---

// (Tus cabeceras CORS - Asegúrate de permitir POST)
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
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";
require_once 'conexion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires'); // O la que uses

$data = json_decode(file_get_contents("php://input"));
$cierreId = isset($data->cierreId) ? intval($data->cierreId) : 0;

if ($cierreId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ID de cierre inválido."]);
    exit;
}

try {
    // 1. Obtener las fechas y el empleado del cierre
    $stmt_cierre = $conn->prepare("SELECT id_empleado, fecha_movimientos_desde, fecha_movimientos_hasta FROM cierres_caja WHERE id = ?");
    $stmt_cierre->bind_param("i", $cierreId);
    $stmt_cierre->execute();
    $cierre_info = $stmt_cierre->get_result()->fetch_assoc();
    
    if (!$cierre_info) {
        throw new Exception("Cierre no encontrado.");
    }
    
    $idEmpleado = $cierre_info['id_empleado'];
    // Usamos las fechas exactas guardadas en el cierre
    $fecha_desde = $cierre_info['fecha_movimientos_desde']; 
    $fecha_hasta = $cierre_info['fecha_movimientos_hasta']; 

    // Inicializamos el array de detalles con todas las categorías
    $detalles = [
        "ventas" => [],
        "reservas_cerradas" => [],
        "pagos_individuales" => [],
        "gastos" => [],
        "atenciones" => [] 
    ];

    // --- CONSULTAS PARA CADA TIPO DE MOVIMIENTO ---

    // 2. Obtener las Ventas (Ingreso)
    $sql_ventas = "SELECT 
                        v.id as venta_id, v.fecha, v.metodo_pago, v.monto_total,
                        COALESCE(GROUP_CONCAT(CONCAT(vd.cantidad, 'x ', p.nombre) SEPARATOR ', '), 'Venta General') as descripcion
                   FROM ventas v
                   LEFT JOIN venta_detalle vd ON v.id = vd.id_venta
                   LEFT JOIN producto p ON vd.id_producto = p.id
                   WHERE v.idEmpleado = ? AND v.fecha > ? AND v.fecha <= ? -- Mayor estricto en DESDE
                   GROUP BY v.id 
                   ORDER BY v.fecha ASC";
    $stmt_ventas = $conn->prepare($sql_ventas);
    $stmt_ventas->bind_param("iss", $idEmpleado, $fecha_desde, $fecha_hasta);
    $stmt_ventas->execute();
    $detalles['ventas'] = $stmt_ventas->get_result()->fetch_all(MYSQLI_ASSOC);

    // 3. Obtener Reservas Cerradas (Ingreso)
    $sql_reservas = "SELECT 
                        r.id as reserva_id, r.pago_fecha as fecha, 
                        GROUP_CONCAT(DISTINCT c.metodoPago SEPARATOR '/') as metodo_pago,
                        CONCAT('Cierre Reserva #', r.id) as descripcion,
                        (r.costo_reserva + COALESCE((SELECT SUM(p.precio * m.cantidad) FROM movimiento m JOIN compra co ON m.id = co.idMovimiento JOIN cliente cli ON co.idCliente = cli.id JOIN producto p ON m.idProducto = p.id WHERE cli.idReserva = r.id AND m.motivo = 'venta_individual'), 0) + COALESCE((SELECT SUM(p.precio * ic.cantidad) FROM item_compartido ic JOIN producto p ON ic.idProducto = p.id WHERE ic.idReserva = r.id), 0)) as monto_total
                    FROM reservas r
                    LEFT JOIN cliente c ON r.id = c.idReserva
                    WHERE r.idEmpleado = ? AND r.pago_fecha > ? AND r.pago_fecha <= ? AND r.pagado = 1
                    GROUP BY r.id
                    ORDER BY r.pago_fecha ASC";
    $stmt_reservas = $conn->prepare($sql_reservas);
    $stmt_reservas->bind_param("iss", $idEmpleado, $fecha_desde, $fecha_hasta);
    $stmt_reservas->execute();
    $detalles['reservas_cerradas'] = $stmt_reservas->get_result()->fetch_all(MYSQLI_ASSOC);

    // 4. Obtener Pagos Individuales de Reservas Abiertas (Ingreso)
    $sql_pagos_ind = "SELECT 
                        pr.fecha, c.metodoPago as metodo_pago, pr.monto,
                        CONCAT('Pago de ', c.nombre, ' (Reserva #', r.id, ')') as descripcion
                      FROM pago_reserva pr
                      JOIN cliente c ON pr.idCliente = c.id
                      JOIN reservas r ON c.idReserva = r.id
                      WHERE r.idEmpleado = ? AND pr.fecha > ? AND pr.fecha <= ? AND r.pagado = 0 AND c.pagado = 1
                      ORDER BY pr.fecha ASC";
    $stmt_pagos_ind = $conn->prepare($sql_pagos_ind);
    $stmt_pagos_ind->bind_param("iss", $idEmpleado, $fecha_desde, $fecha_hasta);
    $stmt_pagos_ind->execute();
    $detalles['pagos_individuales'] = $stmt_pagos_ind->get_result()->fetch_all(MYSQLI_ASSOC);

    // 5. Obtener los Gastos (Egreso)
    $sql_gastos = "SELECT fecha, descripcion, categoria, monto 
                   FROM gastos 
                   WHERE id_empleado_responsable = ? AND fecha > ? AND fecha <= ?
                   ORDER BY fecha ASC";
    $stmt_gastos = $conn->prepare($sql_gastos);
    $stmt_gastos->bind_param("iss", $idEmpleado, $fecha_desde, $fecha_hasta);
    $stmt_gastos->execute();
    $detalles['gastos'] = $stmt_gastos->get_result()->fetch_all(MYSQLI_ASSOC);

    // 6. Obtener las Atenciones (Egreso)
    // Asumimos que las atenciones no están directamente ligadas al empleado que cierra caja,
    // sino que son un egreso general del turno/día. Filtramos solo por fecha.
    // Si tuvieras idEmpleado en 'atenciones', agregarías: AND a.idEmpleado = ?
    $sql_atenciones = "SELECT a.fecha, CONCAT(a.cantidad, 'x ', p.nombre) as descripcion, (a.cantidad * p.precio) as monto_total 
                       FROM atenciones a
                       JOIN producto p ON a.idProducto = p.id
                       JOIN reservas r ON r.id = a.idReserva
                       WHERE a.fecha > ? AND a.fecha <= ? AND r.idEmpleado = ?
                       ORDER BY a.fecha ASC";
    $stmt_atenciones = $conn->prepare($sql_atenciones);
    $stmt_atenciones->bind_param("ssi", $fecha_desde, $fecha_hasta, $idEmpleado); 
    $stmt_atenciones->execute();
    $detalles['atenciones'] = $stmt_atenciones->get_result()->fetch_all(MYSQLI_ASSOC);


    echo json_encode(["success" => true, "detalles" => $detalles]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al obtener detalles: " . $e->getMessage()]);
}

$conn->close();
?>
<?php
// --- Archivo: get_movimientos_pendientes.php ---

// (Tus cabeceras CORS y Content-Type)
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

// Asumimos que el ID del empleado viene por GET (o lo sacas de la sesión/token)
$idEmpleado = isset($_GET['idEmpleado']) ? intval($_GET['idEmpleado']) : 0;


if ($idEmpleado <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ID de empleado inválido."]);
    exit;
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

try {
    // 1. Obtener nombre del empleado
    $stmt_emp = $conn->prepare("SELECT nombre_usuario FROM usuarios WHERE id = ?");
    $stmt_emp->bind_param("i", $idEmpleado);
    $stmt_emp->execute();
    $empleado = $stmt_emp->get_result()->fetch_assoc();
    $nombreEmpleado = $empleado ? $empleado['nombre_usuario'] : 'Empleado Desconocido';

    // --- MODIFICATION 1: Find the last closing time ---
    $sql_ultimo_cierre = "SELECT MAX(fecha_cierre) as ultimo_cierre FROM cierres_caja WHERE id_empleado = ?";
    $stmt_ultimo = $conn->prepare($sql_ultimo_cierre);
    $stmt_ultimo->bind_param("i", $idEmpleado);
    $stmt_ultimo->execute();
    $result_ultimo = $stmt_ultimo->get_result()->fetch_assoc();
    
    // Use last closing time, or default to today midnight if none exists
    $fecha_desde = $result_ultimo && $result_ultimo['ultimo_cierre'] ? $result_ultimo['ultimo_cierre'] : date('Y-m-d 00:00:00'); 
    // Debugging: uncomment to see the date used
    // var_dump($fecha_desde);

    // 3. Consulta para traer TODOS los movimientos relevantes del empleado desde la fecha X
    // Usamos UNION ALL para juntar todo
    $sql = "SELECT * FROM (
        -- INGRESOS: Ventas directas (Use fecha >= ?)
        SELECT v.fecha, 'ingreso' as tipo, 'Venta' as categoria, v.metodo_pago, 
               COALESCE((SELECT GROUP_CONCAT(CONCAT(vd.cantidad, 'x ', p.nombre) SEPARATOR ', ') FROM venta_detalle vd JOIN producto p ON vd.id_producto = p.id WHERE vd.id_venta = v.id), 'Venta General') as descripcion, 
               v.monto_total as monto
        FROM ventas v 
        WHERE v.idEmpleado = ? AND v.fecha > ? -- Use '>' to avoid including the exact closing second

        UNION ALL

        -- INGRESOS: Reservas NO CERRADAS (pagos individuales (irresponsables))
        SELECT pr.fecha, 'ingreso' as tipo, 'Reserva' as categoria, c.metodoPago as metodo_pago,
               CONCAT('Pago de reserva de ', c.nombre) as descripcion,
               (pr.monto +
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
            ) as monto
        FROM pago_reserva pr
        JOIN cliente c ON pr.idCliente = c.id
        JOIN reservas r ON c.idReserva = r.id
        WHERE r.idEmpleado = ? AND pr.fecha > ? AND c.pagado = 1 AND (
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

        -- INGRESOS: Reservas CERRADAS (Use pago_fecha >= ?)
        SELECT r.pago_fecha as fecha, 'ingreso' as tipo, 'Reserva' as categoria, GROUP_CONCAT(DISTINCT c.metodoPago SEPARATOR '/') as metodo_pago,
               CONCAT('Cierre Reserva #', r.id) as descripcion, 
               (r.costo_reserva + COALESCE((SELECT SUM(p.precio * m.cantidad) FROM movimiento m JOIN compra co ON m.id = co.idMovimiento JOIN cliente cli ON co.idCliente = cli.id JOIN producto p ON m.idProducto = p.id WHERE cli.idReserva = r.id AND m.motivo = 'venta_individual'), 0) + COALESCE((SELECT SUM(p.precio * ic.cantidad) FROM item_compartido ic JOIN producto p ON ic.idProducto = p.id WHERE ic.idReserva = r.id), 0)) as monto
        FROM reservas r
        LEFT JOIN cliente c ON r.id = c.idReserva 
        WHERE r.idEmpleado = ? AND r.pago_fecha > ? AND (
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
        GROUP BY r.id 

        UNION ALL

        -- EGRESOS: Gastos (Use fecha >= ?)
        SELECT g.fecha, 'egreso' as tipo, COALESCE(g.categoria, 'Gasto') as categoria, NULL as metodo_pago, 
               g.descripcion, g.monto 
        FROM gastos g
        WHERE g.id_empleado_responsable = ? AND g.fecha > ? -- Use '>' here too

        UNION ALL

        -- EGRESOS: Atenciones
        SELECT a.fecha, 'egreso' as tipo, 'Atencion' as categoria, NULL as metodo_pago,
               CONCAT(a.cantidad,'x ', p.nombre) as descripcion, (p.precio * a.cantidad) as monto
        FROM atenciones a
        JOIN producto p ON p.id = a.idProducto
        JOIN reservas r ON r.id = a.idReserva
        WHERE r.idEmpleado = ? AND a.fecha > ?

    ) as movimientos_pendientes
    ORDER BY fecha ASC"; // Ordenamos por fecha

    $stmt = $conn->prepare($sql);
    if (!$stmt) { // Check if prepare failed
        throw new Exception("Error preparando la sentencia SQL: " . $conn->error);
    }

    // Vinculamos los parámetros (idEmpleado, fecha_desde) para cada UNION
    $stmt->bind_param("isisisisis", $idEmpleado, $fecha_desde, $idEmpleado, $fecha_desde, $idEmpleado, $fecha_desde, $idEmpleado, $fecha_desde, $idEmpleado, $fecha_desde);
    $stmt->execute();
    $result = $stmt->get_result();
    $movimientos = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        "success" => true,
        "nombreEmpleado" => $nombreEmpleado,
        "movimientos" => $movimientos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

if (isset($conn)) { // Close connection only if it exists
    $conn->close();
}
?>
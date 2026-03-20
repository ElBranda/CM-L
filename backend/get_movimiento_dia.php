<?php
// --- Archivo: get_movimientos_dia.php (Versión Corregida y Final) ---

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

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$movimientos = [];

try {
    // =========================================================================
    // 1. OBTENER INGRESOS: Pagos Individuales de RESERVAS AÚN PENDIENTES
    // =========================================================================
    $sql_pagos_individuales = "SELECT
                                r.id,
                                pr.id as prid,
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
                                ) as monto,
                                c.nombre as nombre_cliente,
                                COALESCE(u.nombre_usuario, 'Cliente Online') as nombre_responsable
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
                                )"; // <-- ¡ARREGLO CLAVE!

    $stmt_pagos = $conn->prepare($sql_pagos_individuales);
    $stmt_pagos->bind_param("s", $fecha);
    $stmt_pagos->execute();
    $result_pagos = $stmt_pagos->get_result();
    while($row = $result_pagos->fetch_assoc()){
        $movimientos[] = [
            "id" => "in-pi-" . $row['id'] . "-" . $row['prid'],
            "tipo" => "ingreso",
            "descripcion" => "Pago de " . $row['nombre_cliente'] . " en reserva de " . $row['nombre_responsable'],
            "descripcionReserva" => "Ver Detalle de Reserva",
            "monto" => $row['monto']
        ];
    }

    // =========================================================================
    // 2. OBTENER INGRESOS: Reservas Completamente Pagadas (CON CÁLCULO TOTAL)
    // =========================================================================
    $sql_reservas_pagadas = "SELECT
                               r.id, r.hora_inicio, r.hora_fin,
                               COALESCE(u.nombre_usuario, 'Cliente Online') as nombre_responsable,
                               (
                                   r.costo_reserva +
                                   COALESCE((SELECT SUM(p.precio * m.cantidad) FROM movimiento m JOIN compra co ON m.id = co.idMovimiento JOIN cliente c ON co.idCliente = c.id JOIN producto p ON m.idProducto = p.id WHERE c.idReserva = r.id AND m.motivo = 'venta_individual'), 0) +
                                   COALESCE((SELECT SUM(p.precio * ic.cantidad) FROM item_compartido ic JOIN producto p ON ic.idProducto = p.id WHERE ic.idReserva = r.id), 0)
                               ) as monto_total_calculado
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
        )";

    $stmt_reservas = $conn->prepare($sql_reservas_pagadas);
    $stmt_reservas->bind_param("s", $fecha);
    $stmt_reservas->execute();
    $result_reservas = $stmt_reservas->get_result();
    while($row = $result_reservas->fetch_assoc()){
        $movimientos[] = [
            "id" => "in-rc-" . $row['id'],
            "tipo" => "ingreso",
            "descripcion" => "Reserva Pagada: " . $row['nombre_responsable'] . " (" . $row['hora_inicio'] . " - " . $row['hora_fin'] . ")",
            "descripcionReserva" => "Ver Detalle de Reserva",
            "monto" => $row['monto_total_calculado']
        ];
    }

    // =========================================================================
    // 3. OBTENER INGRESOS: Ventas Directas (con descripción detallada)
    // =========================================================================
    $sql_ventas = "SELECT
                        v.id,
                        v.monto_total,
                        -- Usamos GROUP_CONCAT para listar los productos de la venta
                        COALESCE(
                            (SELECT GROUP_CONCAT(CONCAT(vd.cantidad, 'x ', p.nombre) SEPARATOR ', ')
                             FROM venta_detalle vd
                             JOIN producto p ON vd.id_producto = p.id
                             WHERE vd.id_venta = v.id),
                            'Venta General'
                        ) as descripcion
                    FROM ventas v
                    WHERE DATE(v.fecha) = ?";

    $stmt_ventas = $conn->prepare($sql_ventas);
    $stmt_ventas->bind_param("s", $fecha);
    $stmt_ventas->execute();
    $result_ventas = $stmt_ventas->get_result();

    while($row = $result_ventas->fetch_assoc()){
        $movimientos[] = [
            "id" => "in-vta-" . $row['id'],
            "tipo" => "ingreso",
            "descripcion" => $row['descripcion'], // ¡Ahora es la lista de productos!
            "monto" => $row['monto_total']
        ];
    }

    // =========================================================================
    // 4. OBTENER EGRESOS: Atenciones
    // =========================================================================
    $sql_atenciones = "SELECT a.id, a.cantidad, p.nombre as producto, p.precio 
                       FROM atenciones a 
                       JOIN producto p ON a.idProducto = p.id 
                       WHERE DATE(a.fecha) = ?";
    $stmt_atenciones = $conn->prepare($sql_atenciones);
    $stmt_atenciones->bind_param("s", $fecha);
    $stmt_atenciones->execute();
    $result_atenciones = $stmt_atenciones->get_result();
    while($row = $result_atenciones->fetch_assoc()){
        $movimientos[] = [
            "id" => "eg-at-" . $row['id'],
            "tipo" => "egreso",
            "descripcion" => "Atención: " . $row['cantidad'] . "x " . $row['producto'],
            "monto" => $row['cantidad'] * $row['precio']
        ];
    }

    // =========================================================================
    // 5. OBTENER EGRESOS: Gastos Generales
    // =========================================================================
    $sql_gastos = "SELECT id, monto, descripcion, categoria FROM gastos WHERE DATE(fecha) = ?";
    $stmt_gastos = $conn->prepare($sql_gastos);
    $stmt_gastos->bind_param("s", $fecha);
    $stmt_gastos->execute();
    $result_gastos = $stmt_gastos->get_result();
    while($row = $result_gastos->fetch_assoc()){
        $movimientos[] = [
            "id" => "eg-gasto-" . $row['id'],
            "tipo" => "egreso",
            "descripcion" => $row['descripcion'],
            "categoria" => $row['categoria'],
            "monto" => $row['monto']
        ];
    }
    
    // Devolvemos el array con todos los movimientos
    echo json_encode(["success" => true, "movimientos" => $movimientos]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>
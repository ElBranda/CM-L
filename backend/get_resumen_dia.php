<?php
// --- Archivo: get_resumen_dia.php ---
// --- Cabeceras CORS y de Contenido ---
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
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'conexion.php';

// Si no nos pasan una fecha, usamos la de hoy
$fecha = $_GET['fecha'] ?? date('Y-m-d');

try {
    // 1. OBTENEMOS TODOS LOS MOVIMIENTOS DEL DÍA EN UNA SOLA CONSULTA
    // Reutilizamos la lógica de tus otros scripts para juntar todo.
    $sql = "SELECT * FROM (
        -- INGRESOS: Pagos individuales (agregamos NULL as detalle)
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

        -- INGRESOS: Ventas directas (con descripción detallada)
        SELECT 'in-vta' as tipo_raw, v.id, v.monto_total as monto,
        -- Concatenamos la descripción de la venta con el nombre del empleado
            CONCAT(
                COALESCE(
                    (SELECT GROUP_CONCAT(CONCAT(vd.cantidad, 'x ', p.nombre) SEPARATOR ', ')
                    FROM venta_detalle vd
                    JOIN producto p ON vd.id_producto = p.id
                    WHERE vd.id_venta = v.id),
                    'Venta General'
                ),
                ' (', u.nombre_usuario, ')' -- AÑADIDO: Nombre del empleado entre paréntesis
            ) as descripcion,
            NULL as detalle,
            v.fecha as fecha_movimiento,
            NULL as reserva_id
        FROM ventas v
        -- AÑADIDO: JOIN con la tabla de usuarios
        JOIN usuarios u ON v.idEmpleado = u.id 
        WHERE DATE(v.fecha) = ?

        UNION ALL

        -- 4. EGRESOS: Atenciones (¡Movidos a Egresos!)
        SELECT 'eg-at' as tipo_raw, a.id, (a.cantidad * p.precio) as monto, CONCAT('Atención: ', a.cantidad, 'x ', p.nombre) as descripcion, 'Atención' as detalle, a.fecha as fecha_movimiento, NULL as reserva_id
        FROM atenciones a 
        JOIN producto p ON a.idProducto = p.id
        WHERE DATE(a.fecha) = ?

        UNION ALL

        -- EGRESOS: Gastos generales (ya estaba bien)
        SELECT 'eg-gasto' as tipo_raw, g.id, g.monto, g.descripcion, COALESCE(g.categoria, 'Gasto General') as detalle, g.fecha as fecha_movimiento, NULL as reserva_id
        FROM gastos g
        WHERE DATE(g.fecha) = ?
    ) as todos_los_movimientos
    ORDER BY fecha_movimiento DESC";
    
    $stmt = $conn->prepare($sql);
    // Pasamos la misma fecha a los 5 placeholders (?)
    $stmt->bind_param("sssss", $fecha, $fecha, $fecha, $fecha, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();

    // 2. PROCESAMOS LOS RESULTADOS EN PHP
    $totalIngresos = 0;
    $totalEgresos = 0;
    $ultimosIngresos = [];
    $ultimosEgresos = [];

    while ($row = $result->fetch_assoc()) {
        $tipo = substr($row['tipo_raw'], 0, 2); // 'in' o 'eg'
        
        $movimiento = [
            "id" => $row['id'],
            "monto" => floatval($row['monto']),
            "descripcion" => $row['descripcion']
        ];

        if ($tipo === 'in') {
            $totalIngresos += $movimiento['monto'];
            $movimiento['reserva_id'] = $row['reserva_id'];
            $ultimosIngresos[] = $movimiento;
        } else {
            $totalEgresos += $movimiento['monto'];
            $movimiento['detalle'] = $row['detalle'];
            $ultimosEgresos[] = $movimiento;
        }
    }
    
    // 3. ARMAMOS LA RESPUESTA FINAL
    $respuesta = [
        "success" => true,
        "resumen" => [
            "totalIngresos" => $totalIngresos,
            "totalEgresos" => $totalEgresos
        ],
        // Devolvemos solo los últimos 5 de cada uno
        "ultimosIngresos" => array_slice($ultimosIngresos, 0, 5),
        "ultimosEgresos" => array_slice($ultimosEgresos, 0, 5)
    ];

    echo json_encode($respuesta);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>
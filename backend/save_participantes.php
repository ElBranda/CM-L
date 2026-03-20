<?php
// $origenes_permitidos = [
//     'http://localhost:5173',
//     'http://192.168.1.39:5173',
//     'http://192.168.100.9:5173',
//     'http://10.171.50.47:5173',
//     'http://10.111.15.47:5173'
// ];
// $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
// if (in_array($origen, $origenes_permitidos)) {
//     header("Access-Control-Allow-Origin: $origen");
// }
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Content-Type: application/json; charset=utf-8");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";
date_default_timezone_set('America/Argentina/Buenos_Aires');

include "conexion.php";

$data = json_decode(file_get_contents("php://input"), true);

$reservaId = intval($data['reservaId'] ?? 0);
$participantes = $data['participantes'] ?? [];
$itemsCompartidos = $data['itemsCompartidos'] ?? [];
$atenciones = $data['atenciones'] ?? [];
$costoReserva = floatval($data['costo_reserva'] ?? 0);
$id_empleado_gestion = isset($data['id_empleado_gestion']) ? intval($data['id_empleado_gestion']) : null;

if ($reservaId <= 0) {
    echo json_encode(["success" => false, "error" => "ID de reserva inválido."]);
    exit;
}

$conn->begin_transaction();

try {
    // =================================================================
    // PASO 0: "MEMORIZAR" LAS FECHAS DE PAGO QUE YA EXISTEN
    // =================================================================
    // Este es el bloque nuevo que tenés que agregar.
    // Lo que hace es guardar en un array las fechas de los pagos existentes,
    // usando el ID del cliente viejo como clave.
    $fecha_actual_para_usos_necesarios_y_suficientes = date('Y-m-d H:i:s');
    $fechas_pagos_antiguas = [];
    $stmt_fechas = $conn->prepare("
        SELECT pr.fecha, c.id AS clienteId
        FROM pago_reserva pr
        JOIN cliente c ON pr.idCliente = c.id
        WHERE c.idReserva = ?
    ");
    $stmt_fechas->bind_param("i", $reservaId);
    $stmt_fechas->execute();
    $result_fechas = $stmt_fechas->get_result();
    while ($row = $result_fechas->fetch_assoc()) {
        $fechas_pagos_antiguas[$row['clienteId']] = $row['fecha'];
    }
    $stmt_fechas->close();

    // $reservas_pagos_antiguas = [];
    // $stmt_rp = $conn->prepare("
    //     SELECT c.id as clienteId, 
    // ");

    // =================================================================
    // PASO 1: DEVOLVER TODO EL STOCK DE LA RESERVA
    // =================================================================
    
    // Obtenemos TODOS los movimientos de egreso asociados a esta reserva (individual, compartido, atencion)
    $stmt_old_movs = $conn->prepare("
        SELECT m.cantidad, m.idProducto
        FROM movimiento m
        LEFT JOIN compra c ON m.id = c.idMovimiento
        LEFT JOIN cliente cl ON c.idCliente = cl.id
        WHERE (cl.idReserva = ? AND m.motivo = 'venta_individual')
           OR (m.id IN (SELECT id FROM (SELECT mov.id FROM movimiento mov LEFT JOIN item_compartido ic ON mov.idProducto = ic.idProducto AND mov.cantidad = ic.cantidad WHERE ic.idReserva = ? AND mov.motivo = 'venta_compartida') AS sub1))
           OR (m.id IN (SELECT id FROM (SELECT mov.id FROM movimiento mov LEFT JOIN atenciones a ON mov.idProducto = a.idProducto AND mov.cantidad = a.cantidad WHERE a.idReserva = ? AND mov.motivo = 'atencion') AS sub2))
    ");
    $stmt_old_movs->bind_param("iii", $reservaId, $reservaId, $reservaId);
    $stmt_old_movs->execute();
    $result_old_movs = $stmt_old_movs->get_result();
    
    while ($row = $result_old_movs->fetch_assoc()) {
        if ($row['idProducto'] && $row['cantidad']) {
            $conn->prepare("UPDATE stock SET cantidad = cantidad + ? WHERE idProducto = ?")->execute([$row['cantidad'], $row['idProducto']]);
        }
    }

    // =================================================================
    // PASO 2: BORRADO TOTAL ("TIERRA ARRASADA")
    // =================================================================
    
    // Borramos todo en el orden correcto para no violar las foreign keys
    
    // 1. Borramos las relaciones que apuntan a 'movimiento'
    $conn->prepare("DELETE FROM compra WHERE idCliente IN (SELECT id FROM cliente WHERE idReserva = ?)")->execute([$reservaId]);
    
    // 2. Borramos los movimientos
    $conn->prepare("DELETE FROM movimiento WHERE id IN (SELECT id FROM (SELECT m.id FROM movimiento m LEFT JOIN compra c ON m.id = c.idMovimiento LEFT JOIN cliente cl ON c.idCliente = cl.id WHERE cl.idReserva = ? AND m.motivo = 'venta_individual') as sub1)")->execute([$reservaId]);
    $conn->prepare("DELETE FROM movimiento WHERE motivo IN ('venta_compartida', 'atencion') AND id IN (SELECT id FROM (SELECT mov.id FROM movimiento mov LEFT JOIN item_compartido ic ON mov.idProducto = ic.idProducto LEFT JOIN atenciones a ON mov.idProducto = a.idProducto WHERE ic.idReserva = ? OR a.idReserva = ?) as sub2)")->execute([$reservaId, $reservaId]);

    // 3. Borramos el resto de datos de la reserva
    $conn->prepare("DELETE FROM adelantos WHERE idReserva = ?")->execute([$reservaId]);
    $conn->prepare("DELETE FROM pago_reserva WHERE idCliente IN (SELECT id FROM cliente WHERE idReserva = ?)")->execute([$reservaId]);
    $conn->prepare("DELETE ic_cliente FROM item_compartido_cliente ic_cliente JOIN item_compartido ic ON ic_cliente.idItemCompartido = ic.id WHERE ic.idReserva = ?")->execute([$reservaId]);
    $conn->prepare("DELETE FROM item_compartido WHERE idReserva = ?")->execute([$reservaId]);
    $conn->prepare("DELETE FROM atenciones WHERE idReserva = ?")->execute([$reservaId]);
    $conn->prepare("DELETE FROM cliente WHERE idReserva = ?")->execute([$reservaId]);


    // =================================================================
    // PASO 3: RE-INSERTAR TODO DESDE CERO
    // =================================================================
    $nuevos_ids_participantes = [];
    foreach ($participantes as $p) {
        $nombre = trim($p['nombre']);
        if ($nombre === "") continue;

        $conn->prepare("INSERT INTO cliente (nombre, idReserva, pagado, metodoPago) VALUES (?, ?, ?, ?)")->execute([$nombre, $reservaId, ($p['pagado'] ? 1 : 0), $p['metodoPago']]);
        $clienteId = $conn->insert_id;
        $nuevos_ids_participantes[$p['id']] = $clienteId;

        $monto_pago = floatval($p['pagoReserva'] ?? 0);
        if ($monto_pago > 0) {
            // 1. Datos de entrada que necesitamos para decidir
            $fecha_existente = $fechas_pagos_antiguas[$p['id']] ?? null;
            $esta_pagado_ahora = ($p['pagado'] ? 1 : 0) === 1;

            // 2. Variables para armar la consulta dinámicamente
            $sql = "";
            $params = [];

            // 3. Lógica de decisión
            if ($fecha_existente !== null) {
                // CASO 1: Ya tenía una fecha de antes.
                // La respetamos a muerte, no importa qué diga el checkbox ahora.
                // La fecha está congelada.
                $sql = "INSERT INTO pago_reserva (idCliente, monto, fecha) VALUES (?, ?, ?)";
                $params = ["ids", $clienteId, $monto_pago, $fecha_existente];

            } else {
                // CASO 2: No tenía fecha registrada. Es la primera vez o sigue sin pagar.
                if ($esta_pagado_ahora) {
                    // El usuario marcó "pagado". Este es el momento.
                    // Se registra la fecha por primera y única vez.
                    $sql = "INSERT INTO pago_reserva (idCliente, monto, fecha) VALUES (?, ?, ?)";
                    $params = ["ids", $clienteId, $monto_pago, $fecha_actual_para_usos_necesarios_y_suficientes];
                } else {
                    // No tenía fecha y sigue sin pagar.
                    // Guardamos el monto, pero la fecha va como NULL.
                    $sql = "INSERT INTO pago_reserva (idCliente, monto, fecha) VALUES (?, ?, NULL)";
                    $params = ["id", $clienteId, $monto_pago];
                }
            }

            // 4. Ejecución
            $stmt_pago = $conn->prepare($sql);
            $stmt_pago->bind_param(...$params); // El '...' pasa los elementos del array como argumentos
            $stmt_pago->execute();
            $stmt_pago->close();
        }

        foreach (($p['adelantos'] ?? []) as $adelanto) {
            $conn->prepare("INSERT INTO adelantos (idReserva, idCliente, monto, descripcion, fecha) VALUES (?, ?, ?, ?, NOW())")->execute([$reservaId, $clienteId, abs(floatval($adelanto['monto'])), $adelanto['descripcion'] ?? 'Adelanto / Seña']);
        }
        
        foreach (($p['compras'] ?? []) as $compra) {
            $descripcion = $compra['descripcion'] ?? '';
            if (in_array($descripcion, ["Reserva Cancha", "Adelanto / Seña"]) || strpos($descripcion, "(compartido)") !== false) continue;
            
            $stmtProd = $conn->prepare("SELECT id FROM producto WHERE nombre = ?");
            $stmtProd->bind_param("s", $descripcion);
            $stmtProd->execute();
            $producto = $stmtProd->get_result()->fetch_assoc();
            if (!$producto) continue;

            $productoId = $producto['id'];
            $cantidad = intval($compra['cantidad']);
            
            $conn->prepare("INSERT INTO movimiento (accion, motivo, fecha, cantidad, idProducto, idEmpleado) VALUES ('egreso', 'venta_individual', NOW(), ?, ?, ?)")->execute([$cantidad, $productoId, $id_empleado_gestion]);
            $movimientoId = $conn->insert_id;
            
            $conn->prepare("UPDATE stock SET cantidad = cantidad - ? WHERE idProducto = ?")->execute([$cantidad, $productoId]);
            $conn->prepare("INSERT INTO compra (idCliente, idMovimiento) VALUES (?, ?)")->execute([$clienteId, $movimientoId]);
        }
    }

    foreach ($itemsCompartidos as $item) {
        $productoId = intval($item['productoId']);
        $cantidad = intval($item['cantidad']);
        $clientes_temporales = $item['participantes'] ?? [];
        if ($productoId <= 0 || $cantidad <= 0 || empty($clientes_temporales)) continue;
        
        $conn->prepare("INSERT INTO item_compartido (idReserva, idProducto, cantidad) VALUES (?, ?, ?)")->execute([$reservaId, $productoId, $cantidad]);
        $itemId = $conn->insert_id;
        
        $conn->prepare("UPDATE stock SET cantidad = cantidad - ? WHERE idProducto = ?")->execute([$cantidad, $productoId]);
        
        foreach ($clientes_temporales as $temp_cid) {
            if (isset($nuevos_ids_participantes[$temp_cid])) {
                $real_cid = $nuevos_ids_participantes[$temp_cid];
                $conn->prepare("INSERT INTO item_compartido_cliente (idItemCompartido, idCliente) VALUES (?, ?)")->execute([$itemId, $real_cid]);
            }
        }
        
        $conn->prepare("INSERT INTO movimiento (accion, motivo, fecha, cantidad, idProducto, idEmpleado) VALUES ('egreso', 'venta_compartida', NOW(), ?, ?, ?)")->execute([$cantidad, $productoId, $id_empleado_gestion]);
    }

    foreach ($atenciones as $atencion) {
        $productoId = intval($atencion['productoId']);
        $cantidad = intval($atencion['cantidad']);
        if ($productoId <= 0 || $cantidad <= 0) continue;

        $conn->prepare("INSERT INTO atenciones (idReserva, idProducto, cantidad, idEmpleado) VALUES (?, ?, ?, ?)")->execute([$reservaId, $productoId, $cantidad, $data->id_empleado_gestion]);
        $conn->prepare("UPDATE stock SET cantidad = cantidad - ? WHERE idProducto = ?")->execute([$cantidad, $productoId]);
        $conn->prepare("INSERT INTO movimiento (accion, motivo, fecha, cantidad, idProducto, idEmpleado) VALUES ('egreso', 'atencion', NOW(), ?, ?, ?)")->execute([$cantidad, $productoId, $id_empleado_gestion]);
    }

    // =================================================================
    // PASO 5: VERIFICAR Y ACTUALIZAR ESTADO DE PAGO (LÓGICA MEJORADA)
    // =================================================================
    
    $stmt_check = $conn->prepare("SELECT COUNT(*) as total, SUM(pagado) as pagados FROM cliente WHERE idReserva = ?");
    $stmt_check->bind_param("i", $reservaId);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    $reserva_pagada_completamente = ($result_check['total'] > 0 && $result_check['total'] == $result_check['pagados']);

    if ($costoReserva != 0){
        $stmt_costo_reserva = $conn->prepare("UPDATE reservas SET costo_reserva = ? WHERE id = ?");
        $stmt_costo_reserva->bind_param("di", $costoReserva, $reservaId);
        $stmt_costo_reserva->execute();
        $stmt_costo_reserva->close();
    }

    if ($reserva_pagada_completamente) {
        // Si se pagó por completo, ponemos pagado=1 y la fecha de HOY.
        // Usamos COALESCE para no sobrescribir la fecha si ya estaba pagada de antes.
        $sql_update_reserva = "UPDATE reservas SET pagado = 1, pago_fecha = COALESCE(pago_fecha, ?) WHERE id = ?";
        $stmt_update_reserva = $conn->prepare($sql_update_reserva);
        $stmt_update_reserva->bind_param("si", $fecha_actual_para_usos_necesarios_y_suficientes, $reservaId);
    } else {
        // Si por alguna razón deja de estar pagada, ponemos pagado=0 y limpiamos la fecha.
        $sql_update_reserva = "UPDATE reservas SET pagado = 0, pago_fecha = NULL WHERE id = ?";
        $stmt_update_reserva = $conn->prepare($sql_update_reserva);
        $stmt_update_reserva->bind_param("i", $reservaId);
    }
    $stmt_update_reserva->execute();
    $stmt_update_reserva->close();

    if ($id_empleado_gestion) {
        // Actualiza el empleado gestor SOLAMENTE si la columna está vacía (IS NULL).
        $stmt_gestor = $conn->prepare("UPDATE reservas SET idEmpleado = ? WHERE id = ? AND idEmpleado IS NULL");
        $stmt_gestor->bind_param("ii", $id_empleado_gestion, $reservaId);
        $stmt_gestor->execute();
        $stmt_gestor->close();
    }

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Cambios guardados y sincronizados correctamente."]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => "Error en la transacción: " . $e->getMessage() . " en la línea " . $e->getLine()]);
}

$conn->close();
?>
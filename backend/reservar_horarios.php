<?php
// --- Archivo: /padel-backend/reservar_horarios.php (Versión Mejorada) ---

// 1. MANEJO DE CORS (Forma correcta para evitar múltiples headers)
include "manejo_CORS.php";

// 2. CONEXIÓN Y OBTENCIÓN DE DATOS
include "conexion.php";
$data = json_decode(file_get_contents("php://input"), true);

$cancha_id = (int)($data["cancha_id"] ?? 0);
$nombre = trim($data["nombre"] ?? "");
$fecha = $data['fecha'];
$fecha_inicial_str = $data["fecha"] ?? "";
$horarios = $data["horarios"] ?? [];
$tipo = $data["tipo"] ?? "diario";
$pagado = 0; // Las reservas nuevas siempre inician sin pagar
$id_empleado = isset($data["id_empleado"]) ? intval($data["id_empleado"]) : null;

if (!$cancha_id || !$nombre || !$fecha_inicial_str || !is_array($horarios) || empty($horarios)) {
    http_response_code(400); // Bad Request
    echo json_encode(["success" => false, "error" => "Faltan datos para procesar la reserva."]);
    exit;
}

// 3. LÓGICA DE HORARIOS
sort($horarios);
$hora_inicio = $horarios[0];
$hora_fin = end($horarios); // Forma simple y segura de obtener el último horario

// 4. LÓGICA DE RESERVA CON TRANSACCIÓN (¡LA MAGIA ESTÁ ACÁ!)
$conn->begin_transaction();

try {
    // Determinamos cuántas reservas crear (1 para diario, 4 para mensual)
    $semanas_a_reservar = ($tipo === 'mensual') ? 4 : 1;
    $fecha_obj = new DateTime($fecha_inicial_str); // Usamos un objeto para sumar fechas fácil

    for ($i = 0; $i < $semanas_a_reservar; $i++) {
        $fecha_actual_str = $fecha_obj->format('Y-m-d');

        // --- VALIDACIÓN DE CONFLICTO (EL PASO MÁS IMPORTANTE) ---
        // Antes de insertar, nos fijamos si ya hay algo en ese horario
        $sql_check = "SELECT id FROM reservas WHERE cancha_id = ? AND DATE(fecha) = ? AND estado = 'confirmada' AND ? < hora_fin AND ? > hora_inicio";
        $stmt_check = $conn->prepare($sql_check);
        // Comparamos el nuevo inicio con el fin existente, y el nuevo fin con el inicio existente
        $stmt_check->bind_param("isss", $cancha_id, $fecha_actual_str, $hora_inicio, $hora_fin);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            // ¡Conflicto! Hay una reserva que se pisa. Cancelamos todo.
            throw new Exception("El turno del día {$fecha_actual_str} de {$hora_inicio} a {$hora_fin} ya está ocupado. No se pudo crear la reserva mensual.");
        }
        $stmt_check->close();

        // --- Si no hay conflicto, insertamos la reserva para esta semana ---
        $sql_insert = "INSERT INTO reservas (cancha_id, nombre_usuario, fecha, hora_inicio, hora_fin, tipo, pagado, estado, idEmpleado) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmada', ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("isssssii", $cancha_id, $nombre, $fecha_actual_str, $hora_inicio, $hora_fin, $tipo, $pagado, $id_empleado);
        if (!$stmt_insert->execute()) {
            throw new Exception("Error al guardar la reserva para el día {$fecha_actual_str}.");
        }
        $stmt_insert->close();

        // Preparamos la fecha para la siguiente vuelta del bucle
        $fecha_obj->modify('+1 week');
    }

    // Si el bucle se completó sin errores, confirmamos todas las inserciones en la DB
    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Reserva(s) creada(s) exitosamente."
    ]);

} catch (Exception $e) {
    // Si algo falló (conflicto o error de DB), deshacemos todo lo que se haya insertado
    $conn->rollback();
    http_response_code(409); // 409 Conflict: el estado ideal para este tipo de error
    echo json_encode([
        "success" => false, 
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>
<?php
// --- Archivo: /padel-backend/cancelar_reserva.php (Versión final para tu DB) ---

// --- MANEJO DE CORS (Como lo tenías está bien, pero esta es una forma más estándar) ---
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
// --- FIN DE CORS ---

require_once 'conexion.php'; // Tu archivo de conexión que define $conn

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['reserva_id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ID de reserva no proporcionado"]);
    exit;
}

$reservaId = intval($input['reserva_id']);

// Iniciar transacción para asegurar la integridad de los datos
$conn->begin_transaction();

try {
    // -----------------------------------------------------------------
    // Paso 1: Marcar la reserva como 'cancelada' (Soft Delete)
    // -----------------------------------------------------------------
    // Asumo que agregaste una columna `estado` a tu tabla `reservas`
    $sql_update = "UPDATE reservas SET estado = 'cancelada' WHERE id = ? AND estado = 'confirmada'";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $reservaId);
    $stmt_update->execute();

    if ($stmt_update->affected_rows == 0) {
        throw new Exception("La reserva no existe, no se puede cancelar o ya fue cancelada.");
    }
    $stmt_update->close();

    // -----------------------------------------------------------------
    // Paso 2: Juntar TODOS los productos asociados a la reserva
    // -----------------------------------------------------------------
    $productos_a_devolver = [];

    // a) Items compartidos
    $sql_compartidos = "SELECT idProducto, cantidad FROM item_compartido WHERE idReserva = ?";
    $stmt_compartidos = $conn->prepare($sql_compartidos);
    $stmt_compartidos->bind_param("i", $reservaId);
    $stmt_compartidos->execute();
    $result_compartidos = $stmt_compartidos->get_result();
    while ($row = $result_compartidos->fetch_assoc()) {
        $productos_a_devolver[$row['idProducto']] = ($productos_a_devolver[$row['idProducto']] ?? 0) + $row['cantidad'];
    }
    $stmt_compartidos->close();

    // b) Atenciones (si también usan stock)
    $sql_atenciones = "SELECT idProducto, cantidad FROM atenciones WHERE idReserva = ?";
    $stmt_atenciones = $conn->prepare($sql_atenciones);
    $stmt_atenciones->bind_param("i", $reservaId);
    $stmt_atenciones->execute();
    $result_atenciones = $stmt_atenciones->get_result();
    while ($row = $result_atenciones->fetch_assoc()) {
        $productos_a_devolver[$row['idProducto']] = ($productos_a_devolver[$row['idProducto']] ?? 0) + $row['cantidad'];
    }
    $stmt_atenciones->close();
    
    // c) Consumos individuales (rastreados a través de cliente -> compra -> movimiento)
    $sql_individuales = "SELECT mov.idProducto, mov.cantidad 
                         FROM movimiento mov
                         JOIN compra c ON mov.id = c.idMovimiento
                         JOIN cliente cli ON c.idCliente = cli.id
                         WHERE cli.idReserva = ? AND mov.accion = 'egreso'";
    $stmt_individuales = $conn->prepare($sql_individuales);
    $stmt_individuales->bind_param("i", $reservaId);
    $stmt_individuales->execute();
    $result_individuales = $stmt_individuales->get_result();
     while ($row = $result_individuales->fetch_assoc()) {
        if ($row['idProducto']) { // Solo si el movimiento está asociado a un producto
            $productos_a_devolver[$row['idProducto']] = ($productos_a_devolver[$row['idProducto']] ?? 0) + $row['cantidad'];
        }
    }
    $stmt_individuales->close();


    // -----------------------------------------------------------------
    // Paso 3: Procesar la devolución para cada producto encontrado
    // -----------------------------------------------------------------
    foreach ($productos_a_devolver as $idProducto => $cantidad) {
        if ($cantidad <= 0) continue;

        // a) REGISTRAR el ingreso en la tabla de movimientos
        $sql_mov = "INSERT INTO movimiento (accion, motivo, fecha, cantidad, idProducto) VALUES ('ingreso', 'cancelacion', CURDATE(), ?, ?)";
        $stmt_mov = $conn->prepare($sql_mov);
        $stmt_mov->bind_param("ii", $cantidad, $idProducto);
        $stmt_mov->execute();
        $stmt_mov->close();

        // b) ACTUALIZAR el stock en la tabla de stock
        $sql_stock = "UPDATE stock SET cantidad = cantidad + ? WHERE idProducto = ?";
        $stmt_stock = $conn->prepare($sql_stock);
        $stmt_stock->bind_param("ii", $cantidad, $idProducto);
        $stmt_stock->execute();
        $stmt_stock->close();
    }
    
    // Si llegamos acá, todo fue un éxito. Confirmamos los cambios.
    $conn->commit();
    
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Reserva cancelada y stock devuelto correctamente."
    ]);

} catch (Exception $e) {
    // Si algo falló, deshacemos todos los cambios para no dejar datos corruptos.
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>
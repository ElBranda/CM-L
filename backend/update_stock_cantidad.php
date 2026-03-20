<?php
// --- Archivo: update_stock_cantidad.php ---
// (Cabeceras CORS - Permitir POST)
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
require_once 'conexion.php';
$data = json_decode(file_get_contents("php://input"));

// Validaciones
if (!isset($data->productoId) || !is_numeric($data->productoId) || !isset($data->nuevaCantidad) || !is_numeric($data->nuevaCantidad) || $data->nuevaCantidad < 0 || !isset($data->motivo) || empty($data->motivo)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Datos incompletos o inválidos."]);
    exit;
}
$productoId = intval($data->productoId);
$nuevaCantidad = intval($data->nuevaCantidad);
$motivo = $data->motivo; // Ej: 'ajuste_manual', 'compra_proveedor', 'merma'
$idEmpleado = isset($data->idEmpleado) ? intval($data->idEmpleado) : null;

$conn->begin_transaction();
try {
    // 1. Obtener cantidad actual
    $sql_get = "SELECT cantidad FROM stock WHERE idProducto = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->bind_param("i", $productoId);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();
    $stock_actual = $result_get->fetch_assoc();

    if (!$stock_actual) {
        throw new Exception("Producto no encontrado en stock.");
    }
    $cantidadActual = intval($stock_actual['cantidad']);
    $diferencia = $nuevaCantidad - $cantidadActual;

    if ($diferencia == 0) {
        echo json_encode(["success" => true, "message" => "No hubo cambios en la cantidad."]);
        $conn->commit(); // Confirmar transacción aunque no haya cambios
        exit;
    }

    // 2. Determinar acción y cantidad para el movimiento
    $accion = ($diferencia > 0) ? 'ingreso' : 'egreso';
    $cantidadMovimiento = abs($diferencia);

    // 3. Actualizar tabla 'stock'
    $sql_update = "UPDATE stock SET cantidad = ? WHERE idProducto = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $nuevaCantidad, $productoId);
    $stmt_update->execute();

    // 4. Registrar movimiento
    $sql_mov = "INSERT INTO movimiento (accion, motivo, fecha, cantidad, idProducto, idEmpleado) VALUES (?, ?, NOW(), ?, ?, ?)";
    $stmt_mov = $conn->prepare($sql_mov);
    $stmt_mov->bind_param("ssiii", $accion, $motivo, $cantidadMovimiento, $productoId, $idEmpleado);
    $stmt_mov->execute();

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Stock actualizado y movimiento registrado."]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al actualizar stock: " . $e->getMessage()]);
}
$conn->close();
?>
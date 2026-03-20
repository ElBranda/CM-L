<?php
// --- Archivo: deactivate_producto.php ---

// (Tus cabeceras CORS - Permitir POST)
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

if (!isset($data->productoId) || !is_numeric($data->productoId)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ID de producto inválido."]);
    exit;
}
$productoId = intval($data->productoId);
$idEmpleado = isset($data->idEmpleado) ? intval($data->idEmpleado) : null; // Opcional, para log

$conn->begin_transaction();
try {
    // 1. Marcar el producto como inactivo
    $sql_prod = "UPDATE producto SET activo = FALSE WHERE id = ?";
    $stmt_prod = $conn->prepare($sql_prod);
    $stmt_prod->bind_param("i", $productoId);
    $stmt_prod->execute();

    // (Opcional) Registrar un movimiento de egreso si quieres vaciar el stock al desactivar
    // (O dejar el stock como está, simplemente no aparecerá para vender)
    // $stmt_get = $conn->prepare("SELECT cantidad FROM stock WHERE idProducto = ?"); ...
    // if ($stock_actual && $stock_actual['cantidad'] > 0) {
    //     $stmt_mov = $conn->prepare("INSERT INTO movimiento (...) VALUES ('egreso', 'desactivacion', NOW(), ...)"); ...
    //     $stmt_stock = $conn->prepare("UPDATE stock SET cantidad = 0 WHERE idProducto = ?"); ... // Poner stock a 0
    // }

    // Verificar si se actualizó algo
    if ($stmt_prod->affected_rows > 0) {
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Producto desactivado correctamente."]);
    } else {
        // Puede que el ID no existiera
        $conn->rollback(); // O commit si no encontrarlo no es un error grave
        echo json_encode(["success" => false, "error" => "Producto no encontrado o ya estaba inactivo."]);
    }

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al desactivar producto: " . $e->getMessage()]);
}
$conn->close();
?>
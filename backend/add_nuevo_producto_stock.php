<?php
// --- Archivo: add_nuevo_producto_stock.php ---
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
if (!isset($data->nombre) || empty(trim($data->nombre)) || !isset($data->precio) || !is_numeric($data->precio) || !isset($data->cantidad) || !is_numeric($data->cantidad)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Datos incompletos o inválidos."]);
    exit;
}
$nombre = trim($data->nombre);
$precio = $data->precio;
$cantidad = intval($data->cantidad);
$codigo_barras = isset($data->codigo_barras) && !empty(trim($data->codigo_barras)) ? trim($data->codigo_barras) : null;
$idEmpleado = isset($data->idEmpleado) ? intval($data->idEmpleado) : null; // Opcional

$conn->begin_transaction();
try {
    // 1. Insertar en 'producto'
    $sql_prod = "INSERT INTO producto (codebar, nombre, precio) VALUES (?, ?, ?)";
    $stmt_prod = $conn->prepare($sql_prod);
    $stmt_prod->bind_param("ssd", $codigo_barras, $nombre, $precio);
    $stmt_prod->execute();
    $productoId = $conn->insert_id;

    // 2. Insertar en 'stock'
    $sql_stock = "INSERT INTO stock (idProducto, cantidad) VALUES (?, ?)";
    $stmt_stock = $conn->prepare($sql_stock);
    $stmt_stock->bind_param("ii", $productoId, $cantidad);
    $stmt_stock->execute();

    // 3. Registrar movimiento inicial
    $sql_mov = "INSERT INTO movimiento (accion, motivo, fecha, cantidad, idProducto, idEmpleado) VALUES ('ingreso', 'carga_inicial', NOW(), ?, ?, ?)";
    $stmt_mov = $conn->prepare($sql_mov);
    $stmt_mov->bind_param("iii", $cantidad, $productoId, $idEmpleado);
    $stmt_mov->execute();

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Producto y stock agregados."]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    // Chequear si el error es por duplicado
    if ($conn->errno == 1062) { // Código de error MySQL para duplicate entry
        echo json_encode(["success" => false, "error" => "Error: Ya existe un producto con ese nombre."]);
    } else {
        echo json_encode(["success" => false, "error" => "Error al agregar producto: " . $e->getMessage()]);
    }
}
$conn->close();
?>
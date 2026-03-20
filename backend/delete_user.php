<?php
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
// header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";
require_once "conexion.php"; // tu archivo de conexión

if (!isset($_GET['id'])) {
    echo json_encode(["success" => false, "error" => "ID no proporcionado"]);
    exit;
}

$id = intval($_GET['id']);

if ($id <= 0) {
    echo json_encode(["success" => false, "error" => "ID inválido"]);
    exit;
}

// Evitar que se borre el usuario admin principal (opcional)
if ($id === 1) {
    echo json_encode(["success" => false, "error" => "No se puede borrar el usuario principal"]);
    exit;
}

$sql = "DELETE FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Error al borrar usuario"]);
}

$stmt->close();
$conn->close();
?>

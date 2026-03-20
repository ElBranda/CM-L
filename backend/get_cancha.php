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
// header("Access-Control-Allow-Methods: GET, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";
include "conexion.php";

// Validar parámetro
$cancha_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($cancha_id <= 0) {
    echo json_encode(["success" => false, "error" => "ID inválido"]);
    exit;
}

// Preparar consulta
$stmt = $conn->prepare("
    SELECT id, nombre_cancha, deporte, activa
    FROM canchas
    WHERE id = ?
");

if (!$stmt) {
    error_log("Error preparando get_cancha: " . $conn->error);
    echo json_encode(["success" => false, "error" => "Error en la consulta"]);
    exit;
}

$stmt->bind_param("i", $cancha_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "success" => true,
        "cancha" => $row
    ]);
} else {
    echo json_encode(["success" => false, "error" => "Cancha no encontrada"]);
}
?>

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
include "conexion.php";

// Leer datos JSON
$input = json_decode(file_get_contents("php://input"), true);

// Validar campos
$id = isset($input["id"]) ? (int)$input["id"] : 0;
$nombre_cancha = trim($input["nombre_cancha"] ?? "");
$deporte = trim($input["deporte"] ?? "");
$descripcion = trim($input["descripcion"] ?? "");
$ubicacion = trim($input["ubicacion"] ?? "");
$activa = isset($input["activa"]) ? (int)$input["activa"] : 0;

if ($id <= 0 || $nombre_cancha === "" || $deporte === "") {
    echo json_encode(["success" => false, "error" => "Faltan campos obligatorios"]);
    exit;
}

// Preparar consulta
$stmt = $conn->prepare("
    UPDATE canchas
    SET nombre_cancha = ?, deporte = ?, descripcion = ?, ubicacion = ?, activa = ?
    WHERE id = ?
");

if (!$stmt) {
    error_log("Error preparando update_cancha: " . $conn->error);
    echo json_encode(["success" => false, "error" => "Error en la consulta"]);
    exit;
}

$stmt->bind_param("ssssii", $nombre_cancha, $deporte, $descripcion, $ubicacion, $activa, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    error_log("Error ejecutando update_cancha: " . $stmt->error);
    echo json_encode(["success" => false, "error" => "No se pudo actualizar"]);
}
?>

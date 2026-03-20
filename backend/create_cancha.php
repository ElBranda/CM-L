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
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";
include "conexion.php";

$data = json_decode(file_get_contents("php://input"), true);

$nombre = trim($data["nombre_cancha"] ?? "");
$deporte = trim($data["deporte"] ?? "");
$desc = trim($data["descripcion"] ?? "");
$ubi = trim($data["ubicacion"] ?? "");
$activa = isset($data["activa"]) ? (int)$data["activa"] : 1;

if (!$nombre) {
    echo json_encode(["success" => false, "error" => "Falta nombre obligatorio"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO canchas (nombre_cancha, deporte, descripcion, ubicacion, activa, fecha_creacion) VALUES (?,?,?,?,?,NOW())");
$stmt->bind_param("ssssi", $nombre, $deporte, $desc, $ubi, $activa);

if ($stmt->execute()) {
    $creadoEn = date("Y-m-d H:i:s");
    echo json_encode([
        "success" => true,
        "id" => $stmt->insert_id,
        "nombre_cancha" => $nombre,
        "deporte" => $deporte,
        "descripcion" => $desc,
        "ubicacion" => $ubi,
        "activa" => (bool)$activa,
        "fecha_creacion" => $creadoEn
    ]);
} else {
    echo json_encode(["success" => false, "error" => "Error al crear cancha"]);
}
?>
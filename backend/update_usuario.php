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

$id = isset($input["id"]) ? (int)$input["id"] : 0;
$nombre_usuario = trim($input["nombre_usuario"] ?? "");
$nombre = trim($input["nombre"] ?? "");
$apellido = trim($input["apellido"] ?? "");
$email = trim($input["email"] ?? "");
$rol = trim($input["rol"] ?? "empleado");
$contraseña = $input["contraseña"] ?? "";
$activo = isset($input["activo"]) ? (int)$input["activo"] : 0;


// Validaciones básicas
if ($id <= 0 || $nombre_usuario === "" || $nombre === "" || $apellido === "" || $email === "") {
    echo json_encode(["success" => false, "error" => "Faltan campos obligatorios"]);
    exit;
}

// Si se envió contraseña, actualizarla con hash
if ($contraseña !== "") {
    $hash = password_hash($contraseña, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        UPDATE usuarios
        SET nombre_usuario = ?, nombre = ?, apellido = ?, email = ?, rol = ?, contraseña = ?, activo = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssssssii", $nombre_usuario, $nombre, $apellido, $email, $rol, $hash, $activo, $id);
} else {
    $stmt = $conn->prepare("
        UPDATE usuarios
        SET nombre_usuario = ?, nombre = ?, apellido = ?, email = ?, rol = ?, activo = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssssii", $nombre_usuario, $nombre, $apellido, $email, $rol, $activo, $id);
}

if (!$stmt) {
    error_log("Error preparando update_usuario: " . $conn->error);
    echo json_encode(["success" => false, "error" => "Error en la consulta"]);
    exit;
}

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    error_log("Error ejecutando update_usuario: " . $stmt->error);
    echo json_encode(["success" => false, "error" => "No se pudo actualizar"]);
}
?>

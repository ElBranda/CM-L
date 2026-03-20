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

$nickname = trim($data["nombre_usuario"] ?? "");
$name = trim($data["nombre"] ?? "");
$lname = trim($data["apellido"] ?? "");
$email = trim($data["email"] ?? "");
$rol = $data["rol"] ?? "empleado";
$password = $data["contraseña"] ?? "";
$activo = isset($data["activo"]) ? (int)$data["activo"] : 1;

if (!$nickname || !$name || !$email || !$password) {
    echo json_encode(["success" => false, "error" => "Faltan campos obligatorios"]);
    exit;
}

$hashedPass = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, nombre, apellido, email, contraseña, rol, activo, creado_en) VALUES (?,?,?,?,?,?,?,NOW())");
$stmt->bind_param("ssssssi", $nickname, $name, $lname, $email, $hashedPass, $rol, $activo);

if ($stmt->execute()) {
    $creadoEn = date("Y-m-d H:i:s");
    echo json_encode([
        "success" => true,
        "id" => $stmt->insert_id,
        "nombre_usuario" => $nickname,
        "nombre" => $name,
        "apellido" => $lname,
        "email" => $email,
        "rol" => $rol,
        "activo" => (bool)$activo,
        "creado_en" => $creadoEn
    ]);
} else {
    echo json_encode(["success" => false, "error" => "Error al crear usuario"]);
}
?>
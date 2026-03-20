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
// header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Content-Type: application/json; charset=utf-8");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";
include "conexion.php";
require "vendor/autoload.php";
include "jwt_config.php";

use Firebase\JWT\JWT;

$data = json_decode(file_get_contents("php://input"), true);
$user = $data["user"];
$password = $data["password"];

$stmt = $conn->prepare("SELECT id, nombre_usuario, contraseña, rol, activo FROM usuarios WHERE nombre_usuario = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
$result = $stmt->get_result();



if ($row = $result->fetch_assoc()) {
    // if ($password === $row["contraseña"]) {
    //     // Migrar a hash
    //     $newHash = password_hash($password, PASSWORD_DEFAULT);
    //     $update = $conn->prepare("UPDATE usuarios SET contraseña = ? WHERE id = ?");
    //     $update->bind_param("si", $newHash, $row["id"]);
    //     $update->execute();

    //     // Usás el nuevo hash en el futuro
    //     $row["contraseña"] = $newHash;
    // }

    if (password_verify($password, $row["contraseña"])) {
        $payload = [
            "id" => $row["id"],
            "rol" => $row["rol"],
            "nombre_usuario" => $row["nombre_usuario"],
            "exp" => time() + JWT_EXPIRATION
        ];
        $token = JWT::encode($payload, JWT_SECRET, "HS256");

        echo json_encode([
            "success" => true,
            "token" => $token,
            "id" => $row["id"],
            "rol" => $row["rol"],
            "nombre_usuario" => $row["nombre_usuario"]
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "Contraseña incorrecta"]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Usuario no encontrado"]);
}
?>
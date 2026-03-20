<?php
require "vendor/autoload.php";
include "jwt_config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$headers = getallheaders();
$authHeader = $headers["Authorization"] ?? "";

if (!$authHeader || !str_starts_with($authHeader, "Bearer")) {
    http_response_code(401);
    echo json_encode(["error" => "Token no enviado"]);
    exit;
}

$token = str_replace("Bearer ", "", $authHeader);

try {
    $decoded = JWT::decode($token, new Key(JWT_SECRET, "HS256"));
    echo json_encode(["success" => true, "data" => $decoded]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Token inválido"]);
}
?>
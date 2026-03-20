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
$data = json_decode(file_get_contents("php://input"), true);

$reservaId = intval($data['reservaId'] ?? 0);
$montoTotal = floatval($data['montoTotal'] ?? 0);
$participantes = $data['participantes'] ?? [];

if ($reservaId <= 0 || $montoTotal <= 0 || !is_array($participantes) || count($participantes) === 0) {
  echo json_encode(["success" => false, "error" => "Datos inválidos"]);
  exit;
}

$montoPorPersona = round($montoTotal / count($participantes), 2);

$division = [];

foreach ($participantes as $idCliente) {
  $division[] = [
    "idCliente" => intval($idCliente),
    "monto" => $montoPorPersona
  ];
}

echo json_encode([
  "success" => true,
  "division" => $division
]);

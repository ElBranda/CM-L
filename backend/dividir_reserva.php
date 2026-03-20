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
$reservaId = intval($data['reservaId'] ?? 0);
$montoTotal = floatval($data['montoTotal'] ?? 0);
$montoPorPersona = floatval($data['montoPorPersona'] ?? 0);
$participantes = $data['participantes'] ?? [];

if ($reservaId <= 0 || $montoTotal <= 0 || $montoPorPersona <= 0 || count($participantes) === 0) {
  echo json_encode(["success" => false, "error" => "Datos inválidos"]);
  exit;
}

$conn->begin_transaction();

try {
    $updateRes = $conn->prepare("UPDATE reservas SET costo_reserva = ? WHERE id = ?");
    $updateRes->bind_param("ii", $montoTotal, $reservaId);
    $updateRes->execute();

  foreach ($participantes as $idCliente) {
    $stmtCheck = $conn->prepare("SELECT * FROM pago_reserva WHERE idCliente = ?");
    $stmtCheck->bind_param("i", $idCliente);
    $stmtCheck->execute();
    $stmtCheck->store_result();

    //Si no existe este pibe
    if ($stmtCheck->num_rows === 0) {
        $stmtPago = $conn->prepare("INSERT INTO pago_reserva (idCliente, monto, fecha) VALUES (?, ?, NOW())");
        $stmtPago->bind_param("id", $idCliente, $montoPorPersona);
        $stmtPago->execute();
    } else {
        $stmtUpdate = $conn->prepare("UPDATE pago_reserva SET monto = ?, fecha = NOW() WHERE idCliente = ?");
        $stmtUpdate->bind_param("di", $montoPorPersona, $idCliente);
        $stmtUpdate->execute();
    }
  }

  $conn->commit();
  echo json_encode(["success" => true]);
} catch (Exception $e) {
  $conn->rollback();
  echo json_encode(["success" => false, "error" => "Error al dividir: " . $e->getMessage()]);
}
?>

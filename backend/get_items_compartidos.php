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

$reservaId = intval($_GET['reservaId'] ?? 0);

if ($reservaId <= 0) {
  echo json_encode(["success" => false, "error" => "ID de reserva inválido"]);
  exit;
}

$stmt = $conn->prepare("
  SELECT ic.id AS itemId, ic.idProducto, ic.cantidad, p.nombre AS productoNombre, p.precio, p.activo
  FROM item_compartido ic
  JOIN producto p ON p.id = ic.idProducto
  WHERE ic.idReserva = ?
");
$stmt->bind_param("i", $reservaId);
$stmt->execute();
$res = $stmt->get_result();

$items = [];

while ($row = $res->fetch_assoc()) {
  $itemId = intval($row['itemId']);

  // Buscar participantes
  $stmtPart = $conn->prepare("
    SELECT idCliente
    FROM item_compartido_cliente
    WHERE idItemCompartido = ?
  ");
  $stmtPart->bind_param("i", $itemId);
  $stmtPart->execute();
  $resPart = $stmtPart->get_result();

  $participantes = [];
  while ($p = $resPart->fetch_assoc()) {
    $participantes[] = intval($p['idCliente']);
  }

  $items[] = [
    "id" => $itemId,
    "productoId" => intval($row['idProducto']),
    "productoNombre" => $row['productoNombre'],
    "precio" => floatval($row['precio']),
    "cantidad" => intval($row['cantidad']),
    "activo" => intval($row['activo']),
    "participantes" => $participantes
  ];
}

echo json_encode([
  "success" => true,
  "itemsCompartidos" => $items
]);

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

try {
  $query = "
    SELECT p.id, p.nombre, p.precio, s.cantidad
    FROM producto p
    JOIN stock s ON s.idProducto = p.id
    WHERE s.cantidad > 0
  ";

  $res = $conn->query($query);
  $productos = [];

  while ($row = $res->fetch_assoc()) {
    $productos[] = [
      "id" => intval($row["id"]),
      "nombre" => $row["nombre"],
      "precio" => floatval($row["precio"]),
      "cantidad" => intval($row["cantidad"])
    ];
  }

  echo json_encode([
    "success" => true,
    "productos" => $productos
  ]);
} catch (Exception $e) {
  echo json_encode([
    "success" => false,
    "error" => "Error al obtener productos: " . $e->getMessage()
  ]);
}

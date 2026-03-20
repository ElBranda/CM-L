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

$result = $conn->query("SELECT id, nombre_cancha, deporte, ubicacion, descripcion, activa FROM canchas");

$canchas = [];
while ($row = $result->fetch_assoc()) {
    $canchas[] = $row;
}

echo json_encode($canchas);
?>

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

// Consulta para traer TODOS los productos, con o sin stock
$sql = "SELECT p.id, p.codebar, p.nombre, p.precio, s.cantidad
        FROM producto p
        LEFT JOIN stock s ON p.id = s.idProducto
        WHERE p.activo = TRUE
        ORDER BY p.nombre";

$result = $conn->query($sql);
$productos = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Aseguramos que los tipos de datos sean correctos
        $row['id'] = intval($row['id']);
        $row['cantidad'] = intval($row['cantidad'] ?? 0); // Si no hay stock, es 0
        $productos[] = $row;
    }
}

echo json_encode(["success" => true, "productos" => $productos]);

$conn->close();
?>
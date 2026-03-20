<?php
// --- Archivo: get_stock_actual.php ---
// (Cabeceras CORS y Content-Type)
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

require_once 'conexion.php';

try {
    // Consulta para traer stock y la fecha del último movimiento para cada producto
    $sql = "SELECT 
                p.id, 
                p.nombre, 
                p.precio, 
                s.cantidad,
                (SELECT MAX(fecha) FROM movimiento WHERE idProducto = p.id) as ultima_actualizacion
            FROM producto p
            JOIN stock s ON p.id = s.idProducto
            WHERE p.activo = TRUE
            ORDER BY p.nombre ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $stock = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(["success" => true, "stock" => $stock]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al obtener stock: " . $e->getMessage()]);
}
$conn->close();
?>
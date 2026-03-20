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

if (!isset($_GET['reservaId']) || empty($_GET['reservaId'])) {
    echo json_encode(["success" => false, "error" => "ID de reserva no proporcionado."]);
    exit;
}
$reservaId = intval($_GET['reservaId']);

try {
    // La consulta SQL está perfecta porque usa los nombres correctos de tu tabla
    $stmt = $conn->prepare("
        SELECT
            id,
            idProducto,
            cantidad
        FROM atenciones
        WHERE idReserva = ?
    ");
    $stmt->bind_param("i", $reservaId);
    $stmt->execute();
    $result = $stmt->get_result();

    $atenciones = [];
    
    // ▼▼▼ AQUÍ ESTÁ EL CAMBIO MÁGICO ▼▼▼
    while ($row = $result->fetch_assoc()) {
        // En lugar de usar $row directamente, creamos un nuevo array
        // con las claves que React espera (camelCase).
        $atenciones[] = [
            'id' => intval($row['id']),
            'productoId' => intval($row['idProducto']), // La clave ahora es 'productoId'
            'cantidad' => intval($row['cantidad'])
        ];
    }
    // ▲▲▲ FIN DEL CAMBIO ▲▲▲

    echo json_encode(["success" => true, "atenciones" => $atenciones]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Error de base de datos: " . $e->getMessage()]);
}

$conn->close();
?>
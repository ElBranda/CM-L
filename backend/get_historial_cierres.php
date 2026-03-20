<?php
// --- Archivo: get_historial_cierres.php ---

// (Tus cabeceras CORS - Asegúrate de permitir POST)
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
require_once 'conexion.php';

// Leemos los datos enviados por POST
$data = json_decode(file_get_contents("php://input"));

$idEmpleado = isset($data->idEmpleado) ? intval($data->idEmpleado) : 0;

if ($idEmpleado <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ID de empleado inválido."]);
    exit;
}

try {
    // 1. Obtener nombre del empleado (opcional, para el título)
    $stmt_emp = $conn->prepare("SELECT nombre_usuario FROM usuarios WHERE id = ?");
    $stmt_emp->bind_param("i", $idEmpleado);
    $stmt_emp->execute();
    $empleado = $stmt_emp->get_result()->fetch_assoc();
    $nombreEmpleado = $empleado ? $empleado['nombre_usuario'] : 'Empleado';

    // 2. Obtener el historial de cierres para ESE empleado
    $sql = "SELECT 
                id, 
                fecha_cierre, 
                fecha_movimientos_desde, 
                fecha_movimientos_hasta, 
                total_ingresos, 
                total_egresos, 
                balance_calculado, 
                total_efectivo, 
                total_transferencia, 
                total_tarjeta 
            FROM cierres_caja 
            WHERE id_empleado = ? 
            ORDER BY fecha_cierre DESC"; // Más reciente primero
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idEmpleado);
    $stmt->execute();
    $result = $stmt->get_result();
    $historial = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        "success" => true,
        "nombreEmpleado" => $nombreEmpleado,
        "historial" => $historial
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al obtener historial: " . $e->getMessage()]);
}

$conn->close();
?>
<?php
// --- Archivo: add_gasto.php ---

// --- Cabeceras CORS y de Contenido ---
// (Copiá las mismas cabeceras que tenés en tus otros scripts)
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

// Leemos los datos que nos manda el modal (vienen como JSON)
$data = json_decode(file_get_contents("php://input"));

// Validaciones básicas
if (!isset($data->monto) || !is_numeric($data->monto) || $data->monto <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "El monto es inválido."]);
    exit;
}
if (!isset($data->descripcion) || empty(trim($data->descripcion))) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "La descripción no puede estar vacía."]);
    exit;
}

$monto = $data->monto;
$descripcion = trim($data->descripcion);
// La categoría es opcional, si no viene la guardamos como NULL
$categoria = isset($data->categoria) && !empty($data->categoria) ? $data->categoria : null;
$empleado = intval($data->idEmpleado) ?? 0;

try {
    $sql = "INSERT INTO gastos (monto, descripcion, categoria, fecha, id_empleado_responsable) VALUES (?, ?, ?, NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dssi", $monto, $descripcion, $categoria, $empleado); // 'd' es para decimal/double

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Egreso registrado correctamente."]);
    } else {
        throw new Exception("No se pudo registrar el egreso.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error en el servidor: " . $e->getMessage()]);
}

$conn->close();
?>
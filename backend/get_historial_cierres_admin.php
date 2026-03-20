<?php
// --- Archivo: get_historial_cierres_admin.php ---

// (Tus cabeceras CORS - Asegúrate de permitir POST/GET según necesites)
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
// header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";
require_once 'conexion.php';

// El ID del empleado es opcional ahora (si viene por GET o POST)
$idEmpleadoFiltro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $idEmpleadoFiltro = isset($data->idEmpleado) ? intval($data->idEmpleado) : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $idEmpleadoFiltro = isset($_GET['idEmpleado']) ? intval($_GET['idEmpleado']) : null;
}

try {
    // Construimos la consulta base
    $sql = "SELECT 
                c.id, 
                c.id_empleado,
                u.nombre_usuario as nombre_empleado, -- Incluimos el nombre
                c.fecha_cierre, 
                c.fecha_movimientos_desde, 
                c.fecha_movimientos_hasta, 
                c.total_ingresos, 
                c.total_egresos, 
                c.balance_calculado, 
                c.total_efectivo, 
                c.total_transferencia, 
                c.total_tarjeta 
            FROM cierres_caja c
            JOIN usuarios u ON c.id_empleado = u.id"; // Unimos con usuarios

    $params = [];
    $types = "";

    // Si nos pasaron un ID, agregamos el filtro WHERE
    if ($idEmpleadoFiltro !== null && $idEmpleadoFiltro > 0) {
        $sql .= " WHERE c.id_empleado = ?";
        $params[] = $idEmpleadoFiltro;
        $types .= "i";
    }
    
    $sql .= " ORDER BY c.fecha_cierre DESC"; // Más reciente primero
            
    $stmt = $conn->prepare($sql);
    
    // Vinculamos parámetros si hay filtro
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $historial = $result->fetch_all(MYSQLI_ASSOC);
    
    // (Opcional) Traer lista de empleados para el filtro del frontend
    $empleados = [];
    $result_emps = $conn->query("SELECT id, nombre_usuario FROM usuarios ORDER BY nombre_usuario");
    if($result_emps) {
        $empleados = $result_emps->fetch_all(MYSQLI_ASSOC);
    }


    echo json_encode([
        "success" => true,
        "historial" => $historial,
        "empleados" => $empleados // Enviamos lista para el select
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al obtener historial: " . $e->getMessage()]);
}

$conn->close();
?>
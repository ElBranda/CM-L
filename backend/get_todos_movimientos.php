<?php
// --- Archivo: get_todos_movimientos.php ---

// (Tus cabeceras CORS y Content-Type)
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
// header("Access-Control-Allow-Methods: GET, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Content-Type: application/json; charset=utf-8");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";

require_once 'conexion.php';

// (Opcional) Parámetros para paginación futura:
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 20; // 20 por defecto
$offset = ($pagina - 1) * $por_pagina;

try {
    // Consulta para traer todos los movimientos con detalles
    $sql = "SELECT 
                m.id, 
                m.fecha, 
                m.accion, 
                m.motivo, 
                m.cantidad, 
                p.nombre as nombre_producto,
                u.nombre_usuario as nombre_empleado 
            FROM movimiento m
            JOIN producto p ON m.idProducto = p.id
            LEFT JOIN usuarios u ON m.idEmpleado = u.id -- LEFT JOIN por si idEmpleado es NULL
            ORDER BY m.fecha DESC
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $por_pagina, $offset);
    
    $stmt->execute();
    $result = $stmt->get_result();
    $movimientos = $result->fetch_all(MYSQLI_ASSOC);

    // 4. NECESITAMOS EL TOTAL: Hacemos una segunda consulta (rápida)
    $total_result = $conn->query("SELECT COUNT(*) as total FROM movimiento");
    $total_movimientos = $total_result->fetch_assoc()['total'];
    // 👆 --- FIN DE CAMBIOS ---

    echo json_encode([
        "success" => true, 
        "movimientos" => $movimientos,
        "total" => $total_movimientos // <-- Enviamos el total al frontend
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al obtener movimientos: " . $e->getMessage()]);
}
$conn->close();
?>
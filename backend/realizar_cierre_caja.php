<?php
// --- Archivo: realizar_cierre_caja.php ---

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
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Content-Type: application/json; charset=utf-8");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";

require_once 'conexion.php';

// Recibimos los datos del frontend (ID del empleado y los totales calculados)
$data = json_decode(file_get_contents("php://input"));

$idEmpleado = isset($data->idEmpleado) ? intval($data->idEmpleado) : 0;
$totales = isset($data->totales) ? $data->totales : null;

// Validaciones
if ($idEmpleado <= 0 || !$totales) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Datos incompletos para el cierre."]);
    exit;
}

// Forzar zona horaria para consistencia
date_default_timezone_set('America/Argentina/Buenos_Aires'); // O la que uses

$conn->begin_transaction();

try {
    // 1. Determinar fecha_movimientos_desde
    // Buscamos la fecha del último cierre exitoso de este empleado
    $sql_ultimo_cierre = "SELECT MAX(fecha_cierre) as ultimo_cierre FROM cierres_caja WHERE id_empleado = ?";
    $stmt_ultimo = $conn->prepare($sql_ultimo_cierre);
    $stmt_ultimo->bind_param("i", $idEmpleado);
    $stmt_ultimo->execute();
    $result_ultimo = $stmt_ultimo->get_result()->fetch_assoc();
    $fecha_desde = $result_ultimo && $result_ultimo['ultimo_cierre'] ? $result_ultimo['ultimo_cierre'] : date('Y-m-d 00:00:00'); // Si no hay, desde hoy a medianoche

    // 2. Determinar fecha_movimientos_hasta (la fecha del último movimiento incluido)
    // Re-ejecutamos una consulta similar a get_movimientos_pendientes para encontrar la fecha MÁXIMA
    // (Podrías pasar esta fecha desde el frontend si ya la tienes, pero es más seguro recalcularla)
    $sql_ultima_fecha = "SELECT MAX(fecha) as ultima_fecha FROM (
        SELECT v.fecha FROM ventas v WHERE v.idEmpleado = ? AND v.fecha >= ?
        UNION ALL
        SELECT r.pago_fecha as fecha FROM reservas r WHERE r.idEmpleado = ? AND r.pago_fecha >= ? AND r.pagado = 1
        UNION ALL
        SELECT g.fecha FROM gastos g WHERE g.id_empleado_responsable = ? AND g.fecha >= ?
    ) as todas_las_fechas";
    $stmt_fecha_hasta = $conn->prepare($sql_ultima_fecha);
    $stmt_fecha_hasta->bind_param("isisis", $idEmpleado, $fecha_desde, $idEmpleado, $fecha_desde, $idEmpleado, $fecha_desde);
    $stmt_fecha_hasta->execute();
    $result_fecha_hasta = $stmt_fecha_hasta->get_result()->fetch_assoc();
    // Usamos la fecha actual como fallback si no hay movimientos
    $fecha_hasta = $result_fecha_hasta && $result_fecha_hasta['ultima_fecha'] ? $result_fecha_hasta['ultima_fecha'] : date('Y-m-d H:i:s'); 

    $fecha_cierre_actual = date('Y-m-d H:i:s'); // La hora exacta en que se hace el cierre

    // 3. Insertar el registro de cierre en la tabla
    $sql_insert = "INSERT INTO cierres_caja (
                        id_empleado, fecha_cierre, fecha_movimientos_desde, fecha_movimientos_hasta, 
                        total_ingresos, total_egresos, balance_calculado, 
                        total_efectivo, total_transferencia, total_tarjeta, notas
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);

    file_put_contents("E.txt", $totales->totalEgresos);    
    // Asignamos las variables a insertar
    $notas = $data->notas ?? null; // Por si quieres agregar un campo de notas en el futuro
    $stmt_insert->bind_param(
        "isssdddddds", 
        $idEmpleado, 
        $fecha_cierre_actual, 
        $fecha_desde, 
        $fecha_hasta,
        $totales->totalIngresos,
        $totales->totalEgresos,
        $totales->balance,
        $totales->totalEfectivo,
        $totales->totalTransferencia,
        $totales->totalTarjeta,
        $notas
    );

    $stmt_insert->execute();

    // Si todo salió bien, confirmamos
    $conn->commit();
    echo json_encode(["success" => true, "message" => "Cierre de caja registrado con éxito."]);

} catch (Exception $e) {
    // Si algo falló, revertimos
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al registrar el cierre: " . $e->getMessage()]);
}

$conn->close();
?>
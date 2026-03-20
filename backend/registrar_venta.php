<?php
// --- Archivo: registrar_venta.php ---

// (Tus cabeceras CORS y de Contenido van acá, sin cambios)
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

$data = json_decode(file_get_contents("php://input"));

// Validaciones
if (!isset($data->items) || empty($data->items) || !isset($data->total) || !isset($data->metodoPago)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Datos de venta incompletos."]);
    exit;
}

// Empezamos la transacción
$conn->begin_transaction();

try {
    // 1. Insertamos el registro principal de la venta
    $sql_venta = "INSERT INTO ventas (monto_total, metodo_pago, fecha, idEmpleado) VALUES (?, ?, NOW(), ?)";
    $stmt_venta = $conn->prepare($sql_venta);
    $stmt_venta->bind_param("dsi", $data->total, $data->metodoPago, $data->idEmpleado);
    $stmt_venta->execute();
    $venta_id = $conn->insert_id; // Obtenemos el ID de la venta recién creada

    // 2. Preparamos las consultas para los movimientos y el stock
    $sql_movimiento = "INSERT INTO movimiento (accion, motivo, fecha, cantidad, idProducto, idEmpleado) VALUES ('egreso', 'venta_directa', NOW(), ?, ?, ?)";
    $stmt_movimiento = $conn->prepare($sql_movimiento);

    $sql_stock = "UPDATE stock SET cantidad = cantidad - ? WHERE idProducto = ?";
    $stmt_stock = $conn->prepare($sql_stock);

    $sql_detalle = "INSERT INTO venta_detalle (id_venta, id_producto, cantidad, precio_unitario, id_movimiento) VALUES (?, ?, ?, ?, ?)";
    $stmt_detalle = $conn->prepare($sql_detalle);

    // Creamos variables que van a cambiar en cada vuelta
    $item_cantidad = 0;
    $item_id = 0;
    $item_precio = 0.0;
    $movimiento_id = 0;

    // Vinculamos las variables a los statements UNA SOLA VEZ
    $stmt_movimiento->bind_param("iii", $item_cantidad, $item_id, $data->idEmpleado);
    $stmt_stock->bind_param("ii", $item_cantidad, $item_id);
    $stmt_detalle->bind_param("iiidi", $venta_id, $item_id, $item_cantidad, $item_precio, $movimiento_id);

    // 3. Recorremos cada item de la venta y actualizamos todo
    foreach ($data->items as $item) {
        // Actualizamos los valores de nuestras variables
        $item_cantidad = $item->cantidad;
        $item_id = $item->id;
        $item_precio = $item->precio;

        // Ejecutamos el movimiento de egreso
        $stmt_movimiento->execute();
        $movimiento_id = $conn->insert_id; // Capturamos el ID nuevo

        // Descontamos del stock
        $stmt_stock->execute();
        
        // Guardamos el detalle
        $stmt_detalle->execute();
    }

    // Si todo salió bien, confirmamos los cambios
    $conn->commit();
    echo json_encode(["success" => true, "message" => "Venta registrada y stock actualizado."]);

} catch (Exception $e) {
    // Si algo falló, revertimos todo
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>
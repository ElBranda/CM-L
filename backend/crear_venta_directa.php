<?php
// (Acá van tus headers de CORS y la conexión)

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

// Supongamos que recibimos esto del frontend
$idProducto = 1;      // ID de la Pepsi 2L
$cantidad = 2;        // Se vendieron 2
$precioUnitario = 2600; // Precio de una
$metodoPago = 'efectivo';
$montoTotal = $cantidad * $precioUnitario;

// 1. Iniciar la transacción para que sea "todo o nada"
$conn->begin_transaction();

try {
    // 2. Insertar el movimiento de egreso en la tabla 'movimiento'
    $sql_mov = "INSERT INTO movimiento (accion, motivo, fecha, cantidad, idProducto) VALUES ('egreso', 'venta', NOW(), ?, ?)";
    $stmt_mov = $conn->prepare($sql_mov);
    $stmt_mov->bind_param("ii", $cantidad, $idProducto);
    $stmt_mov->execute();
    
    // 3. ¡LA MAGIA! Capturar el ID del movimiento que se acaba de crear
    $movimientoId = $conn->insert_id;
    
    // 4. Usar ese ID para insertar la venta en la tabla 'ventas'
    $sql_venta = "INSERT INTO ventas (monto_total, metodo_pago, idProducto, idMovimiento) VALUES (?, ?, ?, ?)";
    $stmt_venta = $conn->prepare($sql_venta);
    $stmt_venta->bind_param("dsii", $montoTotal, $metodoPago, $idProducto, $movimientoId);
    $stmt_venta->execute();
    
    // 5. Si todo anduvo bien, confirmamos los cambios en la base de datos
    $conn->commit();
    
    echo json_encode(["success" => true, "message" => "Venta registrada con éxito."]);

} catch (Exception $e) {
    // Si algo falló, deshacemos todo para no dejar datos corruptos
    $conn->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>
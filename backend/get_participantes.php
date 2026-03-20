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
// header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     exit(0);
// }
require_once "manejo_CORS.php";
include "conexion.php";
$reservaId = $_GET['reservaId'];

if (!isset($reservaId)) {
    echo json_encode(["success" => false, "error" => "ID incompleta"]);
    exit;
}

// 1. OBTENEMOS LOS PARTICIPANTES Y SUS COMPRAS DE PRODUCTOS (esto queda igual)
$stmt = $conn->prepare("
  SELECT c.id AS idCliente, c.nombre AS nombreCliente, c.pagado,
         p.nombre AS descripcion,
         p.precio,
         m.cantidad,
         c.metodoPago
  FROM cliente c
  LEFT JOIN compra co ON c.id = co.idCliente
  LEFT JOIN movimiento m ON co.idMovimiento = m.id
  LEFT JOIN producto p ON m.idProducto = p.id
  WHERE c.idReserva = ?
  ORDER BY c.id
");
$stmt->bind_param("i", $reservaId);
$stmt->execute();
$result = $stmt->get_result();

$participantes = [];

while ($row = $result->fetch_assoc()) {
    $id = $row['idCliente'];

    if (!isset($participantes[$id])) {
        $participantes[$id] = [
            "id" => $id,
            "nombre" => $row['nombreCliente'],
            "pagado" => (bool)$row['pagado'],
            "compras" => [],
            "desplegado" => false,
            "metodoPago" => $row['metodoPago'],
        ];
    }

    if ($row['descripcion']) {
        $participantes[$id]['compras'][] = [
            "descripcion" => $row['descripcion'],
            "precio" => floatval($row['precio']),
            "cantidad" => intval($row['cantidad'])
        ];
    }
}

// 2. OBTENEMOS LOS PAGOS DE RESERVA Y ADELANTOS (de forma más eficiente)
if (!empty($participantes)) {
    $listaDeIds = array_keys($participantes);
    $placeholders = implode(',', array_fill(0, count($listaDeIds), '?'));
    $tipos = str_repeat('i', count($listaDeIds));

    // Consulta para la deuda de la reserva
    $stmtReserva = $conn->prepare("SELECT idCliente, monto FROM pago_reserva WHERE idCliente IN ($placeholders)");
    $stmtReserva->bind_param($tipos, ...$listaDeIds);
    $stmtReserva->execute();
    $resultReserva = $stmtReserva->get_result();

    while ($reserva = $resultReserva->fetch_assoc()) {
        $participanteId = $reserva['idCliente'];
        array_unshift($participantes[$participanteId]["compras"], [
            "descripcion" => "Reserva Cancha",
            "precio" => floatval($reserva["monto"]),
            "cantidad" => 1
        ]);
    }

    // ▼▼▼==============================================================▼▼▼
    // ▼▼▼      NUEVO: CONSULTA PARA TRAER TODOS LOS ADELANTOS/SEÑAS      ▼▼▼
    // ▼▼▼==============================================================▼▼▼
    $stmtAdelantos = $conn->prepare("SELECT idCliente, monto, descripcion FROM adelantos WHERE idReserva = ?");
    $stmtAdelantos->bind_param("i", $reservaId);
    $stmtAdelantos->execute();
    $resultAdelantos = $stmtAdelantos->get_result();

    while ($adelanto = $resultAdelantos->fetch_assoc()) {
        $participanteId = $adelanto['idCliente'];
        
        // Verificamos que el participante exista en nuestro array
        if (isset($participantes[$participanteId])) {
            // Agregamos la seña a sus "compras"
            $participantes[$participanteId]['compras'][] = [
                "descripcion" => $adelanto['descripcion'],
                // El detalle CLAVE: lo devolvemos como NEGATIVO para que reste del total
                "precio" => -floatval($adelanto['monto']),
                "cantidad" => 1
            ];
        }
    }
}

echo json_encode(array_values($participantes));

$conn->close();
?>
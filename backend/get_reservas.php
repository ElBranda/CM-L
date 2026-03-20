<?php
// Headers y logging mínimo
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

$cancha_id = isset($_GET["cancha_id"]) ? (int)$_GET["cancha_id"] : 0;
$fecha = $_GET["fecha"] ?? "";

if (!$cancha_id || !$fecha) {
    echo json_encode(["success" => false, "error" => "Parámetros incompletos"]);
    exit;
}

// Recuperar reservas (bloques) con los campos necesarios
$stmt = $conn->prepare("
    SELECT id, hora_inicio, hora_fin, nombre_usuario, tipo, estado, pagado
    FROM reservas
    WHERE cancha_id = ? AND DATE(fecha) = ?
    ORDER BY hora_inicio
");
if (!$stmt) {
    error_log("Error preparando consulta get_reservas: " . $conn->error);
    echo json_encode(["success" => false, "error" => "Error en la consulta"]);
    exit;
}
$stmt->bind_param("is", $cancha_id, $fecha);
$stmt->execute();
$result = $stmt->get_result();

$reservas = [];
while ($row = $result->fetch_assoc()) {
    // Normalizar formato de horas (HH:MM)
    $hi = date("H:i", strtotime($row["hora_inicio"]));
    $hf = date("H:i", strtotime($row["hora_fin"]));

    $reservas[] = [
        "id" => (int)$row["id"],
        "hora_inicio" => $hi,
        "hora_fin" => $hf,
        "nombre_usuario" => $row["nombre_usuario"] ?? "",
        "tipo" => $row["tipo"] ?? "diario",
        "estado" => $row["estado"],
        "pagado" => (int)$row["pagado"] === 1 ? true : false
    ];
}

// Además devolver los tramos ocupados de 30 min (para compatibilidad con versiones previas)
$horarios = [];
foreach ($reservas as $r) {
    $inicio = strtotime($r["hora_inicio"]);
    $fin = strtotime($r["hora_fin"]);
    while ($inicio < $fin) {
        $horarios[] = date("H:i", $inicio);
        $inicio = strtotime("+30 minutes", $inicio);
    }
}

// Eliminar duplicados y ordenar horarios
$horarios = array_values(array_unique($horarios));
sort($horarios);

echo json_encode([
    "success" => true,
    "reservas" => $reservas,
    "horarios" => $horarios
]);
?>

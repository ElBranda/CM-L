<?php
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Origin: http://192.168.100.9:5173");

include 'conexion.php';

$sql = "SELECT * FROM turnos";
$result = $conn->query($sql);

$turnos = [];

while($row = $result->fetch_assoc()) {
    $turnos[] = $row;
}

echo json_encode($turnos);
?>
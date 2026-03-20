<?php

$origenes_permitidos = [
    'https://localhost:5173',
    'https://192.168.1.39:5173',
    'https://192.168.100.9:5173',
    'https://10.171.50.47:5173',
    'https://10.111.15.47:5173',
    'https://192.168.1.107:5173',
    'https://10.190.204.47:5173'
];
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origen, $origenes_permitidos)) {
    header("Access-Control-Allow-Origin: $origen");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}
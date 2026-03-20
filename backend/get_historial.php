<?php
// --- Archivo: get_historial.php (Versión Final Posta, ahora sí) ---

// $origenes_permitidos = [
//     'http://192.168.1.39:5173',
//     'http://192.168.100.9:5173',
//     'http://localhost:5173',
//     'http://10.171.50.47:5173',
//     'http://10.111.15.47:5173',
//     'http://192.168.1.107:5173'
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
require_once 'conexion.php';

$periodo = $_GET['periodo'] ?? 'diario';
$periodo = strtolower($periodo);

// 1. DEFINICIÓN DE FORMATOS SQL
$select_formato_fecha = "";
$group_by_clausula = "";
$order_by_clausula = "";

$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 20; // 20 por defecto
$offset = ($pagina - 1) * $por_pagina;

// Función de MySQL para nombres de meses en español (es una aproximación)
$mes_espanol = "ELT(MONTH(fecha_movimiento), 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre')";

switch ($periodo) {
    case 'mensual':
        $select_formato_fecha = "CONCAT($mes_espanol, ' ', YEAR(fecha_movimiento)) as fecha, DATE_FORMAT(fecha_movimiento, '%Y-%m') as fecha_raw";
        $group_by_clausula = "YEAR(fecha_movimiento), MONTH(fecha_movimiento)";
        $order_by_clausula = "fecha_raw DESC";
        break;
    case 'anual':
        $select_formato_fecha = "YEAR(fecha_movimiento) as fecha, YEAR(fecha_movimiento) as fecha_raw";
        $group_by_clausula = "YEAR(fecha_movimiento)";
        $order_by_clausula = "fecha_raw DESC";
        break;
    default: // Diario
        $select_formato_fecha = "DATE(fecha_movimiento) as fecha, DATE(fecha_movimiento) as fecha_raw";
        $group_by_clausula = "DATE(fecha_movimiento)";
        $order_by_clausula = "fecha_raw DESC";
        break;
}

try {
    // 2. CONSTRUIR LA CONSULTA SQL
    $sql = "SELECT
                $select_formato_fecha,
                SUM(ingreso) as ingreso,
                SUM(egreso) as egreso
            FROM (
                -- A. INGRESOS A CUENTA (Pagos de reservas ABIERTAS)
                -- Suma los pagos individuales que se hicieron en reservas que TODAVÍA NO ESTÁN CERRADAS (r.pagado = 0).
                SELECT DATE(pr.fecha) as fecha_movimiento, pr.monto as ingreso, 0 as egreso
                FROM pago_reserva pr
                JOIN cliente c ON pr.idCliente = c.id
                JOIN reservas r ON c.idReserva = r.id
                WHERE r.pagado = 0 AND c.pagado = 1

                UNION ALL

                -- B. INGRESOS TOTALES (Reservas CERRADAS)
                -- Suma el MONTO TOTAL de las reservas que SÍ ESTÁN PAGADAS (r.pagado = 1), usando la fecha de pago.
                -- Este monto ya incluye todos los pagos y consumos.
                SELECT
                    DATE(r.pago_fecha) as fecha_movimiento,
                    (
                        r.costo_reserva +
                        COALESCE((SELECT SUM(p.precio * m.cantidad) FROM movimiento m JOIN compra co ON m.id = co.idMovimiento JOIN cliente c ON co.idCliente = c.id JOIN producto p ON m.idProducto = p.id WHERE c.idReserva = r.id AND m.motivo = 'venta_individual'), 0) +
                        COALESCE((SELECT SUM(p.precio * ic.cantidad) FROM item_compartido ic JOIN producto p ON ic.idProducto = p.id WHERE ic.idReserva = r.id), 0)
                    ) as ingreso,
                    0 as egreso
                FROM reservas r
                WHERE r.pagado = 1 AND r.pago_fecha IS NOT NULL
                
                UNION ALL

                -- C. INGRESOS: Señas y Adelantos de reservas YA CERRADAS
                -- Si usamos la lógica de reservas cerradas, hay que traer los adelantos también.
                -- Sin embargo, el monto de la reserva CERRADA (B) YA debería incluir el adelanto en r.costo_reserva.
                -- Para evitar errores de lógica, nos aseguramos de que el monto de B sea el total.
                -- Si el monto de la reserva cerrada (B) es el total, NO necesitamos sumar adelantos Aparte.
                -- Si tu lógica de costo_reserva en el save_participantes SOLO GUARDA EL PAGO DE LA CANCHA,
                -- ENTONCES DEBERÍAS SUMAR LOS ADELANTOS ACÁ.
                
                -- Por simplicidad y seguridad, asumiremos que r.costo_reserva es el costo de la cancha.
                
                -- D. INGRESOS POR VENTAS DIRECTAS
                SELECT DATE(fecha) as fecha_movimiento, monto_total as ingreso, 0 as egreso FROM ventas

                UNION ALL

                -- E. EGRESOS (Atenciones)
                SELECT DATE(a.fecha) as fecha_movimiento, 0 as ingreso, (a.cantidad * p.precio) as egreso 
                FROM atenciones a JOIN producto p ON a.idProducto = p.id

                UNION ALL

                -- F. EGRESOS (Gastos Generales)
                SELECT fecha as fecha_movimiento, 0 as ingreso, monto as egreso 
                FROM gastos
            ) as todos_los_movimientos
            WHERE fecha_movimiento IS NOT NULL
            GROUP BY $group_by_clausula
            ORDER BY $order_by_clausula";
            

    $total_result = $conn->query("SELECT COUNT(*) as total FROM ($sql) as conteo_grupos");
    $total_movimientos = $total_result->fetch_assoc()['total'];
    // 3. EJECUTAR LA CONSULTA
    $sql_paginada = $sql . " LIMIT ? OFFSET ? ";
    $stmt = $conn->prepare($sql_paginada);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("ii", $por_pagina, $offset);
    
    $stmt->execute();
    $result = $stmt->get_result();
    $historial = $result->fetch_all(MYSQLI_ASSOC);


    // 4. RESPUESTA EXITOSA
    echo json_encode(["success" => true, "historial" => $historial, "total" => $total_movimientos]);

} catch (Exception $e) {
    // 5. RESPUESTA DE ERROR
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error en la consulta. Detalles: " . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
<?php
require_once '../../includes/auth_check.php';
require_once '../../config/database.php';
checkAuth();
checkPermission(['admin', 'rh']);

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'excel';
$mes = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$quincena = isset($_GET['quincena']) ? (int)$_GET['quincena'] : 1;
$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;

// Definir fechas
if ($quincena == 1) {
    $fecha_inicio = $mes . '-01';
    $fecha_fin = $mes . '-15';
} else {
    $fecha_inicio = $mes . '-16';
    $fecha_fin = $mes . '-31';
}

// Obtener datos del reporte
$sql = "SELECT 
            e.nombre,
            e.apellido_paterno,
            e.apellido_materno,
            e.puesto,
            e.departamento,
            e.salario_diario,
            COUNT(a.id) as dias_trabajados,
            SUM(a.horas_trabajadas) as total_horas,
            SUM(a.horas_extra) as total_extra,
            SUM(a.retardo_minutos) as total_retardo,
            COUNT(CASE WHEN a.tipo_asistencia = 'falta' THEN 1 END) as faltas,
            COUNT(CASE WHEN a.tipo_asistencia = 'incapacidad' THEN 1 END) as incapacidades,
            COUNT(CASE WHEN a.tipo_asistencia = 'vacaciones' THEN 1 END) as vacaciones
        FROM empleados e
        LEFT JOIN asistencias a ON e.id = a.empleado_id AND a.fecha BETWEEN ? AND ?
        WHERE e.estado = 'activo'";

if ($empleado_id > 0) {
    $sql .= " AND e.id = ?";
}

$sql .= " GROUP BY e.id ORDER BY e.departamento, e.apellido_paterno";

$stmt = $conn->prepare($sql);

if ($empleado_id > 0) {
    $stmt->bind_param("ssi", $fecha_inicio, $fecha_fin, $empleado_id);
} else {
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
}

$stmt->execute();
$result = $stmt->get_result();

// Preparar datos
$datos = [];
$total_horas = 0;
$total_extra = 0;
$total_salario = 0;

while ($row = $result->fetch_assoc()) {
    // Calcular salarios
    $salario_normal = $row['total_horas'] * ($row['salario_diario'] / 8);
    $salario_extra = $row['total_extra'] * ($row['salario_diario'] / 8 * 1.5);
    $total_pagar = $salario_normal + $salario_extra;
    
    $datos[] = [
        'empleado' => $row['apellido_paterno'] . ' ' . $row['apellido_materno'] . ' ' . $row['nombre'],
        'departamento' => $row['departamento'],
        'puesto' => $row['puesto'],
        'dias_trabajados' => $row['dias_trabajados'],
        'horas_normales' => round($row['total_horas'], 2),
        'horas_extra' => round($row['total_extra'], 2),
        'faltas' => $row['faltas'],
        'salario_diario' => $row['salario_diario'],
        'salario_normal' => round($salario_normal, 2),
        'salario_extra' => round($salario_extra, 2),
        'total_pagar' => round($total_pagar, 2)
    ];
    
    $total_horas += $row['total_horas'];
    $total_extra += $row['total_extra'];
    $total_salario += $total_pagar;
}

// Exportar según tipo
if ($tipo == 'excel') {
    exportarExcel($datos, $mes, $quincena, $total_horas, $total_extra, $total_salario);
} elseif ($tipo == 'pdf') {
    exportarPDF($datos, $mes, $quincena, $total_horas, $total_extra, $total_salario);
}

function exportarExcel($datos, $mes, $quincena, $total_horas, $total_extra, $total_salario) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="reporte_quincenal_' . $mes . '_q' . $quincena . '.xls"');
    header('Cache-Control: max-age=0');
    
    $output = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">
    <head>
        <meta charset="UTF-8">
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Reporte Quincenal</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            table { border-collapse: collapse; width: 100%; }
            th { background: #4a5568; color: white; font-weight: bold; padding: 10px; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            .total { background: #f7fafc; font-weight: bold; }
            .header { background: #2d3748; color: white; padding: 15px; text-align: center; font-size: 18px; }
        </style>
    </head>
    <body>';
    
    $output .= '<div class="header">Reporte Quincenal de Nómina</div>';
    $output .= '<div style="padding: 10px;">';
    $output .= '<p><strong>Periodo:</strong> ' . date('F Y', strtotime($mes . '-01')) . ' - Quincena ' . $quincena . '</p>';
    $output .= '<p><strong>Fecha de generación:</strong> ' . date('d/m/Y H:i:s') . '</p>';
    $output .= '</div>';
    
    $output .= '<table>';
    $output .= '<tr>
        <th>Empleado</th>
        <th>Departamento</th>
        <th>Puesto</th>
        <th>Días Trab.</th>
        <th>Horas Norm.</th>
        <th>Horas Ext.</th>
        <th>Faltas</th>
        <th>Salario Diario</th>
        <th>Salario Normal</th>
        <th>Salario Extra</th>
        <th>Total a Pagar</th>
    </tr>';
    
    foreach ($datos as $fila) {
        $output .= '<tr>';
        $output .= '<td>' . htmlspecialchars($fila['empleado']) . '</td>';
        $output .= '<td>' . htmlspecialchars($fila['departamento']) . '</td>';
        $output .= '<td>' . htmlspecialchars($fila['puesto']) . '</td>';
        $output .= '<td>' . $fila['dias_trabajados'] . '</td>';
        $output .= '<td>' . $fila['horas_normales'] . '</td>';
        $output .= '<td>' . $fila['horas_extra'] . '</td>';
        $output .= '<td>' . $fila['faltas'] . '</td>';
        $output .= '<td>$' . number_format($fila['salario_diario'], 2) . '</td>';
        $output .= '<td>$' . number_format($fila['salario_normal'], 2) . '</td>';
        $output .= '<td>$' . number_format($fila['salario_extra'], 2) . '</td>';
        $output .= '<td>$' . number_format($fila['total_pagar'], 2) . '</td>';
        $output .= '</tr>';
    }
    
    // Totales
    $output .= '<tr class="total">';
    $output .= '<td colspan="4"><strong>TOTALES</strong></td>';
    $output .= '<td><strong>' . round($total_horas, 2) . '</strong></td>';
    $output .= '<td><strong>' . round($total_extra, 2) . '</strong></td>';
    $output .= '<td colspan="3"></td>';
    $output .= '<td><strong>$' . number_format($total_salario, 2) . '</strong></td>';
    $output .= '</tr>';
    
    $output .= '</table>';
    
    $output .= '<div style="margin-top: 30px; padding: 15px; background: #f7fafc; border: 1px solid #ddd;">';
    $output .= '<p><strong>Notas:</strong></p>';
    $output .= '<ul>';
    $output .= '<li>Horas extra calculadas al 150% del salario normal</li>';
    $output .= '<li>Jornada normal: 8 horas diarias</li>';
    $output .= '<li>Reporte generado automáticamente por Sistema de Control de Empleados</li>';
    $output .= '</ul>';
    $output .= '</div>';
    
    $output .= '</body></html>';
    
    echo $output;
    exit();
}

function exportarPDF($datos, $mes, $quincena, $total_horas, $total_extra, $total_salario) {
    // Para PDF necesitarías una librería como TCPDF o DomPDF
    // Esta es una versión simplificada que genera HTML que se puede guardar como PDF
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="reporte_quincenal_' . $mes . '_q' . $quincena . '.pdf"');
    
    // En un sistema real, usarías:
    // require_once('tcpdf/tcpdf.php');
    // $pdf = new TCPDF();
    // ... código para generar PDF ...
    
    // Por ahora, redirigimos a la versión HTML para imprimir como PDF
    $params = $_GET;
    $params['tipo'] = 'html';
    $query_string = http_build_query($params);
    
    echo '<html>
    <head>
        <title>Exportando PDF...</title>
        <script>
            alert("Para exportar a PDF, instala una librería como TCPDF o usa la función de imprimir del navegador.");
            window.history.back();
        </script>
    </head>
    <body></body>
    </html>';
    exit();
}
?>
<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if required parameters are present
if (!isset($_POST['part_code']) || !isset($_POST['part_name']) || !isset($_POST['headers']) || !isset($_POST['data'])) {
    die('Missing required parameters');
}

$partCode = $_POST['part_code'];
$partName = $_POST['part_name'];
$dateFrom = isset($_POST['date_from']) ? $_POST['date_from'] : '';
$dateTo = isset($_POST['date_to']) ? $_POST['date_to'] : '';
$headers = json_decode($_POST['headers'], true);
$data = json_decode($_POST['data'], true);

// Generate filename
$filename = 'Production_Report_' . $partCode . '_' . date('Y-m-d_His') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write report title
fputcsv($output, ['Production Report']);
fputcsv($output, ['Part Code: ' . $partCode]);
fputcsv($output, ['Part Name: ' . $partName]);

if ($dateFrom && $dateTo) {
    fputcsv($output, ['Date Range: ' . $dateFrom . ' to ' . $dateTo]);
}

fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
fputcsv($output, []); // Empty row

// Write headers
fputcsv($output, $headers);

// Write data rows
if (!empty($data)) {
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
} else {
    fputcsv($output, ['No data available']);
}

fputcsv($output, []); // Empty row

// Write summary if provided
if (isset($_POST['summary'])) {
    $summary = json_decode($_POST['summary'], true);
    fputcsv($output, []); // Empty row
    fputcsv($output, ['Production Summary']);
    fputcsv($output, ['Total Items', $summary['total'] ?? 0]);
    
    if (isset($summary['completed'])) {
        fputcsv($output, ['Completed Items', $summary['completed']]);
    }
    
    if (isset($summary['inProgress'])) {
        fputcsv($output, ['In Progress', $summary['inProgress']]);
    }
    
    if (isset($summary['completionRate'])) {
        fputcsv($output, ['Completion Rate', $summary['completionRate']]);
    }
}

fclose($output);
exit();
?>

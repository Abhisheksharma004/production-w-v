<?php
session_start();

// Check if user is logged in (either admin or line user)
$isAdminUser = isset($_SESSION['user_id']);
$isLineUser = isset($_SESSION['line_id']) && $_SESSION['user_type'] === 'line';

if (!$isAdminUser && !$isLineUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partId = isset($_POST['part_id']) ? intval($_POST['part_id']) : 0;
    $tableName = isset($_POST['table_name']) ? $_POST['table_name'] : '';
    $stageNames = isset($_POST['stage_names']) ? json_decode($_POST['stage_names'], true) : [];
    $dateFrom = isset($_POST['date_from']) ? $_POST['date_from'] : '';
    $dateTo = isset($_POST['date_to']) ? $_POST['date_to'] : '';

    if ($partId <= 0 || empty($tableName) || empty($stageNames)) {
        echo json_encode(['error' => 'Invalid parameters']);
        exit();
    }

    try {
        $conn = getSQLSrvConnection();
        if ($conn === false) {
            echo json_encode(['error' => 'Database connection failed']);
            exit();
        }

        // Build query
        $sql = "SELECT * FROM [$tableName] WHERE part_id = ?";
        $params = [$partId];

        // Add date filters if provided
        if (!empty($dateFrom)) {
            $sql .= " AND CAST(created_at AS DATE) >= ?";
            $params[] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $sql .= " AND CAST(created_at AS DATE) <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY id DESC";

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            echo json_encode(['error' => 'Query execution failed', 'details' => sqlsrv_errors()]);
            exit();
        }

        $data = [];
        $totalItems = 0;
        $completedItems = 0;
        $inProgressItems = 0;

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $totalItems++;
            
            // Check completion status
            $allStagesComplete = true;
            $rowData = [
                'id' => $row['id'],
                'created_at' => $row['created_at'] ? $row['created_at']->format('Y-m-d H:i:s') : '',
                'stages' => []
            ];

            foreach ($stageNames as $stageIndex => $stageName) {
                $stageNumber = $stageIndex + 1;
                $columnName = 'stage_' . $stageNumber . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $stageName));
                
                $value = isset($row[$columnName]) ? $row[$columnName] : '';
                $rowData['stages'][] = $value;
                
                if (empty($value)) {
                    $allStagesComplete = false;
                }
            }

            $rowData['status'] = $allStagesComplete ? 'Complete' : 'Incomplete';
            
            if ($allStagesComplete) {
                $completedItems++;
            } else {
                $inProgressItems++;
            }

            $data[] = $rowData;
        }

        sqlsrv_free_stmt($stmt);

        $completionRate = $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 1) : 0;

        echo json_encode([
            'success' => true,
            'data' => $data,
            'summary' => [
                'total' => $totalItems,
                'completed' => $completedItems,
                'inProgress' => $inProgressItems,
                'completionRate' => $completionRate
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['error' => 'Exception occurred', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>

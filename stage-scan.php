<?php
// No session or authentication required - public access page
require_once 'config/database.php';

$message = '';
$messageType = '';
$allParts = [];
$selectedPart = null;
$selectedStageIndex = null;
$stageMetadata = null;

// Get all parts for dropdown
$conn = getSQLSrvConnection();
$sql = "SELECT id, part_code, part_name FROM parts ORDER BY part_code";
$stmt = sqlsrv_query($conn, $sql);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $allParts[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action']) && $_POST['action'] === 'select_part') {
        $partId = intval($_POST['part_id']);
        
        if ($partId > 0) {
            // Get part information
            $sql = "SELECT id, part_code, part_name FROM parts WHERE id = ?";
            $stmt = sqlsrv_query($conn, $sql, array($partId));
            
            if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $selectedPart = $row;
                
                // Get stage metadata for this part
                $sql = "SELECT table_name, stage_names FROM stages_metadata WHERE part_id = ?";
                $stmt = sqlsrv_query($conn, $sql, array($partId));
                
                if ($stmt && $metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $stageMetadata = $metaRow;
                    $stageMetadata['stage_names'] = json_decode($metaRow['stage_names'], true);
                } else {
                    $message = 'No stages configured for this part';
                    $messageType = 'warning';
                    $selectedPart = null;
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'select_stage') {
        $partId = intval($_POST['part_id']);
        $stageIndex = intval($_POST['stage_index']);
        
        // Reload part and stage data
        $sql = "SELECT id, part_code, part_name FROM parts WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($partId));
        
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $selectedPart = $row;
            $selectedStageIndex = $stageIndex;
            
            $sql = "SELECT table_name, stage_names FROM stages_metadata WHERE part_id = ?";
            $stmt = sqlsrv_query($conn, $sql, array($partId));
            
            if ($stmt && $metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $stageMetadata = $metaRow;
                $stageMetadata['stage_names'] = json_decode($metaRow['stage_names'], true);
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'scan_data') {
        $partId = intval($_POST['part_id']);
        $tableName = $_POST['table_name'];
        $stageColumn = $_POST['stage_column'];
        $stageValue = trim($_POST['stage_value']);
        $stageIndex = intval($_POST['stage_index']);
        
        if (!empty($stageValue)) {
            // Get stage metadata
            $sql = "SELECT stage_names FROM stages_metadata WHERE part_id = ?";
            $stmt = sqlsrv_query($conn, $sql, array($partId));
            $metaData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $stageNames = json_decode($metaData['stage_names'], true);
            
            if ($stageIndex === 0) {
                // Stage 1: Check if this value already exists, if yes update, if no insert
                $sql = "SELECT id FROM [$tableName] WHERE $stageColumn = ?";
                $params = array($stageValue);
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt && $existingRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    // Value already exists, update the timestamp
                    $rowId = $existingRow['id'];
                    $sql = "UPDATE [$tableName] SET updated_at = GETDATE() WHERE id = ?";
                    $params = array($rowId);
                    $stmt = sqlsrv_query($conn, $sql, $params);
                    
                    if ($stmt) {
                        $message = 'Stage 1 data already exists - timestamp updated!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error updating stage data: ' . print_r(sqlsrv_errors(), true);
                        $messageType = 'error';
                    }
                } else {
                    // New value, insert new row
                    $sql = "INSERT INTO [$tableName] (part_id, $stageColumn, created_at, updated_at) 
                            VALUES (?, ?, GETDATE(), GETDATE())";
                    $params = array($partId, $stageValue);
                    $stmt = sqlsrv_query($conn, $sql, $params);
                    
                    if ($stmt) {
                        $message = 'Stage 1 data recorded successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error recording stage data: ' . print_r(sqlsrv_errors(), true);
                        $messageType = 'error';
                    }
                }
            } else {
                // Stage 2+: Find matching row from PREVIOUS stage and update
                // ONLY store data if previous stage has matching value
                $previousStageName = $stageNames[$stageIndex - 1];
                $previousStageNumber = $stageIndex; // Previous stage number (0-indexed + 1 = current index)
                $previousStageColumn = 'stage_' . $previousStageNumber . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $previousStageName));
                
                // Check if a row exists with the scanned value in the previous stage
                $sql = "SELECT id FROM [$tableName] WHERE $previousStageColumn = ?";
                $params = array($stageValue);
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt && $existingRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    // CONDITION MET: Row found in previous stage, now update current stage
                    $rowId = $existingRow['id'];
                    $sql = "UPDATE [$tableName] SET $stageColumn = ?, updated_at = GETDATE() WHERE id = ?";
                    $params = array($stageValue, $rowId);
                    $stmt = sqlsrv_query($conn, $sql, $params);
                    
                    if ($stmt) {
                        $message = "✓ Stage " . ($stageIndex + 1) . " data recorded! Matched with Stage " . $previousStageNumber . ".";
                        $messageType = 'success';
                    } else {
                        $message = '✗ Database error: ' . print_r(sqlsrv_errors(), true);
                        $messageType = 'error';
                    }
                } else {
                    // CONDITION NOT MET: No matching data in previous stage - DATA NOT STORED
                    $message = '✗ Error: This item was not found in Stage ' . $previousStageNumber . '. Please scan it in Stage ' . $previousStageNumber . ' first. Value: ' . htmlspecialchars($stageValue);
                    $messageType = 'error';
                }
            }
            
            // Keep the part and stage selected for continuous scanning
            $sql = "SELECT id, part_code, part_name FROM parts WHERE id = ?";
            $stmt = sqlsrv_query($conn, $sql, array($partId));
            
            if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $selectedPart = $row;
                $selectedStageIndex = $stageIndex;
                
                $sql = "SELECT table_name, stage_names FROM stages_metadata WHERE part_id = ?";
                $stmt = sqlsrv_query($conn, $sql, array($partId));
                
                if ($stmt && $metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $stageMetadata = $metaRow;
                    $stageMetadata['stage_names'] = json_decode($metaRow['stage_names'], true);
                }
            }
        } else {
            $message = 'Please enter a value';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stage Scanning - Production Management</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            /* background: #f1f5f9; */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px 40px;
            max-width: 600px;
            width: 100%;
        }

        h1 {
            color: #1e293b;
            text-align: center;
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: 700;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
        }

        input[type="text"],
        select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8fafc;
        }

        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        select {
            cursor: pointer;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(17, 153, 142, 0.4);
        }

        .btn-secondary {
            background: #64748b;
            color: white;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
        }

        .part-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #3b82f6;
        }

        .part-info h3 {
            color: #1e293b;
            margin-bottom: 10px;
            font-size: 18px;
            font-weight: 600;
        }

        .part-info p {
            color: #64748b;
            margin: 5px 0;
            font-size: 14px;
        }

        .part-info p strong {
            color: #1e293b;
        }

        .stage-form {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .stage-title {
            color: #3b82f6;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .divider {
            height: 1px;
            background: #e0e0e0;
            margin: 30px 0;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            padding-bottom: 10px;
        }

        .step::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e0e0e0;
            z-index: -1;
        }

        .step:last-child::after {
            display: none;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #94a3b8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .step.active .step-number {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .step.completed .step-number {
            background: #38ef7d;
            color: white;
        }

        .step-label {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
        }

        .step.active .step-label {
            color: #3b82f6;
            font-weight: 600;
        }

        .step.completed .step-label {
            color: #38ef7d;
        }

        @media (max-width: 600px) {
            .container {
                padding: 32px 24px;
            }

            h1 {
                font-size: 24px;
            }

            .step-label {
                font-size: 10px;
            }

            .step-number {
                width: 25px;
                height: 25px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Stage Scanning</h1>
        <p class="subtitle">Select part, select stage, then scan</p>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step <?php echo (!$selectedPart ? 'active' : 'completed'); ?>">
                <div class="step-number">1</div>
                <div class="step-label">Select Part</div>
            </div>
            <div class="step <?php echo ($selectedPart && $selectedStageIndex === null ? 'active' : ($selectedStageIndex !== null ? 'completed' : '')); ?>">
                <div class="step-number">2</div>
                <div class="step-label">Select Stage</div>
            </div>
            <div class="step <?php echo ($selectedStageIndex !== null ? 'active' : ''); ?>">
                <div class="step-number">3</div>
                <div class="step-label">Scan Data</div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$selectedPart): ?>
            <!-- Step 1: Select Part -->
            <form method="POST" action="">
                <input type="hidden" name="action" value="select_part">
                
                <div class="form-group">
                    <label for="part_id">Step 1: Select Part</label>
                    <select id="part_id" name="part_id" required autofocus>
                        <option value="">-- Choose a Part --</option>
                        <?php foreach ($allParts as $part): ?>
                            <option value="<?php echo $part['id']; ?>">
                                <?php echo htmlspecialchars($part['part_code']) . ' - ' . htmlspecialchars($part['part_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Continue to Stage Selection</button>
            </form>

        <?php elseif ($selectedStageIndex === null): ?>
            <!-- Step 2: Select Stage -->
            <div class="part-info">
                <h3>Selected Part</h3>
                <p><strong>Part Code:</strong> <?php echo htmlspecialchars($selectedPart['part_code']); ?></p>
                <p><strong>Part Name:</strong> <?php echo htmlspecialchars($selectedPart['part_name']); ?></p>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="select_stage">
                <input type="hidden" name="part_id" value="<?php echo $selectedPart['id']; ?>">
                
                <div class="form-group">
                    <label for="stage_index">Step 2: Select Stage</label>
                    <select id="stage_index" name="stage_index" required autofocus>
                        <option value="">-- Choose a Stage --</option>
                        <?php foreach ($stageMetadata['stage_names'] as $index => $stageName): ?>
                            <option value="<?php echo $index; ?>">
                                Stage <?php echo ($index + 1); ?>: <?php echo htmlspecialchars($stageName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Continue to Scanning</button>
                <button type="submit" class="btn btn-secondary" formaction="" formmethod="get">Change Part</button>
            </form>

        <?php else: ?>
            <!-- Step 3: Scan Data -->
            <?php 
                $stageName = $stageMetadata['stage_names'][$selectedStageIndex];
                $stageNumber = $selectedStageIndex + 1;
                $stageColumn = 'stage_' . $stageNumber . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $stageName));
            ?>
            
            <div class="part-info">
                <h3>Selected Part</h3>
                <p><strong>Part Code:</strong> <?php echo htmlspecialchars($selectedPart['part_code']); ?></p>
                <p><strong>Part Name:</strong> <?php echo htmlspecialchars($selectedPart['part_name']); ?></p>
            </div>

            <div class="stage-form">
                <div class="stage-title">Stage <?php echo $stageNumber; ?>: <?php echo htmlspecialchars($stageName); ?></div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="scan_data">
                    <input type="hidden" name="part_id" value="<?php echo $selectedPart['id']; ?>">
                    <input type="hidden" name="table_name" value="<?php echo htmlspecialchars($stageMetadata['table_name']); ?>">
                    <input type="hidden" name="stage_column" value="<?php echo $stageColumn; ?>">
                    <input type="hidden" name="stage_index" value="<?php echo $selectedStageIndex; ?>">
                    
                    <div class="form-group">
                        <label for="stage_value">Step 3: Scan or Enter Data</label>
                        <input type="text" 
                               id="stage_value" 
                               name="stage_value" 
                               placeholder="Scan barcode or enter value" 
                               required 
                               autofocus>
                    </div>

                    <button type="submit" class="btn btn-success">✓ Record Data</button>
                </form>
            </div>

            <script>
                // Auto-focus and clear input for continuous scanning
                document.addEventListener('DOMContentLoaded', function() {
                    const input = document.getElementById('stage_value');
                    if (input) {
                        // Clear the input field on page load (after successful submission)
                        input.value = '';
                        // Focus the input for the next scan
                        input.focus();
                        
                        // Optional: Select all text when clicking (for easy replacement)
                        input.addEventListener('click', function() {
                            this.select();
                        });
                    }
                });
            </script>

            <div class="divider"></div>

            <form method="GET" action="">
                <button type="submit" class="btn btn-secondary">Start New Scan</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

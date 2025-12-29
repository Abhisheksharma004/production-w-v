<?php
// No session or authentication required - public access page
require_once 'config/database.php';

$message = '';
$messageType = '';
$selectedPart = null;
$selectedStageIndex = null;
$selectedBin = null;
$materialData = null;
$stageMetadata = null;
$stageQuantity = null;

$conn = getSQLSrvConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action']) && $_POST['action'] === 'reset_workflow') {
        // Reset all variables to start fresh
        $selectedPart = null;
        $selectedStageIndex = null;
        $selectedBin = null;
        $materialData = null;
        $stageMetadata = null;
        $message = '⟳ Workflow reset. Select a new part to begin.';
        $messageType = 'success';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'select_part') {
        $partId = intval($_POST['part_id']);
        
        if ($partId > 0) {
            // Get part information
            $sql = "SELECT id, part_code, part_name FROM parts WHERE id = ?";
            $stmt = sqlsrv_query($conn, $sql, array($partId));
            
            if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $selectedPart = $row;
                
                // Get stage metadata
                $sql = "SELECT table_name, stage_names FROM stages_metadata WHERE part_id = ?";
                $stmt = sqlsrv_query($conn, $sql, array($partId));
                
                if ($stmt && $metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $stageMetadata = $metaRow;
                    $stageMetadata['stage_names'] = json_decode($metaRow['stage_names'], true);
                    $message = '✓ Part selected! Now choose your stage.';
                    $messageType = 'success';
                } else {
                    $message = 'No stages configured for this part';
                    $messageType = 'warning';
                    $selectedPart = null;
                }
            }
        } else {
            $message = 'Please select a part';
            $messageType = 'error';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'select_stage') {
        $partId = intval($_POST['part_id']);
        $stageIndex = intval($_POST['stage_index']);
        
        // Reload part and stage info
        $sql = "SELECT id, part_code, part_name FROM parts WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($partId));
        
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $selectedPart = $row;
            $selectedStageIndex = $stageIndex;
            
            // Get stage metadata
            $sql = "SELECT table_name, stage_names FROM stages_metadata WHERE part_id = ?";
            $stmt = sqlsrv_query($conn, $sql, array($partId));
            
            if ($stmt && $metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $stageMetadata = $metaRow;
                $stageMetadata['stage_names'] = json_decode($metaRow['stage_names'], true);
                $message = '✓ Stage selected! Now scan the bin barcode.';
                $messageType = 'success';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'scan_bin') {
        $partId = intval($_POST['part_id']);
        $stageIndex = intval($_POST['stage_index']);
        $binBarcode = trim($_POST['bin_barcode']);
        
        // Reload part and stage info
        $sql = "SELECT id, part_code, part_name FROM parts WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($partId));
        
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $selectedPart = $row;
            $selectedStageIndex = $stageIndex;
            
            // Get stage metadata
            $sql = "SELECT table_name, stage_names FROM stages_metadata WHERE part_id = ?";
            $stmt = sqlsrv_query($conn, $sql, array($partId));
            
            if ($stmt && $metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $stageMetadata = $metaRow;
                $stageMetadata['stage_names'] = json_decode($metaRow['stage_names'], true);
            }
        }
        
        if (!empty($binBarcode)) {
            // Check material_in table for bin barcode with open status
            $sql = "SELECT m.*, p.part_code, p.part_name, w.scale_code, w.scale_name 
                    FROM material_in m 
                    LEFT JOIN parts p ON m.part_id = p.id
                    LEFT JOIN wing_scales w ON m.wing_scale_id = w.id 
                    WHERE (w.scale_code = ? OR m.wing_scale_code = ?) 
                    AND LOWER(m.production_status) = 'open' 
                    ORDER BY m.created_at DESC";
            $stmt = sqlsrv_query($conn, $sql, array($binBarcode, $binBarcode));
            
            if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Found open material with this bin
                
                // Verify part matches (STRICT MODE)
                if ($row['part_id'] == $partId) {
                    // Success - parts match, proceed to Step 4
                    $materialData = $row;
                    $selectedBin = $binBarcode;
                    $message = '✓ Bin verified! Material matches selected part. Enter quantity to save.';
                    $messageType = 'success';
                } else {
                    // Error - parts don't match, stay on Step 3
                    $selectedBin = null;
                    $message = '✗ Error: Bin contains ' . htmlspecialchars($row['part_code']) . ' but you selected ' . htmlspecialchars($selectedPart['part_code']) . '. Please scan the correct bin or start over.';
                    $messageType = 'error';
                }
            } else {
                // No open material found for this bin
                $message = '✗ Warning: No OPEN material found for bin barcode "' . htmlspecialchars($binBarcode) . '". Please check the barcode or material status.';
                $messageType = 'error';
            }
        } else {
            $message = 'Please scan or enter bin barcode';
            $messageType = 'error';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'save_stage_data') {
        $partId = intval($_POST['part_id']);
        $stageIndex = intval($_POST['stage_index']);
        $stageQuantity = isset($_POST['stage_quantity']) ? intval($_POST['stage_quantity']) : null;
        $binBarcode = isset($_POST['bin_barcode']) ? trim($_POST['bin_barcode']) : null;
        
        // Get stage metadata and table name
        $sql = "SELECT table_name, stage_names FROM stages_metadata WHERE part_id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($partId));
        
        if ($stmt && $metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $tableName = $metaRow['table_name'];
            $stageNames = json_decode($metaRow['stage_names'], true);
            $stageName = $stageNames[$stageIndex];
            $stageNumber = $stageIndex + 1;
            
            // Column names for this stage
            $stageColumn = 'stage_' . $stageNumber . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $stageName));
            $stageQtyColumn = $stageColumn . '_qty';
            
            // Get material data to retrieve batch number
            $batchNumber = '';
            if ($binBarcode) {
                $sql = "SELECT m.batch_number 
                        FROM material_in m 
                        LEFT JOIN wing_scales w ON m.wing_scale_id = w.id 
                        WHERE (w.scale_code = ? OR m.wing_scale_code = ?) 
                        AND LOWER(m.production_status) = 'open' 
                        ORDER BY m.created_at DESC";
                $stmt = sqlsrv_query($conn, $sql, array($binBarcode, $binBarcode));
                if ($stmt && $batchRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $batchNumber = $batchRow['batch_number'];
                }
            }
            
            // Format: "Bin - Batch Number"
            $stageValue = $binBarcode . ' - ' . $batchNumber;
            
            // Validate quantity: Check if previous stage exists and compare quantities
            if ($stageIndex > 0) {
                // Get the previous stage quantity column
                $previousStageName = $stageNames[$stageIndex - 1];
                $previousStageNumber = $stageIndex; // Previous stage number (0-indexed + 1 = current index)
                $previousStageQtyColumn = 'stage_' . $previousStageNumber . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $previousStageName)) . '_qty';
                
                // Check if a row exists with this bin-batch value
                $whereConditions = array();
                $searchParams = array();
                foreach ($stageNames as $idx => $name) {
                    $stgNum = $idx + 1;
                    $stgCol = 'stage_' . $stgNum . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name));
                    $whereConditions[] = "$stgCol = ?";
                    $searchParams[] = $stageValue;
                }
                $whereClause = implode(' OR ', $whereConditions);
                
                $sql = "SELECT $previousStageQtyColumn FROM [$tableName] WHERE $whereClause";
                $stmt = sqlsrv_query($conn, $sql, $searchParams);
                
                if ($stmt && $prevRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $previousQuantity = $prevRow[$previousStageQtyColumn];
                    
                    if ($previousQuantity !== null && $stageQuantity > $previousQuantity) {
                        $message = '✗ Error: Stage quantity (' . $stageQuantity . ') cannot be greater than previous stage quantity (' . $previousQuantity . ')';
                        $messageType = 'error';
                        
                        // Reload data to show the form again
                        $sql = "SELECT id, part_code, part_name FROM parts WHERE id = ?";
                        $stmt = sqlsrv_query($conn, $sql, array($partId));
                        
                        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $selectedPart = $row;
                            $selectedStageIndex = null;
                            $selectedBin = $binBarcode;
                            
                            if ($binBarcode) {
                                $sql = "SELECT m.*, p.part_code, p.part_name, w.scale_code, w.scale_name 
                                        FROM material_in m 
                                        LEFT JOIN parts p ON m.part_id = p.id
                                        LEFT JOIN wing_scales w ON m.wing_scale_id = w.id 
                                        WHERE (w.scale_code = ? OR m.wing_scale_code = ?) 
                                        AND LOWER(m.production_status) = 'open' 
                                        ORDER BY m.created_at DESC";
                                $stmt = sqlsrv_query($conn, $sql, array($binBarcode, $binBarcode));
                                if ($stmt) {
                                    $materialData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                                }
                            }
                            
                            $sql = "SELECT table_name, stage_names FROM stages_metadata WHERE part_id = ?";
                            $stmt = sqlsrv_query($conn, $sql, array($partId));
                            
                            if ($stmt && $metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                $stageMetadata = $metaRow;
                                $stageMetadata['stage_names'] = json_decode($metaRow['stage_names'], true);
                            }
                        }
                        
                        // Skip the rest of the processing
                        return;
                    }
                }
            }
            
            // Check if a row exists with this bin-batch value in ANY stage column
            // Build dynamic WHERE clause to check all stage columns
            $whereConditions = array();
            $searchParams = array();
            foreach ($stageNames as $idx => $name) {
                $stgNum = $idx + 1;
                $stgCol = 'stage_' . $stgNum . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name));
                $whereConditions[] = "$stgCol = ?";
                $searchParams[] = $stageValue;
            }
            $whereClause = implode(' OR ', $whereConditions);
            
            $sql = "SELECT id FROM [$tableName] WHERE $whereClause";
            $stmt = sqlsrv_query($conn, $sql, $searchParams);
            
            if ($stmt && $existingRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                // Update existing row - add data to the current stage
                $rowId = $existingRow['id'];
                $sql = "UPDATE [$tableName] SET $stageColumn = ?, $stageQtyColumn = ?, updated_at = GETDATE() WHERE id = ?";
                $stmt = sqlsrv_query($conn, $sql, array($stageValue, $stageQuantity, $rowId));
                
                if ($stmt) {
                    $message = '✓ Stage data updated successfully! Scan next bin for same part/stage.';
                    $messageType = 'success';
                    // Keep part and stage locked - only reset bin data to scan next bin
                    $selectedBin = null;
                    $materialData = null;
                    // $selectedPart, $selectedStageIndex, and $stageMetadata remain locked
                } else {
                    $message = '✗ Error updating stage data: ' . print_r(sqlsrv_errors(), true);
                    $messageType = 'error';
                }
            } else {
                // Insert new row - this is the first stage for this bin-batch
                $sql = "INSERT INTO [$tableName] (part_id, $stageColumn, $stageQtyColumn, created_at, updated_at) 
                        VALUES (?, ?, ?, GETDATE(), GETDATE())";
                $stmt = sqlsrv_query($conn, $sql, array($partId, $stageValue, $stageQuantity));
                
                if ($stmt) {
                    $message = '✓ Stage data saved successfully! Scan next bin for same part/stage.';
                    $messageType = 'success';
                    // Keep part and stage locked - only reset bin data to scan next bin
                    $selectedBin = null;
                    $materialData = null;
                    // $selectedPart, $selectedStageIndex, and $stageMetadata remain locked
                } else {
                    $message = '✗ Error saving stage data: ' . print_r(sqlsrv_errors(), true);
                    $messageType = 'error';
                }
            }
            
            // Reload part and stage info to keep them locked after save
            if ($messageType === 'success' && $partId && $stageIndex !== null) {
                $sql = "SELECT id, part_code, part_name FROM parts WHERE id = ?";
                $stmt = sqlsrv_query($conn, $sql, array($partId));
                
                if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $selectedPart = $row;
                    $selectedStageIndex = $stageIndex;
                    
                    // Get stage metadata
                    $sql = "SELECT table_name, stage_names FROM stages_metadata WHERE part_id = ?";
                    $stmt = sqlsrv_query($conn, $sql, array($partId));
                    
                    if ($stmt && $metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $stageMetadata = $metaRow;
                        $stageMetadata['stage_names'] = json_decode($metaRow['stage_names'], true);
                    }
                }
            }
        }
        
        skip_save:
        // Part and stage remain locked after successful save
    } elseif (isset($_POST['action']) && $_POST['action'] === 'scan_data') {
        $partId = intval($_POST['part_id']);
        $tableName = $_POST['table_name'];
        $stageColumn = $_POST['stage_column'];
        $stageValue = trim($_POST['stage_value']);
        $stageIndex = intval($_POST['stage_index']);
        $stageQuantity = isset($_POST['stage_quantity']) ? intval($_POST['stage_quantity']) : null;
        $wingScaleBarcode = isset($_POST['wing_scale_barcode']) ? trim($_POST['wing_scale_barcode']) : null;
        
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
                $stageQuantity = $stageQuantity;
                $selectedWingScale = $wingScaleBarcode;
                
                // Reload material data if bin exists
                if ($wingScaleBarcode) {
                    $sql = "SELECT m.*, w.scale_code, w.scale_name 
                            FROM material_in m 
                            LEFT JOIN wing_scales w ON m.wing_scale_id = w.id 
                            WHERE (w.scale_code = ? OR m.wing_scale_code = ?) 
                            AND LOWER(m.production_status) = 'open' 
                            ORDER BY m.created_at DESC";
                    $stmt = sqlsrv_query($conn, $sql, array($wingScaleBarcode, $wingScaleBarcode));
                    if ($stmt) {
                        $materialData = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    }
                }
                
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
        <p class="subtitle">Two-phase workflow: Setup → Execution</p>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Phase indicator -->
        <div style="display: flex; justify-content: space-around; margin: 30px 0; gap: 20px;">
            <!-- SECTION 1: Setup Phase (Steps 1-2) -->
            <div style="flex: 1; border: 2px solid <?php echo (!$selectedPart || ($selectedPart && $selectedStageIndex === null && !$selectedBin)) ? '#3b82f6' : '#38ef7d'; ?>; border-radius: 12px; padding: 20px; background: <?php echo (!$selectedPart || ($selectedPart && $selectedStageIndex === null && !$selectedBin)) ? '#eff6ff' : '#f0fdf4'; ?>;">
                <h3 style="margin: 0 0 15px 0; color: <?php echo (!$selectedPart || ($selectedPart && $selectedStageIndex === null && !$selectedBin)) ? '#3b82f6' : '#38ef7d'; ?>; font-size: 16px; text-align: center;">
                    <?php echo (!$selectedPart || ($selectedPart && $selectedStageIndex === null && !$selectedBin)) ? '📋 SECTION 1: SETUP PHASE' : '✓ SECTION 1: COMPLETED'; ?>
                </h3>
                <div style="display: flex; justify-content: space-around; align-items: center;">
                    <div class="step <?php echo (!$selectedPart) ? 'active' : 'completed'; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">Select Part</div>
                    </div>
                    <div style="width: 40px; height: 2px; background: #e0e0e0;"></div>
                    <div class="step <?php echo ($selectedPart && $selectedStageIndex === null && !$selectedBin) ? 'active' : (($selectedStageIndex !== null || $selectedBin) ? 'completed' : ''); ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">Select Stage</div>
                    </div>
                </div>
            </div>
            
            <!-- SECTION 2: Execution Phase (Steps 3-4) -->
            <div style="flex: 1; border: 2px solid <?php echo ($selectedStageIndex !== null || $selectedBin) ? '#3b82f6' : '#cbd5e1'; ?>; border-radius: 12px; padding: 20px; background: <?php echo ($selectedStageIndex !== null || $selectedBin) ? '#eff6ff' : '#f8fafc'; ?>; opacity: <?php echo ($selectedStageIndex === null && !$selectedBin) ? '0.5' : '1'; ?>;">
                <h3 style="margin: 0 0 15px 0; color: <?php echo ($selectedStageIndex !== null || $selectedBin) ? '#3b82f6' : '#94a3b8'; ?>; font-size: 16px; text-align: center;">
                    <?php echo ($selectedStageIndex === null && !$selectedBin) ? '⏳ SECTION 2: EXECUTION PHASE' : ($selectedStageIndex !== null ? '✓ SECTION 2: IN PROGRESS' : '📦 SECTION 2: ACTIVE'); ?>
                </h3>
                <div style="display: flex; justify-content: space-around; align-items: center;">
                    <div class="step <?php echo (!$selectedBin && $selectedStageIndex !== null) ? 'active' : (($selectedStageIndex !== null) ? 'completed' : ''); ?>">
                        <div class="step-number">3</div>
                        <div class="step-label">Verify Bin</div>
                    </div>
                    <div style="width: 40px; height: 2px; background: #e0e0e0;"></div>
                    <div class="step <?php echo ($selectedStageIndex !== null) ? 'active' : ''; ?>">
                        <div class="step-number">4</div>
                        <div class="step-label">Enter Quantity</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reset button - show when part is locked -->
        <?php if ($selectedPart): ?>
            <div style="text-align: center; margin: 20px 0;">
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="reset_workflow">
                    <button type="submit" class="btn" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(239, 68, 68, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(239, 68, 68, 0.3)';">
                        🔄 Start New Selection (Change Part/Stage)
                    </button>
                </form>
                <p style="font-size: 12px; color: #64748b; margin-top: 8px;">
                    Currently locked: <strong style="color: #3b82f6;"><?php echo htmlspecialchars($selectedPart['part_code']); ?></strong>
                    <?php if ($selectedStageIndex !== null && $stageMetadata): ?>
                        → <strong style="color: #3b82f6;">Stage <?php echo ($selectedStageIndex + 1); ?>: <?php echo htmlspecialchars($stageMetadata['stage_names'][$selectedStageIndex]); ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!$selectedPart): ?>
            <!-- Step 1: Select Part -->
            <?php
            // Get all parts
            $sql = "SELECT id, part_code, part_name FROM parts WHERE status = 'active' ORDER BY part_code";
            $partsStmt = sqlsrv_query($conn, $sql);
            ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="select_part">
                
                <div class="form-group">
                    <label for="part_id">Step 1: Select Part</label>
                    <select id="part_id" name="part_id" required autofocus>
                        <option value="">-- Choose a Part --</option>
                        <?php while ($partsStmt && $part = sqlsrv_fetch_array($partsStmt, SQLSRV_FETCH_ASSOC)): ?>
                            <option value="<?php echo $part['id']; ?>">
                                <?php echo htmlspecialchars($part['part_code'] . ' - ' . $part['part_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Next: Select Stage</button>
            </form>

        <?php elseif ($selectedStageIndex === null && !$selectedBin): ?>
            <!-- Step 2: Select Stage -->
            <div class="part-info" style="border-left-color: #3b82f6; background: #eff6ff;">
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

                <button type="submit" class="btn btn-primary">Next: Scan Bin</button>
            </form>

        <?php elseif (!$selectedBin && $selectedStageIndex !== null): ?>
            <!-- Step 3: Scan Bin -->
            <div class="part-info" style="border-left-color: #3b82f6; background: #eff6ff;">
                <h3>Selected Part & Stage</h3>
                <p><strong>Part Code:</strong> <?php echo htmlspecialchars($selectedPart['part_code']); ?></p>
                <p><strong>Part Name:</strong> <?php echo htmlspecialchars($selectedPart['part_name']); ?></p>
                <p><strong>Stage:</strong> Stage <?php echo ($selectedStageIndex + 1); ?> - <?php echo htmlspecialchars($stageMetadata['stage_names'][$selectedStageIndex]); ?></p>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="scan_bin">
                <input type="hidden" name="part_id" value="<?php echo $selectedPart['id']; ?>">
                <input type="hidden" name="stage_index" value="<?php echo $selectedStageIndex; ?>">
                
                <div class="form-group">
                    <label for="bin_barcode">Step 3: Scan Bin Barcode</label>
                    <input type="text" 
                           id="bin_barcode" 
                           name="bin_barcode" 
                           placeholder="Scan or enter bin barcode" 
                           required 
                           autofocus>
                </div>

                <button type="submit" class="btn btn-primary">Verify Bin</button>
            </form>

        <?php elseif ($selectedStageIndex !== null): ?>
            <!-- Step 4: Enter Quantity and Save -->
            <?php if ($materialData): ?>
            <div class="part-info" style="border-left-color: #38ef7d; background: #f0fdf4;">
                <h3>Material Information</h3>
                <p><strong>Part Code:</strong> <?php echo htmlspecialchars($selectedPart['part_code']); ?></p>
                <p><strong>Part Name:</strong> <?php echo htmlspecialchars($selectedPart['part_name']); ?></p>
                <p><strong>Stage:</strong> Stage <?php echo ($selectedStageIndex + 1); ?> - <?php echo htmlspecialchars($stageMetadata['stage_names'][$selectedStageIndex]); ?></p>
                <p><strong>Bin:</strong> <?php echo htmlspecialchars($selectedBin); ?></p>
                <p><strong>Batch Number:</strong> <?php echo htmlspecialchars($materialData['batch_number'] ?? 'N/A'); ?></p>
                <p><strong>Available Quantity:</strong> <?php echo htmlspecialchars($materialData['in_quantity'] ?? 'N/A'); ?></p>
                <p><strong>Status:</strong> <span style="color: #38ef7d; font-weight: bold;">OPEN</span></p>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="save_stage_data">
                <input type="hidden" name="part_id" value="<?php echo $selectedPart['id']; ?>">
                <input type="hidden" name="stage_index" value="<?php echo $selectedStageIndex; ?>">
                <input type="hidden" name="bin_barcode" value="<?php echo htmlspecialchars($selectedBin); ?>">
                
                <div class="form-group">
                    <label for="stage_quantity">Step 4: Enter Production Quantity</label>
                    <input type="number" 
                           id="stage_quantity" 
                           name="stage_quantity" 
                           placeholder="Enter quantity for this stage" 
                           min="0"
                           required
                           autofocus>
                </div>

                <button type="submit" class="btn btn-primary">Save Stage Data</button>
            </form>

        <?php endif; ?>
    </div>
</body>
</html>

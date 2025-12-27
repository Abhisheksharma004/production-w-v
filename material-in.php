<?php
session_start();

// Check if line user is logged in
if (!isset($_SESSION['line_id']) || $_SESSION['user_type'] !== 'line') {
    header("Location: line-login.php");
    exit();
}

// Include database configuration
require_once 'config/database.php';

$line_id = $_SESSION['line_id'];
$line_name = $_SESSION['line_name'];
$line_email = $_SESSION['line_email'];
$activePage = 'material-in';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $conn = getSQLSrvConnection();
    $materialId = intval($_POST['material_id']);
    
    $deleteSql = "DELETE FROM material_in WHERE id = ? AND line_id = ?";
    if (sqlsrv_query($conn, $deleteSql, array($materialId, $line_id))) {
        $_SESSION['message'] = "Material record deleted successfully!";
        $_SESSION['messageType'] = 'success';
    } else {
        $_SESSION['message'] = "Error deleting material record!";
        $_SESSION['messageType'] = 'error';
    }
    
    header("Location: material-in.php");
    exit();
}

// Handle close production action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'close_production') {
    $conn = getSQLSrvConnection();
    $materialId = intval($_POST['material_id']);
    $finalProductionQuantity = intval($_POST['final_production_quantity']);
    $scrapQuantity = intval($_POST['scrap_quantity']);
    $closedAt = date('Y-m-d H:i:s');
    
    // Update the material record with production closure data
    $updateSql = "UPDATE material_in 
                  SET final_production_quantity = ?, 
                      scrap_quantity = ?, 
                      production_status = 'Closed',
                      closed_at = ?
                  WHERE id = ? AND line_id = ?";
    
    $params = array($finalProductionQuantity, $scrapQuantity, $closedAt, $materialId, $line_id);
    
    if (sqlsrv_query($conn, $updateSql, $params)) {
        $_SESSION['message'] = "Production closed successfully! Final Production: $finalProductionQuantity, Scrap: $scrapQuantity";
        $_SESSION['messageType'] = 'success';
    } else {
        $_SESSION['message'] = "Error closing production!";
        $_SESSION['messageType'] = 'error';
    }
    
    header("Location: material-in.php");
    exit();
}

// Handle update action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $conn = getSQLSrvConnection();
    
    $materialId = intval($_POST['material_id']);
    $wingScaleId = intval($_POST['wing_scale_id']);
    $partId = intval($_POST['part_id']);
    $in_quantity = intval($_POST['in_quantity']);
    $in_units = trim($_POST['in_units']);
    $receivedDate = $_POST['received_date'];
    $receivedTime = $_POST['received_time'];
    $receivedDateTime = $receivedDate . ' ' . $receivedTime . ':00';
    $batchNumber = trim($_POST['batch_number']);
    
    // Get wing scale information
    $wingScaleName = '';
    $wingScaleCode = '';
    $sql = "SELECT scale_code, scale_name FROM wing_scales WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, array($wingScaleId));
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $wingScaleCode = $row['scale_code'];
        $wingScaleName = $row['scale_name'];
        sqlsrv_free_stmt($stmt);
    }
    
    // Get part information
    $sql = "SELECT part_code, part_name FROM parts WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, array($partId));
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $partCode = $row['part_code'];
        $partName = $row['part_name'];
        
        // Update material receipt record
        $updateSql = "UPDATE material_in SET wing_scale_id = ?, wing_scale_code = ?, wing_scale_name = ?, part_id = ?, part_code = ?, part_name = ?, in_quantity = ?, in_units = ?, received_date = ?, batch_number = ? WHERE id = ? AND line_id = ?";
        $params = array($wingScaleId, $wingScaleCode, $wingScaleName, $partId, $partCode, $partName, $in_quantity, $in_units, $receivedDateTime, $batchNumber, $materialId, $line_id);
        
        if (sqlsrv_query($conn, $updateSql, $params)) {
            $_SESSION['message'] = "Material record updated successfully! Batch: $batchNumber";
            $_SESSION['messageType'] = 'success';
        } else {
            $_SESSION['message'] = "Error updating material record!";
            $_SESSION['messageType'] = 'error';
        }
        
        sqlsrv_free_stmt($stmt);
    }
    
    header("Location: material-in.php");
    exit();
}

// Handle AJAX request to get material data
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['get_material']) && isset($_GET['id'])) {
    $conn = getSQLSrvConnection();
    $materialId = intval($_GET['id']);
    
    $sql = "SELECT * FROM material_in WHERE id = ? AND line_id = ?";
    $stmt = sqlsrv_query($conn, $sql, array($materialId, $line_id));
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Format received_date for output
        if ($row['received_date'] instanceof DateTime) {
            $dateTime = $row['received_date'];
            $row['received_date_only'] = $dateTime->format('Y-m-d');
            $row['received_time_only'] = $dateTime->format('H:i');
        } else {
            $timestamp = strtotime($row['received_date']);
            $row['received_date_only'] = date('Y-m-d', $timestamp);
            $row['received_time_only'] = date('H:i', $timestamp);
        }
        
        header('Content-Type: application/json');
        echo json_encode($row);
        sqlsrv_free_stmt($stmt);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Material not found']);
    }
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $conn = getSQLSrvConnection();
    
    $wingScaleId = intval($_POST['wing_scale_id']);
    $partId = intval($_POST['part_id']);
    $in_quantity = intval($_POST['in_quantity']);
    $in_units = trim($_POST['in_units']);
    $receivedDate = $_POST['received_date'];
    $receivedTime = $_POST['received_time'];
    $receivedDateTime = $receivedDate . ' ' . $receivedTime . ':00'; // Add seconds for proper datetime format
    $batchNumber = trim($_POST['batch_number']);
    
    // Get wing scale information
    $wingScaleName = '';
    $wingScaleCode = '';
    $sql = "SELECT scale_code, scale_name FROM wing_scales WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, array($wingScaleId));
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $wingScaleCode = $row['scale_code'];
        $wingScaleName = $row['scale_name'];
        sqlsrv_free_stmt($stmt);
    }
    
    // Get part information
    $sql = "SELECT part_code, part_name FROM parts WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, array($partId));
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $partCode = $row['part_code'];
        $partName = $row['part_name'];
        
        // Insert material receipt record
        $insertSql = "INSERT INTO material_in (line_id, wing_scale_id, wing_scale_code, wing_scale_name, part_id, part_code, part_name, in_quantity, in_units, received_date, batch_number, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = array($line_id, $wingScaleId, $wingScaleCode, $wingScaleName, $partId, $partCode, $partName, $in_quantity, $in_units, $receivedDateTime, $batchNumber, date('Y-m-d H:i:s'));
        
        if (sqlsrv_query($conn, $insertSql, $params)) {
            $_SESSION['message'] = "Material received successfully! Batch: $batchNumber";
            $_SESSION['messageType'] = 'success';
        } else {
            $_SESSION['message'] = "Error recording material receipt!";
            $_SESSION['messageType'] = 'error';
        }
        
        sqlsrv_free_stmt($stmt);
    }
    
    header("Location: material-in.php");
    exit();
}

// Fetch wing scales for dropdown
$wingScales = [];
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        $sql = "SELECT id, scale_code, scale_name FROM wing_scales WHERE status = 'Active' ORDER BY scale_code";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $wingScales[] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }
    }
} catch (Exception $e) {
    // Handle error
}

// Fetch parts for dropdown
$parts = [];
try {
    if ($conn !== false) {
        $sql = "SELECT id, part_code, part_name FROM parts WHERE status = 'Active' ORDER BY part_code";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $parts[] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }
    }
} catch (Exception $e) {
    // Handle error
}

// Fetch recent material receipts
$recentMaterials = [];
try {
    if ($conn !== false) {
        $sql = "SELECT TOP 10 * FROM material_in WHERE line_id = ? ORDER BY created_at DESC";
        $stmt = sqlsrv_query($conn, $sql, array($line_id));
        
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $recentMaterials[] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }
    }
} catch (Exception $e) {
    // Handle error
}

// Display session messages
$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material In - <?php echo htmlspecialchars($line_name); ?></title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/line-management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/material-in.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <img src="images/logo.jpg" alt="Viros Logo" class="logo-img">
            <h2><?php echo htmlspecialchars($line_name); ?></h2>
        </div>
        <nav class="nav-menu">
            <a href="line-dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="material-in.php" class="nav-item active">
                <i class="fas fa-plus-circle"></i>
                <span>Material In</span>
            </a>
            <a href="line-production-report.php" class="nav-item">
                <i class="fas fa-list"></i>
                <span>Production Records</span>
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <div class="page-title">
                    <h1><i class="fas fa-plus-circle"></i> Material In</h1>
                    <p>Record incoming materials and parts</p>
                </div>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($line_email); ?></span>
                        <span class="user-role">Production Line Operator</span>
                    </div>
                </div>
                <a href="line-logout.php" class="logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <div class="dashboard-container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <div class="page-title">
                    <h2>Material In Records</h2>
                    <p>View and manage incoming materials</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button class="btn-export-excel" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                    <button class="btn-add-material" onclick="openModal()">
                        <i class="fas fa-plus-circle"></i> Add New Material
                    </button>
                </div>
            </div>

            <!-- Date Filter Section -->
            <div class="filter-section">
                <div class="filter-container">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i>
                        <span>Filter Records</span>
                    </div>
                    <div class="filter-controls">
                        <div class="filter-group">
                            <label for="filter_date_from">From Date</label>
                            <input type="date" id="filter_date_from" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label for="filter_date_to">To Date</label>
                            <input type="date" id="filter_date_to" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label for="filter_part_code">Part Code</label>
                            <input type="text" id="filter_part_code" class="filter-input" placeholder="Enter part code">
                        </div>
                        <div class="filter-group">
                            <label for="filter_batch_number">Batch Number</label>
                            <input type="text" id="filter_batch_number" class="filter-input" placeholder="Enter batch number">
                        </div>
                        <div class="filter-actions">
                            <button class="btn-filter" onclick="applyFilter()">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <button class="btn-reset-filter" onclick="resetFilter()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button class="btn-export-excel" onclick="exportToExcel()" style="margin-left: 10px;">
                                <i class="fas fa-file-excel"></i> Export Filtered Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Form -->
            <div id="materialModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="modal_title"><i class="fas fa-plus-circle"></i> Record Material Receipt</h2>
                        <button class="modal-close" onclick="closeModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" id="materialInForm">
                            <input type="hidden" name="action" id="form_action" value="add">
                            <input type="hidden" name="material_id" id="material_id" value="">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="wing_scale_id">Select Wing Scale *</label>
                            <select id="wing_scale_id" name="wing_scale_id" required>
                                <option value="">-- Select Wing Scale --</option>
                                <?php foreach ($wingScales as $scale): ?>
                                    <option value="<?php echo $scale['id']; ?>">
                                        <?php echo htmlspecialchars($scale['scale_code'] . ' - ' . $scale['scale_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="part_id">Select Part *</label>
                            <select id="part_id" name="part_id" required>
                                <option value="">-- Select Part --</option>
                                <?php foreach ($parts as $part): ?>
                                    <option value="<?php echo $part['id']; ?>">
                                        <?php echo htmlspecialchars($part['part_code'] . ' - ' . $part['part_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="received_date">Received Date *</label>
                            <input type="date" id="received_date" name="received_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="received_time">Received Time *</label>
                            <input type="time" id="received_time" name="received_time" 
                                   value="<?php echo date('H:i'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="in_quantity">In Quantity *</label>
                            <input type="number" id="in_quantity" name="in_quantity" min="1" required placeholder="Enter quantity">
                        </div>

                        <div class="form-group">
                            <label for="in_units">In Units *</label>
                            <select id="in_units" name="in_units" required>
                                <option value="">-- Select Unit --</option>
                                <option value="Pcs">Pieces (Pcs)</option>
                                <option value="Kg">Kilograms (Kg)</option>
                                <option value="Gm">Grams (Gm)</option>
                                <option value="Ltr">Liters (Ltr)</option>
                                <option value="Mtr">Meters (Mtr)</option>
                                <option value="Box">Box</option>
                                <option value="Set">Set</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="batch_number">Batch Number *</label>
                            <input type="text" id="batch_number" name="batch_number" required 
                                   placeholder="Enter batch/lot number">
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 20px; display: flex; gap: 15px;">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Record Material
                        </button>
                        <button type="reset" class="btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <button type="button" class="btn-secondary" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Close Production Modal -->
            <div id="closeProductionModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-check-circle"></i> Close Production</h2>
                        <button class="modal-close" onclick="closeProductionModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" id="closeProductionForm">
                            <input type="hidden" name="action" value="close_production">
                            <input type="hidden" name="material_id" id="close_material_id" value="">
                            
                            <div class="info-section" style="background: #f0f9ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #3b82f6;">
                                <h3 style="margin: 0 0 10px 0; color: #1e40af; font-size: 16px;"><i class="fas fa-info-circle"></i> Batch Information</h3>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div>
                                        <strong>Batch Number:</strong>
                                        <p id="close_batch_number" style="margin: 5px 0; color: #334155;">-</p>
                                    </div>
                                    <div>
                                        <strong>Part Code:</strong>
                                        <p id="close_part_code" style="margin: 5px 0; color: #334155;">-</p>
                                    </div>
                                    <div>
                                        <strong>Material In:</strong>
                                        <p id="close_in_quantity" style="margin: 5px 0; color: #334155;">-</p>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="final_production_quantity">Final Production Quantity *</label>
                                    <input type="number" id="final_production_quantity" name="final_production_quantity" 
                                           min="0" required placeholder="Enter actual production quantity">
                                    <small style="color: #64748b;">Enter the total quantity successfully produced</small>
                                </div>

                                <div class="form-group">
                                    <label for="scrap_quantity">Scrap Quantity *</label>
                                    <input type="number" id="scrap_quantity" name="scrap_quantity" 
                                           min="0" required placeholder="Enter scrap/waste quantity">
                                    <small style="color: #64748b;">Enter the quantity rejected or wasted</small>
                                </div>
                            </div>

                            <div class="form-actions" style="margin-top: 20px; display: flex; gap: 15px;">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-check-circle"></i> Close Production
                                </button>
                                <button type="button" class="btn-secondary" onclick="closeProductionModal()">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Material In Records Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-clipboard-list"></i> Material In Records</h3>
                    <p>Recent material receipts and inventory tracking</p>
                </div>
                
                <?php if (!empty($recentMaterials)): ?>
                <div class="table-container">
                    <table class="recent-materials-table">
                        <thead>
                            <tr>
                                <th><i class="far fa-calendar-alt"></i> Date & Time</th>
                                <th><i class="fas fa-balance-scale"></i> Wing Scale</th>
                                <th><i class="fas fa-barcode"></i> Part Code</th>
                                <th style="display: none;"><i class="fas fa-box"></i> Part Name</th>
                                <th style="width: 120px;"><i class="fas fa-arrow-down"></i> In Quantity</th>
                                <th style="width: 120px;"><i class="fas fa-check-double"></i> Final Production</th>
                                <th style="width: 100px;"><i class="fas fa-trash-alt"></i> Scrap</th>
                                <th><i class="fas fa-tag"></i> Batch Number</th>
                                <th><i class="fas fa-check-circle"></i> Status</th>
                                <th><i class="fas fa-cog"></i> Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMaterials as $material): ?>
                            <tr>
                                <td class="date-cell">
                                    <?php 
                                    $receivedDate = $material['received_date'];
                                    if ($receivedDate instanceof DateTime) {
                                        echo '<span class="date-day">' . $receivedDate->format('d M Y') . '</span>';
                                        echo $receivedDate->format('H:i');
                                    } else {
                                        $timestamp = strtotime($receivedDate);
                                        echo '<span class="date-day">' . date('d M Y', $timestamp) . '</span>';
                                        echo date('H:i', $timestamp);
                                    }
                                    ?>
                                </td>
                                <td><span class="batch-badge"><?php echo htmlspecialchars($material['wing_scale_code'] ?? '-'); ?></span></td>
                                <td><span class="part-code"><?php echo htmlspecialchars($material['part_code']); ?></span></td>
                                <td style="display: none;"><?php echo htmlspecialchars($material['part_name']); ?></td>
                                <td class="quantity-cell">
                                    <?php echo number_format($material['in_quantity']); ?> 
                                    <span class="quantity-unit"><?php echo htmlspecialchars($material['in_units'] ?? 'Pcs'); ?></span>
                                </td>
                                <td class="quantity-cell">
                                    <?php if (!empty($material['final_production_quantity'])): ?>
                                        <?php echo number_format($material['final_production_quantity']); ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="quantity-cell">
                                    <?php if (!empty($material['scrap_quantity']) || $material['scrap_quantity'] === 0): ?>
                                        <span style="color: #ef4444; font-weight: 600;"><?php echo number_format($material['scrap_quantity']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="batch-badge"><?php echo htmlspecialchars($material['batch_number']); ?></span></td>
                                <td>
                                    <?php 
                                    $status = $material['production_status'] ?? 'Open';
                                    if ($status === 'Closed'): 
                                    ?>
                                        <span class="badge badge-closed"><i class="fas fa-check-double"></i> Closed</span>
                                    <?php else: ?>
                                        <span class="badge badge-open"><i class="fas fa-clock"></i> Open</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($status !== 'Closed'): ?>
                                        <button class="btn-action btn-update" onclick="updateMaterial(<?php echo $material['id']; ?>)" title="Update">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                        <button class="btn-action btn-close-production" onclick="closeProduction(<?php echo $material['id']; ?>, '<?php echo htmlspecialchars($material['batch_number']); ?>')" title="Close Production">
                                            <i class="fas fa-check-circle"></i> Close
                                        </button>
                                        <?php else: ?>
                                        <span class="text-muted" style="font-size: 12px; color: #94a3b8;">
                                            <i class="fas fa-lock"></i> Production Closed
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Materials Recorded Yet</h3>
                    <p>Click "Add New Material" to record your first material receipt</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Error Display Panel -->
    <div id="errorPanel" class="error-panel" style="display: none;">
        <div class="error-content">
            <div class="error-header">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Error Details</span>
                <button class="error-close" onclick="closeErrorPanel()">&times;</button>
            </div>
            <div id="errorMessage" class="error-message"></div>
            <div class="error-timestamp" id="errorTimestamp"></div>
        </div>
    </div>

    <script>
        // Error Display Functions
        function showError(message, error) {
            const errorPanel = document.getElementById('errorPanel');
            const errorMessage = document.getElementById('errorMessage');
            const errorTimestamp = document.getElementById('errorTimestamp');
            
            let fullMessage = message;
            if (error) {
                fullMessage += '\n\nError Details:\n' + error.toString();
                if (error.stack) {
                    fullMessage += '\n\nStack Trace:\n' + error.stack;
                }
            }
            
            errorMessage.textContent = fullMessage;
            errorTimestamp.textContent = 'Error occurred at: ' + new Date().toLocaleString('en-GB');
            errorPanel.style.display = 'block';
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                closeErrorPanel();
            }, 10000);
        }

        function closeErrorPanel() {
            const errorPanel = document.getElementById('errorPanel');
            errorPanel.style.display = 'none';
        }

        // Global error handler
        window.addEventListener('error', function(event) {
            showError('JavaScript Error: ' + event.message, event.error);
        });

        window.addEventListener('unhandledrejection', function(event) {
            showError('Promise Rejection: ' + event.reason, event.reason);
        });

        // Function to set current date and time
        function setCurrentDateTime() {
            const now = new Date();
            
            // Format date as YYYY-MM-DD
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;
            
            // Format time as HH:MM
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const timeStr = `${hours}:${minutes}`;
            
            // Set the values
            document.getElementById('received_date').value = dateStr;
            document.getElementById('received_time').value = timeStr;
        }

        // Modal functions
        function openModal() {
            document.getElementById('materialModal').classList.add('show');
            // Only set current date/time if in add mode
            if (document.getElementById('form_action').value === 'add') {
                setCurrentDateTime();
            }
        }

        function closeModal() {
            document.getElementById('materialModal').classList.remove('show');
            // Reset form to add mode
            document.getElementById('materialInForm').reset();
            document.getElementById('form_action').value = 'add';
            document.getElementById('material_id').value = '';
            document.getElementById('modal_title').innerHTML = '<i class="fas fa-plus-circle"></i> Record Material Receipt';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('materialModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Auto-hide alert messages
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.display = 'none';
            }
        }, 5000);

        // Generate batch number suggestion
        document.getElementById('part_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const partText = selectedOption.text;
                const partCode = partText.split(' - ')[0];
                const date = new Date();
                const dateStr = date.getFullYear() + 
                               String(date.getMonth() + 1).padStart(2, '0') + 
                               String(date.getDate()).padStart(2, '0');
                const timeStr = String(date.getHours()).padStart(2, '0') + 
                               String(date.getMinutes()).padStart(2, '0');
                
                const batchSuggestion = partCode + '-' + dateStr + '-' + timeStr;
                document.getElementById('batch_number').placeholder = 'e.g., ' + batchSuggestion;
            }
        });

        // View material details
        function viewMaterial(id) {
            alert('View material details for ID: ' + id + '\n\nThis will open a detailed view of the material record.');
        }

        // Update material
        function updateMaterial(id) {
            try {
                // Fetch material data via AJAX
                fetch('material-in.php?get_material=1&id=' + id)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.error) {
                            showError('Failed to fetch material data for update', new Error(data.error));
                            return;
                        }
                        
                        // Populate form fields
                        document.getElementById('form_action').value = 'update';
                        document.getElementById('material_id').value = data.id;
                        document.getElementById('wing_scale_id').value = data.wing_scale_id;
                        document.getElementById('part_id').value = data.part_id;
                        document.getElementById('in_quantity').value = data.in_quantity;
                        document.getElementById('in_units').value = data.in_units;
                        document.getElementById('received_date').value = data.received_date_only;
                        document.getElementById('received_time').value = data.received_time_only;
                        document.getElementById('batch_number').value = data.batch_number;
                        
                        // Update modal title
                        document.getElementById('modal_title').innerHTML = '<i class="fas fa-edit"></i> Update Material Receipt';
                        
                        // Open modal
                        openModal();
                    })
                    .catch(error => {
                        showError('Error fetching material data for update', error);
                    });
            } catch (error) {
                showError('Unexpected error in updateMaterial function', error);
            }
        }

        // Close Production
        function closeProduction(id, batchNumber) {
            try {
                // Fetch material data via AJAX
                fetch('material-in.php?get_material=1&id=' + id)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.error) {
                            showError('Failed to fetch material data for closing production', new Error(data.error));
                            return;
                        }
                        
                        // Populate modal with material information
                        document.getElementById('close_material_id').value = data.id;
                        document.getElementById('close_batch_number').textContent = data.batch_number;
                        document.getElementById('close_part_code').textContent = data.part_code;
                        document.getElementById('close_in_quantity').textContent = data.in_quantity + ' ' + data.in_units;
                        
                        // Reset input fields
                        document.getElementById('final_production_quantity').value = '';
                        document.getElementById('scrap_quantity').value = '';
                        
                        // Open modal
                        document.getElementById('closeProductionModal').style.display = 'block';
                    })
                    .catch(error => {
                        showError('Error fetching material data for closing production', error);
                    });
            } catch (error) {
                showError('Unexpected error in closeProduction function', error);
            }
        }

        // Close Production Modal
        function closeProductionModal() {
            document.getElementById('closeProductionModal').style.display = 'none';
        }

        // Delete material
        function deleteMaterial(id, batchNumber) {
            if (confirm('Are you sure you want to delete material record?\n\nBatch Number: ' + batchNumber + '\n\nThis action cannot be undone.')) {
                // Submit delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'material-in.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'material_id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const materialModal = document.getElementById('materialModal');
            const closeProdModal = document.getElementById('closeProductionModal');
            
            if (event.target == materialModal) {
                closeModal();
            }
            if (event.target == closeProdModal) {
                closeProductionModal();
            }
        }

        // Export to Excel function
        function exportToExcel() {
            // Get table data
            const table = document.querySelector('.recent-materials-table');
            if (!table) {
                alert('No data available to export');
                return;
            }

            // Create workbook data
            let excelData = [];
            
            // Add headers
            const headers = ['Date & Time', 'Wing Scale', 'Part Code', 'Part Name', 'In Quantity', 'Final Production', 'Scrap', 'Batch Number', 'Status'];
            excelData.push(headers);
            
            // Get all table rows (only export visible/filtered rows)
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                // Skip rows that are hidden by filter
                if (row.classList.contains('hidden-row')) {
                    return;
                }
                
                const cols = row.querySelectorAll('td');
                if (cols.length > 0) {
                    const rowData = [
                        cols[0].textContent.trim().replace(/\s+/g, ' '),  // Date & Time
                        cols[1].textContent.trim(),  // Wing Scale
                        cols[2].textContent.trim(),  // Part Code
                        cols[3].textContent.trim(),  // Part Name (hidden but still in DOM)
                        cols[4].textContent.trim().replace(/\s+/g, ' '),  // In Quantity
                        cols[5].textContent.trim().replace(/\s+/g, ' '),  // Final Production
                        cols[6].textContent.trim(),  // Scrap
                        cols[7].textContent.trim(),  // Batch Number
                        cols[8].textContent.trim().replace(/\s+/g, ' ')   // Status
                    ];
                    excelData.push(rowData);
                }
            });
            
            // Convert to CSV format
            let csvContent = '';
            excelData.forEach(row => {
                const csvRow = row.map(cell => {
                    // Escape quotes and wrap in quotes if contains comma
                    const cellStr = String(cell).replace(/"/g, '""');
                    return `"${cellStr}"`;
                }).join(',');
                csvContent += csvRow + '\r\n';
            });
            
            // Add BOM for UTF-8
            const BOM = '\uFEFF';
            csvContent = BOM + csvContent;
            
            // Create blob and download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            // Generate filename with timestamp
            const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
            const filename = `Material_In_Report_${timestamp}.csv`;
            
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Filter function
        function applyFilter() {
            const dateFrom = document.getElementById('filter_date_from').value;
            const dateTo = document.getElementById('filter_date_to').value;
            const partCode = document.getElementById('filter_part_code').value.trim().toLowerCase();
            const batchNumber = document.getElementById('filter_batch_number').value.trim().toLowerCase();
            
            if (!dateFrom && !dateTo && !partCode && !batchNumber) {
                alert('Please select at least one filter option');
                return;
            }
            
            const table = document.querySelector('.recent-materials-table');
            if (!table) return;
            
            const rows = table.querySelectorAll('tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length === 0) return;
                
                let showRow = true;
                
                // Filter by date
                if (dateFrom || dateTo) {
                    const dateCell = cells[0];
                    const dateText = dateCell.textContent.trim();
                    const dateParts = dateText.split('\n')[0].trim();
                    const rowDate = parseDateFromCell(dateParts);
                    
                    if (!rowDate) {
                        showRow = false;
                    } else {
                        if (dateFrom) {
                            const fromDate = new Date(dateFrom);
                            fromDate.setHours(0, 0, 0, 0);
                            if (rowDate < fromDate) {
                                showRow = false;
                            }
                        }
                        
                        if (dateTo && showRow) {
                            const toDate = new Date(dateTo);
                            toDate.setHours(23, 59, 59, 999);
                            if (rowDate > toDate) {
                                showRow = false;
                            }
                        }
                    }
                }
                
                // Filter by part code
                if (partCode && showRow) {
                    const partCodeCell = cells[2];
                    const rowPartCode = partCodeCell.textContent.trim().toLowerCase();
                    if (!rowPartCode.includes(partCode)) {
                        showRow = false;
                    }
                }
                
                // Filter by batch number
                if (batchNumber && showRow) {
                    const batchCell = cells[7]; // Batch number column
                    const rowBatchNumber = batchCell.textContent.trim().toLowerCase();
                    if (!rowBatchNumber.includes(batchNumber)) {
                        showRow = false;
                    }
                }
                
                if (showRow) {
                    row.classList.remove('hidden-row');
                    visibleCount++;
                } else {
                    row.classList.add('hidden-row');
                }
            });
            
            // Show message if no records found
            if (visibleCount === 0) {
                alert('No records found for the selected filters.');
            }
        }

        // Helper function to parse date from cell text
        function parseDateFromCell(dateText) {
            try {
                // Expected format: "DD MMM YYYY" (e.g., "24 Dec 2025")
                const months = {
                    'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5,
                    'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11
                };
                
                const parts = dateText.trim().split(' ');
                if (parts.length >= 3) {
                    const day = parseInt(parts[0]);
                    const month = months[parts[1]];
                    const year = parseInt(parts[2]);
                    
                    if (!isNaN(day) && month !== undefined && !isNaN(year)) {
                        return new Date(year, month, day);
                    }
                }
                return null;
            } catch (e) {
                return null;
            }
        }

        // Reset filter function
        function resetFilter() {
            document.getElementById('filter_date_from').value = '';
            document.getElementById('filter_date_to').value = '';
            document.getElementById('filter_part_code').value = '';
            document.getElementById('filter_batch_number').value = '';
            
            const table = document.querySelector('.recent-materials-table');
            if (!table) return;
            
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.classList.remove('hidden-row');
            });
        }
    </script>
</body>
</html>

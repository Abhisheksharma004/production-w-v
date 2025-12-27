<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database configuration
require_once 'config/database.php';

$current_user = $_SESSION['username'];
$activePage = 'stages-management';
$pageTitle = 'Stages Management';

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $conn = getSQLSrvConnection();
    
    if ($_POST['action'] === 'add' && isset($_POST['part_id']) && isset($_POST['stage_names'])) {
        $partId = intval($_POST['part_id']);
        $stageNames = $_POST['stage_names'];
        
        // Get part code
        $sql = "SELECT part_code, part_name FROM parts WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($partId));
        
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $partCode = $row['part_code'];
            $partName = $row['part_name'];
            
            // Sanitize part code for table name (remove special characters, spaces)
            $tableName = 'part_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $partCode);
            
            // Check if table already exists
            $checkSql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?";
            $checkStmt = sqlsrv_query($conn, $checkSql, array($tableName));
            $checkRow = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
            
            if ($checkRow['cnt'] > 0) {
                $_SESSION['message'] = "Table for part code '$partCode' already exists!";
                $_SESSION['messageType'] = 'error';
            } else {
                // Build column definitions for each stage
                $columnDefinitions = array();
                foreach ($stageNames as $index => $stageName) {
                    $columnName = 'stage_' . ($index + 1) . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($stageName));
                    // Limit column name length to 128 characters
                    $columnName = substr($columnName, 0, 128);
                    $columnDefinitions[] = "[$columnName] NVARCHAR(255) NULL";
                }
                
                // Create table with stages as columns
                $createTableSql = "CREATE TABLE [$tableName] (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    part_id INT NOT NULL,
                    " . implode(",\n                    ", $columnDefinitions) . ",
                    created_at DATETIME2 DEFAULT GETDATE(),
                    updated_at DATETIME2 DEFAULT GETDATE(),
                    FOREIGN KEY (part_id) REFERENCES parts(id)
                )";
                
                if (sqlsrv_query($conn, $createTableSql)) {
                    // Store stage metadata
                    $insertMetadataSql = "INSERT INTO stages_metadata (part_id, part_code, table_name, stage_names, created_at) 
                                          VALUES (?, ?, ?, ?, GETDATE())";
                    $stageNamesJson = json_encode($stageNames);
                    $metadataStmt = sqlsrv_query($conn, $insertMetadataSql, array($partId, $partCode, $tableName, $stageNamesJson));
                    
                    $_SESSION['message'] = "Table '$tableName' created successfully with " . count($stageNames) . " stage columns!";
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = "Error creating table: " . print_r(sqlsrv_errors(), true);
                    $_SESSION['messageType'] = 'error';
                }
            }
            sqlsrv_free_stmt($stmt);
        }
    } elseif ($_POST['action'] === 'update' && isset($_POST['metadata_id']) && isset($_POST['stage_names'])) {
        $metadataId = intval($_POST['metadata_id']);
        $newStageNames = $_POST['stage_names'];
        
        // Get current metadata
        $sql = "SELECT * FROM stages_metadata WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($metadataId));
        
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $oldTableName = $row['table_name'];
            $partId = $row['part_id'];
            $partCode = $row['part_code'];
            $oldStageNames = json_decode($row['stage_names'], true);
            
            // Create new table name (same as old)
            $newTableName = $oldTableName;
            
            // Build new column definitions
            $columnDefinitions = array();
            foreach ($newStageNames as $index => $stageName) {
                $columnName = 'stage_' . ($index + 1) . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($stageName));
                $columnName = substr($columnName, 0, 128);
                $columnDefinitions[] = "[$columnName] NVARCHAR(255) NULL";
            }
            
            // Drop old table and create new one
            $dropSql = "DROP TABLE IF EXISTS [$oldTableName]";
            if (sqlsrv_query($conn, $dropSql)) {
                // Create new table with updated stages
                $createTableSql = "CREATE TABLE [$newTableName] (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    part_id INT NOT NULL,
                    " . implode(",\n                    ", $columnDefinitions) . ",
                    created_at DATETIME2 DEFAULT GETDATE(),
                    updated_at DATETIME2 DEFAULT GETDATE(),
                    FOREIGN KEY (part_id) REFERENCES parts(id)
                )";
                
                if (sqlsrv_query($conn, $createTableSql)) {
                    // Update metadata
                    $updateMetadataSql = "UPDATE stages_metadata SET stage_names = ? WHERE id = ?";
                    $stageNamesJson = json_encode($newStageNames);
                    sqlsrv_query($conn, $updateMetadataSql, array($stageNamesJson, $metadataId));
                    
                    $_SESSION['message'] = "Stages updated successfully! (Note: Previous data was cleared)";
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = "Error creating updated table!";
                    $_SESSION['messageType'] = 'error';
                }
            } else {
                $_SESSION['message'] = "Error dropping old table!";
                $_SESSION['messageType'] = 'error';
            }
            sqlsrv_free_stmt($stmt);
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['metadata_id'])) {
        $metadataId = intval($_POST['metadata_id']);
        
        // Get table name
        $sql = "SELECT table_name FROM stages_metadata WHERE id = ?";
        $stmt = sqlsrv_query($conn, $sql, array($metadataId));
        
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $tableName = $row['table_name'];
            
            // Drop the table
            $dropSql = "DROP TABLE IF EXISTS [$tableName]";
            if (sqlsrv_query($conn, $dropSql)) {
                // Delete metadata
                $deleteSql = "DELETE FROM stages_metadata WHERE id = ?";
                sqlsrv_query($conn, $deleteSql, array($metadataId));
                
                $_SESSION['message'] = "Table '$tableName' deleted successfully!";
                $_SESSION['messageType'] = 'success';
            } else {
                $_SESSION['message'] = "Error deleting table!";
                $_SESSION['messageType'] = 'error';
            }
            sqlsrv_free_stmt($stmt);
        }
    }
    
    header("Location: stages-management.php");
    exit();
}

// Display session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// Fetch all parts for dropdown
$parts = [];
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        $sql = "SELECT * FROM parts WHERE status = 'Active' ORDER BY part_code";
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

// Fetch all stages metadata
$stagesMetadata = [];
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        $sql = "SELECT sm.*, p.part_code, p.part_name 
                FROM stages_metadata sm 
                JOIN parts p ON sm.part_id = p.id 
                ORDER BY sm.created_at DESC";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $stagesMetadata[] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }
    }
} catch (Exception $e) {
    // Handle error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stages Management - Production Management System</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/line-management.css">
    <link rel="stylesheet" href="css/stages-management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <!-- Dashboard Content -->
        <div class="dashboard-container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="page-header">
                <div class="page-title">
                    <h2>Production Stages</h2>
                    <p>Manage your production stages and workflow</p>
                </div>
                <button class="btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Stage
                </button>
            </div>

            <!-- Stages Table -->
            <div class="table-container">
                <?php if (empty($stagesMetadata)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <h3>No Stages Yet</h3>
                        <p>Get started by adding your first production stage</p>
                        <button class="btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Stage
                        </button>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Part Code</th>
                                <th>Part Name</th>
                                <th>Table Name</th>
                                <th>Stages</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($stagesMetadata as $metadata): ?>
                                <?php 
                                    $stageNamesArray = json_decode($metadata['stage_names'], true);
                                    $createdAt = $metadata['created_at'] instanceof DateTime ? 
                                                 $metadata['created_at']->format('Y-m-d H:i') : 
                                                 date('Y-m-d H:i', strtotime($metadata['created_at']));
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td class="line-code"><?php echo htmlspecialchars($metadata['part_code']); ?></td>
                                    <td class="line-name"><?php echo htmlspecialchars($metadata['part_name']); ?></td>
                                    <td class="line-code"><?php echo htmlspecialchars($metadata['table_name']); ?></td>
                                    <td>
                                        <div class="stages-list">
                                            <?php foreach ($stageNamesArray as $index => $stageName): ?>
                                                <span class="stage-badge"><?php echo ($index + 1) . '. ' . htmlspecialchars($stageName); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $createdAt; ?></td>
                                    <td class="action-buttons">
                                        <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($metadata); ?>)' title="Edit" style="margin-right: 5px;">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete" onclick="deleteStage(<?php echo $metadata['id']; ?>, '<?php echo htmlspecialchars($metadata['table_name']); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal" id="stageModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3 id="modalTitle">Add Stages for Part</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="stageForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="metadata_id" id="metadataId">
                
                <div class="form-group" id="partSelectGroup">
                    <label for="partId">Select Part *</label>
                    <select id="partId" name="part_id" required>
                        <option value="">-- Select Part --</option>
                        <?php foreach ($parts as $part): ?>
                            <option value="<?php echo $part['id']; ?>">
                                <?php echo htmlspecialchars($part['part_code']) . ' - ' . htmlspecialchars($part['part_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="partInfoDisplay" style="display: none; padding: 15px; background: #f0f9ff; border-radius: 8px; margin-bottom: 15px; color: #0369a1;">
                    <strong>Part:</strong> <span id="partInfoText"></span>
                </div>
                
                <div class="stages-section">
                    <div class="section-header">
                        <label>Stages</label>
                    </div>
                    <div id="stagesContainer">
                        <!-- Stage rows will be added here dynamically -->
                    </div>
                    <div style="margin-top: 10px;">
                        <button type="button" class="btn-primary btn-sm" onclick="addStageRow()">
                            <i class="fas fa-plus"></i> Add Stage
                        </button>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="submitBtn">Save Stages</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete table <strong id="deleteTableName"></strong>?</p>
                <p class="warning-text">This action will delete the table and all its data. This cannot be undone.</p>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="metadata_id" id="deleteMetadataId">
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    
    <script>
        let stageCounter = 0;

        // Modal functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Stages for Part';
            document.getElementById('formAction').value = 'add';
            document.getElementById('submitBtn').textContent = 'Save Stages';
            document.getElementById('metadataId').value = '';
            document.getElementById('stageForm').reset();
            document.getElementById('stagesContainer').innerHTML = '';
            document.getElementById('partSelectGroup').style.display = 'block';
            document.getElementById('partInfoDisplay').style.display = 'none';
            document.getElementById('partId').required = true;
            stageCounter = 0;
            addStageRow();
            document.getElementById('stageModal').style.display = 'flex';
        }
        
        function openEditModal(metadata) {
            document.getElementById('modalTitle').textContent = 'Update Stages for ' + metadata.part_code;
            document.getElementById('formAction').value = 'update';
            document.getElementById('submitBtn').textContent = 'Update Stages';
            document.getElementById('metadataId').value = metadata.id;
            document.getElementById('stagesContainer').innerHTML = '';
            document.getElementById('partSelectGroup').style.display = 'none';
            document.getElementById('partInfoDisplay').style.display = 'block';
            document.getElementById('partInfoText').textContent = metadata.part_code + ' - ' + metadata.part_name;
            document.getElementById('partId').required = false;
            
            // Parse and populate existing stages
            const stageNames = JSON.parse(metadata.stage_names);
            stageCounter = 0;
            stageNames.forEach(stageName => {
                addStageRow();
                const inputs = document.querySelectorAll('input[name="stage_names[]"]');
                inputs[inputs.length - 1].value = stageName;
            });
            
            document.getElementById('stageModal').style.display = 'flex';
        }
        
        function addStageRow() {
            stageCounter++;
            const container = document.getElementById('stagesContainer');
            const stageRow = document.createElement('div');
            stageRow.className = 'stage-row';
            stageRow.id = 'stageRow' + stageCounter;
            stageRow.innerHTML = `
                <div class="stage-row-number">${stageCounter}</div>
                <div class="form-group" style="flex: 3;">
                    <input type="text" name="stage_names[]" placeholder="Stage Name" required>
                </div>
                <button type="button" class="btn-remove" onclick="removeStageRow(${stageCounter})" title="Remove">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(stageRow);
        }
        
        function removeStageRow(id) {
            const row = document.getElementById('stageRow' + id);
            if (row) {
                row.remove();
                renumberStageRows();
            }
        }
        
        function renumberStageRows() {
            const rows = document.querySelectorAll('.stage-row');
            rows.forEach((row, index) => {
                const numberElement = row.querySelector('.stage-row-number');
                if (numberElement) {
                    numberElement.textContent = index + 1;
                }
            });
        }
        
        function closeModal() {
            document.getElementById('stageModal').style.display = 'none';
        }

        function deleteStage(metadataId, tableName) {
            document.getElementById('deleteMetadataId').value = metadataId;
            document.getElementById('deleteTableName').textContent = tableName;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modals on outside click
        window.onclick = function(event) {
            const stageModal = document.getElementById('stageModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target == stageModal) {
                closeModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>

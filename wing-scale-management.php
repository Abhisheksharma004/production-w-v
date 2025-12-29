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
$activePage = 'wing-scale-management';
$pageTitle = 'Bin Management';

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getSQLSrvConnection();
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            // Add new bin
            $scaleName = trim($_POST['scale_name']);
            $scaleCode = trim($_POST['scale_code']);
            $status = $_POST['status'];
            
            $sql = "INSERT INTO wing_scales (scale_name, scale_code, status) VALUES (?, ?, ?)";
            $params = [$scaleName, $scaleCode, $status];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $_SESSION['message'] = "Bin added successfully!";
                $_SESSION['messageType'] = "success";
                sqlsrv_free_stmt($stmt);
            } else {
                $_SESSION['message'] = "Error adding bin.";
                $_SESSION['messageType'] = "error";
            }
            header("Location: wing-scale-management.php");
            exit();
        } elseif ($_POST['action'] == 'edit') {
            // Update existing bin
            $scaleId = (int)$_POST['scale_id'];
            $scaleName = trim($_POST['scale_name']);
            $scaleCode = trim($_POST['scale_code']);
            $status = $_POST['status'];
            
            $sql = "UPDATE wing_scales SET scale_name = ?, scale_code = ?, status = ?, updated_at = GETDATE() WHERE id = ?";
            $params = [$scaleName, $scaleCode, $status, $scaleId];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $_SESSION['message'] = "Bin updated successfully!";
                $_SESSION['messageType'] = "success";
                sqlsrv_free_stmt($stmt);
            } else {
                $_SESSION['message'] = "Error updating bin.";
                $_SESSION['messageType'] = "error";
            }
            header("Location: wing-scale-management.php");
            exit();
        } elseif ($_POST['action'] == 'delete') {
            // Delete bin
            $scaleId = (int)$_POST['scale_id'];
            
            $sql = "DELETE FROM wing_scales WHERE id = ?";
            $params = [$scaleId];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $_SESSION['message'] = "Bin deleted successfully!";
                $_SESSION['messageType'] = "success";
                sqlsrv_free_stmt($stmt);
            } else {
                $_SESSION['message'] = "Error deleting bin.";
                $_SESSION['messageType'] = "error";
            }
            header("Location: wing-scale-management.php");
            exit();
        }
    }
}

// Get message from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// Fetch all bins
$scales = [];
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        $sql = "SELECT * FROM wing_scales ORDER BY scale_code";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $scales[] = $row;
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
    <title>Bin Management - Production Management System</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/line-management.css">
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
                    <h2>Bin Management</h2>
                    <p>Manage your weighing scales and equipment</p>
                </div>
                <button class="btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Bin
                </button>
            </div>

            <!-- Bins Table -->
            <div class="table-container">
                <?php if (empty($scales)): ?>
                    <div class="empty-state">
                        <i class="fas fa-cart-shopping"></i>
                        <h3>No Bins Yet</h3>
                        <p>Get started by adding your first bin</p>
                        <button class="btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Bin
                        </button>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Scale Code</th>
                                <th>Scale Name</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($scales as $scale): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td class="line-code"><?php echo htmlspecialchars($scale['scale_code']); ?></td>
                                    <td class="line-name"><?php echo htmlspecialchars($scale['scale_name']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($scale['status']); ?>">
                                            <?php echo htmlspecialchars($scale['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo isset($scale['created_at']) ? $scale['created_at']->format('Y-m-d H:i') : 'N/A'; ?></td>
                                    <td class="action-buttons">
                                        <button class="btn-edit" onclick='editScale(<?php echo json_encode($scale); ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete" onclick="deleteScale(<?php echo $scale['id']; ?>, '<?php echo htmlspecialchars($scale['scale_name']); ?>')" title="Delete">
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
    <div class="modal" id="scaleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Bin</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="scaleForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="scale_id" id="scaleId">
                
                <div class="form-group">
                    <label for="scaleCode">Scale Code *</label>
                    <input type="text" id="scaleCode" name="scale_code" required placeholder="WS001">
                </div>
                
                <div class="form-group">
                    <label for="scaleName">Scale Name *</label>
                    <input type="text" id="scaleName" name="scale_name" required placeholder="Bin A1">
                </div>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Calibration">Calibration</option>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="submitBtn">Add Bin</button>
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
                <p>Are you sure you want to delete <strong id="deleteScaleName"></strong>?</p>
                <p class="warning-text">This action cannot be undone.</p>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="scale_id" id="deleteScaleId">
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    
    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Bin';
            document.getElementById('formAction').value = 'add';
            document.getElementById('submitBtn').textContent = 'Add Bin';
            document.getElementById('scaleForm').reset();
            document.getElementById('scaleModal').style.display = 'flex';
        }

        function editScale(scale) {
            document.getElementById('modalTitle').textContent = 'Edit Bin';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('submitBtn').textContent = 'Update Bin';
            document.getElementById('scaleId').value = scale.id;
            document.getElementById('scaleCode').value = scale.scale_code;
            document.getElementById('scaleName').value = scale.scale_name;
            document.getElementById('status').value = scale.status;
            document.getElementById('scaleModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('scaleModal').style.display = 'none';
        }

        function deleteScale(id, name) {
            document.getElementById('deleteScaleId').value = id;
            document.getElementById('deleteScaleName').textContent = name;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modals on outside click
        window.onclick = function(event) {
            const scaleModal = document.getElementById('scaleModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target == scaleModal) {
                closeModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            
            tableRows.forEach(row => {
                const scaleCode = row.cells[1].textContent.toLowerCase();
                const scaleName = row.cells[2].textContent.toLowerCase();
                
                if (scaleCode.includes(searchTerm) || scaleName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Auto-hide alert messages
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.display = 'none';
            }
        }, 5000);
    </script>

    <style>
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>

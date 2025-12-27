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
$activePage = 'part-management';
$pageTitle = 'Part Management';

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getSQLSrvConnection();
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            // Add new part
            $partName = trim($_POST['part_name']);
            $partCode = trim($_POST['part_code']);
            $status = $_POST['status'];
            
            $sql = "INSERT INTO parts (part_name, part_code, status) VALUES (?, ?, ?)";
            $params = [$partName, $partCode, $status];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $_SESSION['message'] = "Part added successfully!";
                $_SESSION['messageType'] = "success";
                sqlsrv_free_stmt($stmt);
            } else {
                $_SESSION['message'] = "Error adding part.";
                $_SESSION['messageType'] = "error";
            }
            header("Location: part-management.php");
            exit();
        } elseif ($_POST['action'] == 'edit') {
            // Update existing part
            $partId = (int)$_POST['part_id'];
            $partName = trim($_POST['part_name']);
            $partCode = trim($_POST['part_code']);
            $status = $_POST['status'];
            
            $sql = "UPDATE parts SET part_name = ?, part_code = ?, status = ?, updated_at = GETDATE() WHERE id = ?";
            $params = [$partName, $partCode, $status, $partId];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $_SESSION['message'] = "Part updated successfully!";
                $_SESSION['messageType'] = "success";
                sqlsrv_free_stmt($stmt);
            } else {
                $_SESSION['message'] = "Error updating part.";
                $_SESSION['messageType'] = "error";
            }
            header("Location: part-management.php");
            exit();
        } elseif ($_POST['action'] == 'delete') {
            // Delete part
            $partId = (int)$_POST['part_id'];
            
            $sql = "DELETE FROM parts WHERE id = ?";
            $params = [$partId];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $_SESSION['message'] = "Part deleted successfully!";
                $_SESSION['messageType'] = "success";
                sqlsrv_free_stmt($stmt);
            } else {
                $_SESSION['message'] = "Error deleting part.";
                $_SESSION['messageType'] = "error";
            }
            header("Location: part-management.php");
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

// Fetch all parts
$parts = [];
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        $sql = "SELECT * FROM parts ORDER BY part_code";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Part Management - Production Management System</title>
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
                    <h2>Parts Management</h2>
                    <p>Manage your parts and components</p>
                </div>
                <button class="btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Part
                </button>
            </div>

            <!-- Parts Table -->
            <div class="table-container">
                <?php if (empty($parts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-cog"></i>
                        <h3>No Parts Yet</h3>
                        <p>Get started by adding your first part</p>
                        <button class="btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Part
                        </button>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Part Number</th>
                                <th>Part Name</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($parts as $part): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td class="line-code"><?php echo htmlspecialchars($part['part_code']); ?></td>
                                    <td class="line-name"><?php echo htmlspecialchars($part['part_name']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($part['status']); ?>">
                                            <?php echo htmlspecialchars($part['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo isset($part['created_at']) ? $part['created_at']->format('Y-m-d H:i') : 'N/A'; ?></td>
                                    <td class="action-buttons">
                                        <button class="btn-edit" onclick='editPart(<?php echo json_encode($part); ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete" onclick="deletePart(<?php echo $part['id']; ?>, '<?php echo htmlspecialchars($part['part_name']); ?>')" title="Delete">
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
    <div class="modal" id="partModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Part</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="partForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="part_id" id="partId">
                
                <div class="form-group">
                    <label for="partCode">Part Number *</label>
                    <input type="text" id="partCode" name="part_code" required>
                </div>
                
                <div class="form-group">
                    <label for="partName">Part Name *</label>
                    <input type="text" id="partName" name="part_name" required>
                </div>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Discontinued">Discontinued</option>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="submitBtn">Add Part</button>
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
                <p>Are you sure you want to delete <strong id="deletePartName"></strong>?</p>
                <p class="warning-text">This action cannot be undone.</p>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="part_id" id="deletePartId">
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
            document.getElementById('modalTitle').textContent = 'Add New Part';
            document.getElementById('formAction').value = 'add';
            document.getElementById('submitBtn').textContent = 'Add Part';
            document.getElementById('partForm').reset();
            document.getElementById('partModal').style.display = 'flex';
        }

        function editPart(part) {
            document.getElementById('modalTitle').textContent = 'Edit Part';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('submitBtn').textContent = 'Update Part';
            document.getElementById('partId').value = part.id;
            document.getElementById('partCode').value = part.part_code;
            document.getElementById('partName').value = part.part_name;
            document.getElementById('status').value = part.status;
            document.getElementById('partModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('partModal').style.display = 'none';
        }

        function deletePart(id, name) {
            document.getElementById('deletePartId').value = id;
            document.getElementById('deletePartName').textContent = name;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modals on outside click
        window.onclick = function(event) {
            const partModal = document.getElementById('partModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target == partModal) {
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
                const partCode = row.cells[1].textContent.toLowerCase();
                const partName = row.cells[2].textContent.toLowerCase();
                
                if (partCode.includes(searchTerm) || partName.includes(searchTerm)) {
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
</body>
</html>

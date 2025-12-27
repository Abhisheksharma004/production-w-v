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
$activePage = 'line-management';
$pageTitle = 'Line Management';

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getSQLSrvConnection();
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            // Add new line
            $lineName = trim($_POST['line_name']);
            $lineCode = trim($_POST['user_email']);
            $status = $_POST['status'];
            $password = trim($_POST['password']);
            $confirmPassword = trim($_POST['confirm_password']);
            
            if ($password !== $confirmPassword) {
                $_SESSION['message'] = "Passwords do not match!";
                $_SESSION['messageType'] = "error";
            } else {
                $sql = "INSERT INTO lines (line_name, user_email, password, status) VALUES (?, ?, ?, ?)";
                $params = [$lineName, $lineCode, $password, $status];
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    $_SESSION['message'] = "Line added successfully!";
                    $_SESSION['messageType'] = "success";
                    sqlsrv_free_stmt($stmt);
                } else {
                    $_SESSION['message'] = "Error adding line.";
                    $_SESSION['messageType'] = "error";
                }
            }
            header("Location: line-management.php");
            exit();
        } elseif ($_POST['action'] == 'edit') {
            // Update existing line
            $lineId = (int)$_POST['line_id'];
            $lineName = trim($_POST['line_name']);
            $lineCode = trim($_POST['user_email']);
            $status = $_POST['status'];
            $password = trim($_POST['password']);
            $confirmPassword = trim($_POST['confirm_password']);
            
            if ($password !== $confirmPassword) {
                $_SESSION['message'] = "Passwords do not match!";
                $_SESSION['messageType'] = "error";
            } else {
                $sql = "UPDATE lines SET line_name = ?, user_email = ?, password = ?, status = ?, updated_at = GETDATE() WHERE id = ?";
                $params = [$lineName, $lineCode, $password, $status, $lineId];
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    $_SESSION['message'] = "Line updated successfully!";
                    $_SESSION['messageType'] = "success";
                    sqlsrv_free_stmt($stmt);
                } else {
                    $_SESSION['message'] = "Error updating line.";
                    $_SESSION['messageType'] = "error";
                }
            }
            header("Location: line-management.php");
            exit();
        } elseif ($_POST['action'] == 'delete') {
            // Delete line
            $lineId = (int)$_POST['line_id'];
            
            $sql = "DELETE FROM lines WHERE id = ?";
            $params = [$lineId];
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt) {
                $_SESSION['message'] = "Line deleted successfully!";
                $_SESSION['messageType'] = "success";
                sqlsrv_free_stmt($stmt);
            } else {
                $_SESSION['message'] = "Error deleting line.";
                $_SESSION['messageType'] = "error";
            }
            header("Location: line-management.php");
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

// Fetch all lines
$lines = [];
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        $sql = "SELECT * FROM lines ORDER BY user_email";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $lines[] = $row;
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
    <title>Line Management - Production Management System</title>
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
                    <h2>Production Lines</h2>
                    <p>Manage your production lines and their configurations</p>
                </div>
                <button class="btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Line
                </button>
            </div>

            <!-- Lines Table -->
            <div class="table-container">
                <?php if (empty($lines)): ?>
                    <div class="empty-state">
                        <i class="fas fa-layer-group"></i>
                        <h3>No Production Lines Yet</h3>
                        <p>Get started by adding your first production line</p>
                        <button class="btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Line
                        </button>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Line Name</th>
                                <th>User ID / Email</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($lines as $line): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td class="line-name"><?php echo htmlspecialchars($line['line_name']); ?></td>
                                    <td class="line-code"><?php echo htmlspecialchars($line['user_email']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($line['status']); ?>">
                                            <?php echo htmlspecialchars($line['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo isset($line['created_at']) ? $line['created_at']->format('Y-m-d H:i') : 'N/A'; ?></td>
                                    <td class="action-buttons">
                                        <button class="btn-edit" onclick='editLine(<?php echo json_encode($line); ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete" onclick="deleteLine(<?php echo $line['id']; ?>, '<?php echo htmlspecialchars($line['line_name']); ?>')" title="Delete">
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
    <div class="modal" id="lineModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Line</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="lineForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="line_id" id="lineId">
                
                <div class="form-group">
                    <label for="lineName">Line Name *</label>
                    <input type="text" id="lineName" name="line_name" required>
                </div>
                
                <div class="form-group">
                    <label for="lineCode">User ID or Email ID *</label>
                    <input type="text" id="lineCode" name="user_email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" required>
                        <span class="toggle-password" onclick="togglePassword('password', this)">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password *</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirmPassword" name="confirm_password" required>
                        <span class="toggle-password" onclick="togglePassword('confirmPassword', this)">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="submitBtn">Add Line</button>
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
                <p>Are you sure you want to delete <strong id="deleteLineName"></strong>?</p>
                <p class="warning-text">This action cannot be undone.</p>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="line_id" id="deleteLineId">
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
            document.getElementById('modalTitle').textContent = 'Add New Line';
            document.getElementById('formAction').value = 'add';
            document.getElementById('submitBtn').textContent = 'Add Line';
            document.getElementById('lineForm').reset();
            document.getElementById('lineModal').style.display = 'flex';
        }

        function editLine(line) {
            document.getElementById('modalTitle').textContent = 'Edit Line';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('submitBtn').textContent = 'Update Line';
            document.getElementById('lineId').value = line.id;
            document.getElementById('lineName').value = line.line_name;
            document.getElementById('lineCode').value = line.user_email;
            document.getElementById('password').value = line.password || '';
            document.getElementById('confirmPassword').value = line.password || '';
            document.getElementById('status').value = line.status;
            document.getElementById('lineModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('lineModal').style.display = 'none';
        }

        function deleteLine(id, name) {
            document.getElementById('deleteLineId').value = id;
            document.getElementById('deleteLineName').textContent = name;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modals on outside click
        window.onclick = function(event) {
            const lineModal = document.getElementById('lineModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target == lineModal) {
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
                const lineName = row.querySelector('.line-name').textContent.toLowerCase();
                const userEmail = row.querySelector('.line-code').textContent.toLowerCase();
                
                if (lineName.includes(searchTerm) || userEmail.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Toggle password visibility
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            const iconElement = icon.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            }
        }

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

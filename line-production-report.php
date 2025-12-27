<?php
session_start();

// Check if line user is logged in
if (!isset($_SESSION['line_id']) || $_SESSION['user_type'] !== 'line') {
    header("Location: line-login.php");
    exit();
}

// Include database configuration
require_once 'config/database.php';

$line_name = $_SESSION['line_name'];
$line_email = $_SESSION['line_email'];
$activePage = 'production-records';
$pageTitle = 'Production Records';

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

// Fetch stages metadata
$stagesMetadata = [];
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        $sql = "SELECT sm.*, p.part_name 
                FROM stages_metadata sm 
                LEFT JOIN parts p ON sm.part_id = p.id 
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
    <title>Production Records - <?php echo htmlspecialchars($line_name); ?></title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/line-management.css">
    <link rel="stylesheet" href="css/production-report.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom styling for line production report header */
        .header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            padding: 12px 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .header-left .page-title h1 {
            color: white;
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 3px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-left .page-title p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .user-avatar {
            font-size: 32px;
            color: white;
            display: flex;
            align-items: center;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        
        .user-name {
            color: white;
            font-size: 13px;
            font-weight: 600;
        }
        
        .user-role {
            color: rgba(255, 255, 255, 0.7);
            font-size: 11px;
        }
        
        .logout {
            color: white;
            font-size: 20px;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .logout:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .sidebar .logo {
            background: rgba(255, 255, 255, 0.05);
            padding: 25px 20px;
        }

        .sidebar .logo h2 {
            font-size: 18px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-complete {
            background-color: #d4edda;
            color: #155724;
        }

        .status-incomplete {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
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
            <a href="material-in.php" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                <span>Material In</span>
            </a>
            <a href="line-production-report.php" class="nav-item active">
                <i class="fas fa-list"></i>
                <span>Production Records</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-left">
                <div class="page-title">
                    <h1><i class="fas fa-list"></i> Production Records</h1>
                    <p>View and analyze production data</p>
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

        <!-- Dashboard Content -->
        <div class="dashboard-container">
            <div class="page-header">
                <div class="page-title">
                    <h2>Production Records</h2>
                    <p>View and analyze production data</p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="filterPart">Select Part</label>
                        <select id="filterPart" class="form-control">
                            <option value="">-- Select Part --</option>
                            <?php foreach ($parts as $part): ?>
                                <option value="<?php echo $part['id']; ?>" 
                                        data-code="<?php echo htmlspecialchars($part['part_code']); ?>"
                                        data-name="<?php echo htmlspecialchars($part['part_name']); ?>">
                                    <?php echo htmlspecialchars($part['part_code'] . ' - ' . $part['part_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filterDateFrom">Date From</label>
                        <input type="date" id="filterDateFrom" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="filterDateTo">Date To</label>
                        <input type="date" id="filterDateTo" class="form-control">
                    </div>
                    <div class="form-group" style="align-self: flex-end;">
                        <button class="btn-primary" onclick="loadReport()">
                            <i class="fas fa-search"></i> Get Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Report Display -->
            <div id="reportContainer" style="display: none;">
                <div class="report-header">
                    <h3 id="reportTitle"></h3>
                    <div class="report-actions">
                        <button class="btn-secondary" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Export to Excel
                        </button>
                    </div>
                </div>

                <!-- Production Data Table -->
                <div class="table-container">
                    <table class="data-table" id="reportTable">
                        <thead>
                            <tr id="tableHeaders">
                                <!-- Dynamic headers will be inserted here -->
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- Dynamic data rows will be inserted here -->
                        </tbody>
                    </table>
                </div>

                <!-- Summary Section -->
                <div class="summary-section">
                    <h4>Production Summary</h4>
                    <div class="summary-grid">
                        <div class="summary-card">
                            <div class="summary-label">Total Items</div>
                            <div class="summary-value" id="totalItems">0</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Completed Items</div>
                            <div class="summary-value" id="completedItems">0</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">In Progress</div>
                            <div class="summary-value" id="inProgressItems">0</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Completion Rate</div>
                            <div class="summary-value" id="completionRate">0%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div class="empty-state" id="emptyState">
                <i class="fas fa-chart-bar"></i>
                <h3>No Report Generated</h3>
                <p>Select a part and date range, then click "Get Report" to view production data</p>
            </div>
        </div>
    </div>
    
    <script>
        let currentPartCode = '';
        let stagesMetadata = <?php echo json_encode($stagesMetadata); ?>;

        function loadReport() {
            const partSelect = document.getElementById('filterPart');
            const partId = partSelect.value;
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;

            if (!partId) {
                alert('Please select a part');
                return;
            }

            const selectedOption = partSelect.options[partSelect.selectedIndex];
            currentPartCode = selectedOption.dataset.code;
            const partName = selectedOption.dataset.name;

            // Find metadata for selected part
            const metadata = stagesMetadata.find(m => m.part_id == partId);
            
            if (!metadata) {
                alert('No stages configured for this part');
                return;
            }

            // Get stage names
            const stageNames = JSON.parse(metadata.stage_names);
            
            // Build table headers
            let headers = '<th>S.No</th><th>Date</th>';
            stageNames.forEach(stage => {
                headers += `<th>${stage}</th>`;
            });
            headers += '<th>Status</th>';
            
            document.getElementById('tableHeaders').innerHTML = headers;
            
            // Update report title
            document.getElementById('reportTitle').textContent = 
                `Production Report - ${currentPartCode} (${partName})`;

            // Show report container
            document.getElementById('reportContainer').style.display = 'block';
            document.getElementById('emptyState').style.display = 'none';

            // Load data from database (will be implemented with actual data)
            loadProductionData(metadata.table_name, stageNames, dateFrom, dateTo);
        }

        function loadProductionData(tableName, stageNames, dateFrom, dateTo) {
            const partSelect = document.getElementById('filterPart');
            const partId = partSelect.value;

            // Show loading state
            document.getElementById('tableBody').innerHTML = 
                '<tr><td colspan="100" style="text-align: center; padding: 40px; color: #64748b;"><i class="fas fa-spinner fa-spin"></i> Loading data...</td></tr>';

            // Prepare form data
            const formData = new FormData();
            formData.append('part_id', partId);
            formData.append('table_name', tableName);
            formData.append('stage_names', JSON.stringify(stageNames));
            formData.append('date_from', dateFrom || '');
            formData.append('date_to', dateTo || '');

            // Fetch data via AJAX
            fetch('get-production-data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.error) {
                    document.getElementById('tableBody').innerHTML = 
                        `<tr><td colspan="100" style="text-align: center; padding: 40px; color: #ef4444;">Error: ${result.error}</td></tr>`;
                    return;
                }

                if (result.success && result.data.length > 0) {
                    let tableHTML = '';
                    result.data.forEach((row, index) => {
                        tableHTML += '<tr>';
                        tableHTML += `<td>${index + 1}</td>`;
                        tableHTML += `<td>${row.created_at ? new Date(row.created_at).toLocaleDateString() : '-'}</td>`;
                        
                        row.stages.forEach(stageValue => {
                            tableHTML += `<td>${stageValue || '-'}</td>`;
                        });
                        
                        const statusClass = row.status === 'Complete' ? 'status-complete' : 'status-incomplete';
                        tableHTML += `<td><span class="status-badge ${statusClass}">${row.status}</span></td>`;
                        tableHTML += '</tr>';
                    });
                    
                    document.getElementById('tableBody').innerHTML = tableHTML;
                    
                    // Update summary
                    document.getElementById('totalItems').textContent = result.summary.total;
                    document.getElementById('completedItems').textContent = result.summary.completed;
                    document.getElementById('inProgressItems').textContent = result.summary.inProgress;
                    document.getElementById('completionRate').textContent = result.summary.completionRate + '%';
                } else {
                    document.getElementById('tableBody').innerHTML = 
                        '<tr><td colspan="100" style="text-align: center; padding: 40px; color: #64748b;">No production data available</td></tr>';
                    
                    // Reset summary
                    document.getElementById('totalItems').textContent = '0';
                    document.getElementById('completedItems').textContent = '0';
                    document.getElementById('inProgressItems').textContent = '0';
                    document.getElementById('completionRate').textContent = '0%';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('tableBody').innerHTML = 
                    '<tr><td colspan="100" style="text-align: center; padding: 40px; color: #ef4444;">Failed to load data. Please try again.</td></tr>';
            });
        }

        function exportToExcel() {
            const partSelect = document.getElementById('filterPart');
            if (!partSelect.value) {
                alert('Please generate a report first');
                return;
            }

            const selectedOption = partSelect.options[partSelect.selectedIndex];
            const partCode = selectedOption.dataset.code;
            const partName = selectedOption.dataset.name;
            const dateFrom = document.getElementById('filterDateFrom').value;
            const dateTo = document.getElementById('filterDateTo').value;

            // Get table headers
            const headerCells = document.querySelectorAll('#tableHeaders th');
            const headers = [];
            headerCells.forEach(cell => {
                headers.push(cell.textContent);
            });

            // Get table data
            const dataRows = document.querySelectorAll('#tableBody tr');
            const data = [];
            dataRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const rowData = [];
                    cells.forEach(cell => {
                        rowData.push(cell.textContent.trim());
                    });
                    data.push(rowData);
                }
            });

            // Get summary data
            const summary = {
                total: document.getElementById('totalItems').textContent,
                completed: document.getElementById('completedItems').textContent,
                inProgress: document.getElementById('inProgressItems').textContent,
                completionRate: document.getElementById('completionRate').textContent
            };

            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export-report.php';
            form.style.display = 'none';

            const inputs = {
                part_code: partCode,
                part_name: partName,
                date_from: dateFrom,
                date_to: dateTo,
                headers: JSON.stringify(headers),
                data: JSON.stringify(data),
                summary: JSON.stringify(summary)
            };

            for (const key in inputs) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = inputs[key];
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
</body>
</html>

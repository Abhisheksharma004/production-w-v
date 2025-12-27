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
$activePage = 'material-in';
$pageTitle = 'Material In';

// Fetch all production lines for filter
$lines = [];
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        $sql = "SELECT id, line_name FROM lines WHERE status = 'Active' ORDER BY line_name";
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

// Fetch parts for dropdown
$parts = [];
try {
    $conn = getSQLSrvConnection();
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

// Fetch all material receipts (admin can see all)
$allMaterials = [];
$dataFetchError = null;
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        $sql = "SELECT m.*, l.line_name 
                FROM material_in m 
                LEFT JOIN lines l ON m.line_id = l.id 
                ORDER BY m.created_at DESC";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $allMaterials[] = $row;
            }
            sqlsrv_free_stmt($stmt);
        } else {
            $errors = sqlsrv_errors();
            $dataFetchError = "Error fetching materials: " . print_r($errors, true);
        }
    } else {
        $dataFetchError = "Database connection failed";
    }
} catch (Exception $e) {
    $dataFetchError = "Exception: " . $e->getMessage();
}

// Calculate statistics
$totalMaterials = count($allMaterials);
$openProductions = 0;
$closedProductions = 0;
$totalInQuantity = 0;
$totalFinalProduction = 0;
$totalScrap = 0;

foreach ($allMaterials as $material) {
    if ($material['production_status'] == 'Closed') {
        $closedProductions++;
    } else {
        $openProductions++;
    }
    $totalInQuantity += $material['in_quantity'];
    if ($material['final_production_quantity']) {
        $totalFinalProduction += $material['final_production_quantity'];
    }
    if (isset($material['scrap_quantity']) && $material['scrap_quantity']) {
        $totalScrap += $material['scrap_quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Material In - Production Management System</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/line-management.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/material-in.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="dashboard-container">
            <?php if ($dataFetchError): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($dataFetchError); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid" style="margin-bottom: 30px;">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($totalMaterials); ?></h3>
                        <p>Total Materials</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($openProductions); ?></h3>
                        <p>Open Productions</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($closedProductions); ?></h3>
                        <p>Closed Productions</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($totalFinalProduction); ?></h3>
                        <p>Final Production Total</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($totalScrap); ?></h3>
                        <p>Total Scrap</p>
                    </div>
                </div>
            </div>

            <div class="page-header">
                <div class="page-title">
                    <h2>Material In Records</h2>
                    <p>View all incoming materials across production lines</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button class="btn-export-excel" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-container">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i>
                        <span>Filter Records</span>
                    </div>
                    <div class="filter-controls">
                        <div class="filter-group">
                            <label for="filter_line">Production Line</label>
                            <select id="filter_line" class="filter-input">
                                <option value="">All Lines</option>
                                <?php foreach ($lines as $line): ?>
                                    <option value="<?php echo $line['id']; ?>"><?php echo htmlspecialchars($line['line_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                        <div class="filter-group">
                            <label for="filter_status">Status</label>
                            <select id="filter_status" class="filter-input">
                                <option value="">All Status</option>
                                <option value="Open">Open</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button class="btn-filter" onclick="applyFilter()">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <button class="btn-reset-filter" onclick="resetFilter()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Materials Table -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #e2e8f0;">
                    <div>
                        <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b;">Material Records</h3>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #64748b;">Showing <?php echo number_format(count($allMaterials)); ?> records</p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span style="font-size: 12px; color: #64748b;">Last updated: <?php echo date('d M Y H:i'); ?></span>
                        <button onclick="location.reload()" class="btn-secondary" style="padding: 8px 16px;">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                <?php if (count($allMaterials) > 0): ?>
                <div class="table-container">
                    <table class="recent-materials-table">
                        <thead>
                            <tr>
                                <th>Production Line</th>
                                <th>Received Date</th>
                                <th>Part Code</th>
                                <th>Part Name</th>
                                <th>Batch Number</th>
                                <th>In Qty</th>
                                <th>Production Qty</th>
                                <th>Final Production</th>
                                <th>Scrap</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allMaterials as $material): ?>
                            <tr data-line-id="<?php echo $material['line_id']; ?>" data-status="<?php echo htmlspecialchars($material['production_status']); ?>">
                                <td><?php echo htmlspecialchars($material['line_name']); ?></td>
                                <td>
                                    <?php 
                                    if ($material['received_date'] instanceof DateTime) {
                                        echo $material['received_date']->format('d M Y H:i');
                                    } else {
                                        echo date('d M Y H:i', strtotime($material['received_date']));
                                    }
                                    ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($material['part_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($material['part_name']); ?></td>
                                <td><span class="badge-batch"><?php echo htmlspecialchars($material['batch_number']); ?></span></td>
                                <td><?php echo $material['in_quantity'] . ' ' . $material['in_units']; ?></td>
                                <td><?php echo $material['production_quantity'] . ' ' . $material['production_units']; ?></td>
                                <td>
                                    <?php 
                                    echo $material['final_production_quantity'] 
                                        ? '<span class="qty-value">' . $material['final_production_quantity'] . ' ' . $material['production_units'] . '</span>' 
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    echo $material['scrap_quantity'] 
                                        ? '<span class="qty-scrap">' . $material['scrap_quantity'] . ' ' . $material['production_units'] . '</span>' 
                                        : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($material['production_status'] == 'Closed'): ?>
                                        <span class="badge-status closed">Closed</span>
                                    <?php else: ?>
                                        <span class="badge-status open">Open</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Material Records Found</h3>
                    <p>Material records will appear here once production lines start recording receipts</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    
    <script>
        // Filter function
        function applyFilter() {
            const lineId = document.getElementById('filter_line').value;
            const dateFrom = document.getElementById('filter_date_from').value;
            const dateTo = document.getElementById('filter_date_to').value;
            const partCode = document.getElementById('filter_part_code').value.trim().toLowerCase();
            const batchNumber = document.getElementById('filter_batch_number').value.trim().toLowerCase();
            const status = document.getElementById('filter_status').value;
            
            const table = document.querySelector('.recent-materials-table');
            if (!table) return;
            
            const rows = table.querySelectorAll('tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length === 0) return;
                
                let showRow = true;
                
                // Filter by line
                if (lineId && showRow) {
                    const rowLineId = row.getAttribute('data-line-id');
                    if (rowLineId != lineId) {
                        showRow = false;
                    }
                }
                
                // Filter by status
                if (status && showRow) {
                    const rowStatus = row.getAttribute('data-status');
                    if (rowStatus != status) {
                        showRow = false;
                    }
                }
                
                // Filter by date
                if ((dateFrom || dateTo) && showRow) {
                    const dateCell = cells[1];
                    const dateText = dateCell.textContent.trim();
                    const rowDate = parseDateFromCell(dateText);
                    
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
                    const batchCell = cells[4];
                    const rowBatchNumber = batchCell.textContent.trim().toLowerCase();
                    if (!rowBatchNumber.includes(batchNumber)) {
                        showRow = false;
                    }
                }
                
                if (showRow) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (visibleCount === 0) {
                alert('No records found for the selected filters.');
            }
        }

        function parseDateFromCell(dateText) {
            try {
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

        function resetFilter() {
            document.getElementById('filter_line').value = '';
            document.getElementById('filter_date_from').value = '';
            document.getElementById('filter_date_to').value = '';
            document.getElementById('filter_part_code').value = '';
            document.getElementById('filter_batch_number').value = '';
            document.getElementById('filter_status').value = '';
            
            const table = document.querySelector('.recent-materials-table');
            if (!table) return;
            
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.style.display = '';
            });
        }

        function exportToExcel() {
            const table = document.querySelector('.recent-materials-table');
            if (!table) {
                alert('No data available to export');
                return;
            }

            // Get visible rows only
            const rows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
            
            if (rows.length === 0) {
                alert('No data available to export');
                return;
            }

            // Get headers
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.textContent.trim());
            });

            // Get data
            const data = [];
            rows.forEach(row => {
                const rowData = [];
                row.querySelectorAll('td').forEach(td => {
                    rowData.push(td.textContent.trim());
                });
                data.push(rowData);
            });

            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export-report.php';
            form.style.display = 'none';

            const inputs = {
                part_code: 'Material In',
                part_name: 'All Lines',
                date_from: document.getElementById('filter_date_from').value || '',
                date_to: document.getElementById('filter_date_to').value || '',
                headers: JSON.stringify(headers),
                data: JSON.stringify(data),
                summary: JSON.stringify({ total: rows.length })
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

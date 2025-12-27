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
$activePage = 'dashboard';
$pageTitle = 'Dashboard';

// Initialize variables for dashboard stats
$total_parts = 0;
$total_materials = 0;
$open_productions = 0;
$closed_productions = 0;
$total_production = 0;
$total_scrap = 0;
$completion_rate = 0;

// Fetch real statistics from database
try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        // Get total parts
        $sql = "SELECT COUNT(*) as count FROM parts";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $total_parts = $row['count'];
            sqlsrv_free_stmt($stmt);
        }
        
        // Get material_in statistics
        $sql = "SELECT 
                    COUNT(*) as total_materials,
                    SUM(CASE WHEN production_status = 'Open' THEN 1 ELSE 0 END) as open_count,
                    SUM(CASE WHEN production_status = 'Closed' THEN 1 ELSE 0 END) as closed_count,
                    SUM(ISNULL(final_production_quantity, 0)) as total_final,
                    SUM(ISNULL(scrap_quantity, 0)) as total_scrap
                FROM material_in";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $total_materials = $row['total_materials'];
            $open_productions = $row['open_count'];
            $closed_productions = $row['closed_count'];
            $total_production = $row['total_final'];
            $total_scrap = $row['total_scrap'];
            sqlsrv_free_stmt($stmt);
        }
        
        // Calculate completion rate
        if ($total_materials > 0) {
            $completion_rate = round(($closed_productions / $total_materials) * 100, 1);
        }
        
        // Get recent activities from material_in
        $recent_activities = [];
        $sql = "SELECT TOP 5 m.*, p.part_name, l.line_name,
                DATEDIFF(MINUTE, m.created_at, GETDATE()) as minutes_ago
                FROM material_in m
                LEFT JOIN parts p ON m.part_code = p.part_code
                LEFT JOIN lines l ON m.line_id = l.id
                ORDER BY m.created_at DESC";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $recent_activities[] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }
    }
} catch (Exception $e) {
    // Handle error silently
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Production Management System</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <!-- Dashboard Content -->
        <div class="dashboard-container">
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($total_parts); ?></h3>
                        <p>Total Parts</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($total_materials); ?></h3>
                        <p>Total Materials</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($open_productions); ?></h3>
                        <p>Open Productions</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($closed_productions); ?></h3>
                        <p>Closed Productions</p>
                    </div>
                </div>
            </div>

            <!-- Charts and Tables -->
            <div class="content-grid">
                <!-- Production Overview -->
                <div class="card chart-card">
                    <div class="card-header">
                        <h3>Production Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="overview-stats">
                            <div class="overview-item">
                                <div class="overview-icon blue">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <div class="overview-details">
                                    <h4><?php echo number_format($total_materials); ?></h4>
                                    <p>Total Materials In</p>
                                </div>
                            </div>
                            <div class="overview-item">
                                <div class="overview-icon green">
                                    <i class="fas fa-industry"></i>
                                </div>
                                <div class="overview-details">
                                    <h4><?php echo number_format($total_production); ?></h4>
                                    <p>Total Production</p>
                                </div>
                            </div>
                            <div class="overview-item">
                                <div class="overview-icon purple">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <div class="overview-details">
                                    <h4><?php echo $completion_rate; ?>%</h4>
                                    <p>Completion Rate</p>
                                </div>
                            </div>
                            <div class="overview-item">
                                <div class="overview-icon red">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="overview-details">
                                    <h4><?php echo number_format($total_scrap); ?></h4>
                                    <p>Total Scrap</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Production Status Chart -->
                <div class="card">
                    <div class="card-header">
                        <h3>Production Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="pie-chart-container">
                            <canvas id="productionPieChart" style="max-height: 250px;"></canvas>
                        </div>
                        <div class="pie-chart-legend">
                            <div class="pie-legend-item">
                                <span class="pie-legend-color" style="background: #10b981;"></span>
                                <span class="pie-legend-label">Closed: <?php echo $closed_productions; ?></span>
                            </div>
                            <div class="pie-legend-item">
                                <span class="pie-legend-color" style="background: #f59e0b;"></span>
                                <span class="pie-legend-label">Open: <?php echo $open_productions; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Activities</h3>
                    </div>
                    <div class="card-body">
                        <div class="activity-list">
                            <?php if (empty($recent_activities)): ?>
                                <div class="activity-item">
                                    <div class="activity-content">
                                        <p>No recent activities</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): 
                                    $minutes = $activity['minutes_ago'];
                                    if ($minutes < 60) {
                                        $timeAgo = $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ago';
                                    } elseif ($minutes < 1440) {
                                        $hours = floor($minutes / 60);
                                        $timeAgo = $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
                                    } else {
                                        $days = floor($minutes / 1440);
                                        $timeAgo = $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
                                    }
                                    
                                    $iconClass = $activity['production_status'] == 'Closed' ? 'green' : 'blue';
                                    $icon = $activity['production_status'] == 'Closed' ? 'fa-check' : 'fa-inbox';
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?php echo $iconClass; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p><strong><?php echo htmlspecialchars($activity['part_code']); ?></strong> - <?php echo htmlspecialchars($activity['part_name']); ?> (<?php echo htmlspecialchars($activity['line_name']); ?>)</p>
                                        <span class="time"><?php echo $timeAgo; ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/scripts.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Production Status Pie Chart
        const ctx = document.getElementById('productionPieChart').getContext('2d');
        const productionPieChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Closed Productions', 'Open Productions'],
                datasets: [{
                    data: [<?php echo $closed_productions; ?>, <?php echo $open_productions; ?>],
                    backgroundColor: ['#10b981', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed;
                                let total = <?php echo $total_materials; ?>;
                                let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    </script>
</body>
</html>

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
$activePage = 'dashboard';
$pageTitle = 'Line Dashboard';

// Fetch real statistics from database
$total_production = 0;
$today_production = 0;
$pending_items = 0;
$completed_items = 0;
$recentActivities = [];

try {
    $conn = getSQLSrvConnection();
    if ($conn !== false) {
        
        // Get all stages metadata to count production
        $sql = "SELECT part_id, table_name, stage_names FROM stages_metadata";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt) {
            while ($metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $tableName = $metaRow['table_name'];
                $partId = $metaRow['part_id'];
                $stageNames = json_decode($metaRow['stage_names'], true);
                
                // Count total production entries for this part
                $countSql = "SELECT COUNT(*) as total FROM [$tableName] WHERE part_id = ?";
                $countStmt = sqlsrv_query($conn, $countSql, [$partId]);
                if ($countStmt && $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC)) {
                    $total_production += $countRow['total'];
                }
                
                // Count today's production
                $todaySql = "SELECT COUNT(*) as total FROM [$tableName] 
                            WHERE part_id = ? AND CAST(created_at AS DATE) = CAST(GETDATE() AS DATE)";
                $todayStmt = sqlsrv_query($conn, $todaySql, [$partId]);
                if ($todayStmt && $todayRow = sqlsrv_fetch_array($todayStmt, SQLSRV_FETCH_ASSOC)) {
                    $today_production += $todayRow['total'];
                }
                
                // Count completed vs incomplete items
                $dataSql = "SELECT * FROM [$tableName] WHERE part_id = ?";
                $dataStmt = sqlsrv_query($conn, $dataSql, [$partId]);
                
                if ($dataStmt) {
                    while ($dataRow = sqlsrv_fetch_array($dataStmt, SQLSRV_FETCH_ASSOC)) {
                        $isComplete = true;
                        foreach ($stageNames as $stageIndex => $stageName) {
                            $stageNumber = $stageIndex + 1;
                            $columnName = 'stage_' . $stageNumber . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $stageName));
                            if (empty($dataRow[$columnName])) {
                                $isComplete = false;
                                break;
                            }
                        }
                        
                        if ($isComplete) {
                            $completed_items++;
                        } else {
                            $pending_items++;
                        }
                    }
                }
            }
        }
        
        // Get recent activities (last 5 entries from all part tables)
        $sql = "SELECT part_id, table_name FROM stages_metadata";
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt) {
            $allActivities = [];
            while ($metaRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $tableName = $metaRow['table_name'];
                $partId = $metaRow['part_id'];
                
                // Get part name
                $partSql = "SELECT part_code, part_name FROM parts WHERE id = ?";
                $partStmt = sqlsrv_query($conn, $partSql, [$partId]);
                $partInfo = sqlsrv_fetch_array($partStmt, SQLSRV_FETCH_ASSOC);
                
                // Get recent entries
                $activitySql = "SELECT TOP 5 id, created_at, updated_at FROM [$tableName] 
                               WHERE part_id = ? ORDER BY created_at DESC";
                $activityStmt = sqlsrv_query($conn, $activitySql, [$partId]);
                
                if ($activityStmt) {
                    while ($actRow = sqlsrv_fetch_array($activityStmt, SQLSRV_FETCH_ASSOC)) {
                        $allActivities[] = [
                            'part_code' => $partInfo['part_code'],
                            'part_name' => $partInfo['part_name'],
                            'created_at' => $actRow['created_at'],
                            'id' => $actRow['id']
                        ];
                    }
                }
            }
            
            // Sort all activities by date and get top 5
            usort($allActivities, function($a, $b) {
                return $b['created_at'] <=> $a['created_at'];
            });
            
            $recentActivities = array_slice($allActivities, 0, 5);
        }
    }
} catch (Exception $e) {
    // Handle errors silently
}

// Calculate completion rate
$completion_rate = $total_production > 0 ? round(($completed_items / $total_production) * 100, 1) : 0;

// Calculate production overview metrics
$material_in = 0;
$production_quantity = 0;
$final_production = 0;

try {
    if ($conn !== false) {
        // Material In: Total entries created (Stage 1 entries)
        $material_in = $total_production;
        
        // Production Quantity: Items that have at least one stage completed
        $production_quantity = $completed_items + $pending_items;
        
        // Final Production: Completed items
        $final_production = $completed_items;
    }
} catch (Exception $e) {
    // Handle errors silently
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Line Dashboard - <?php echo htmlspecialchars($line_name); ?></title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom styling for line dashboard header */
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

        /* Improve sidebar for line dashboard */
        .sidebar .logo {
            background: rgba(255, 255, 255, 0.05);
            padding: 25px 20px;
        }

        .sidebar .logo h2 {
            font-size: 18px;
        }

        /* Custom bar colors for production overview */
        .bar.material-in {
            background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .bar.production {
            background: linear-gradient(180deg, #10b981 0%, #059669 100%);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .bar.final-product {
            background: linear-gradient(180deg, #8b5cf6 0%, #7c3aed 100%);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }

        .legend-color.material-in {
            background: #3b82f6;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }

        .legend-color.final-product {
            background: #8b5cf6;
            box-shadow: 0 2px 4px rgba(139, 92, 246, 0.3);
        }

        /* Enhanced production overview chart */
        .production-stats {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            height: 280px;
            padding: 30px 20px 20px;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            border-radius: 12px;
            margin-bottom: 25px;
            gap: 30px;
        }

        .production-day {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .production-day .day-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-align: center;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .production-day .bar-container {
            width: 100%;
            max-width: 80px;
            height: 200px;
            background: #e2e8f0;
            border-radius: 8px 8px 0 0;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);
        }

        .production-day .bar {
            width: 100%;
            position: absolute;
            bottom: 0;
            border-radius: 8px 8px 0 0;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 10px;
        }

        .production-day:hover .bar {
            transform: scaleY(1.02);
            filter: brightness(1.1);
        }

        .production-day .day-value {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 8px;
            padding: 8px 16px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            min-width: 60px;
            text-align: center;
        }

        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 25px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            padding: 8px 15px;
            background: white;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .legend-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            flex-shrink: 0;
        }

        .card.chart-card {
            position: relative;
        }

        .card.chart-card .card-header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .card.chart-card .card-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }

        .card.chart-card .badge {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        /* Progress bars for production overview */
        .progress-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
            padding: 10px 0;
        }

        .progress-item {
            position: relative;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .progress-label {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-label i {
            font-size: 16px;
        }

        .progress-value {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        .progress-bar-container {
            width: 100%;
            height: 12px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);
        }

        .progress-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .progress-percent {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-top: 4px;
            display: block;
            text-align: right;
        }
    </style>
</head>
<body>
    <?php 
    // Create a custom sidebar for line users
    ?>
    <div class="sidebar">
        <div class="logo">
            <img src="images/logo.jpg" alt="Viros Logo" class="logo-img">
            <h2>Production Line</h2>
        </div>
        <nav class="nav-menu">
            <a href="line-dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="material-in.php" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                <span>Material In</span>
            </a>
            <a href="line-production-report.php" class="nav-item">
                <i class="fas fa-list"></i>
                <span>Production Records</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php 
        // Create a custom header for line users
        ?>
        <div class="header">
            <div class="header-left">
                <div class="page-title">
                    <h1><i class="fas fa-home"></i> <?php echo htmlspecialchars($line_name); ?></h1>
                    <p>Production Line Dashboard</p>
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
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($total_production); ?></h3>
                        <p>Total Production</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($today_production); ?></h3>
                        <p>Today's Production</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($pending_items); ?></h3>
                        <p>Pending Items</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo number_format($completed_items); ?></h3>
                        <p>Completed Items</p>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-percentage"></i> <?php echo $completion_rate; ?>%
                    </div>
                </div>
            </div>

            <!-- Charts and Tables -->
            <div class="content-grid">
                <!-- Production Overview -->
                <div class="card chart-card">
                    <div class="card-header">
                        <h3>Production Overview</h3>
                        <span class="badge"><?php echo date('F Y'); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="production-stats">
                            <div class="production-day">
                                <span class="day-label">Material In</span>
                                <div class="bar-container">
                                    <div class="bar material-in" style="height: <?php echo $material_in > 0 ? '100%' : '5%'; ?>;"></div>
                                </div>
                                <span class="day-value"><?php echo number_format($material_in); ?></span>
                            </div>
                            <div class="production-day">
                                <span class="day-label">Production</span>
                                <div class="bar-container">
                                    <?php 
                                    $prod_height = $material_in > 0 ? round(($production_quantity / $material_in) * 100) : 5;
                                    $prod_height = max(5, min(100, $prod_height));
                                    ?>
                                    <div class="bar production" style="height: <?php echo $prod_height; ?>%;"></div>
                                </div>
                                <span class="day-value"><?php echo number_format($production_quantity); ?></span>
                            </div>
                            <div class="production-day">
                                <span class="day-label">Final Product</span>
                                <div class="bar-container">
                                    <?php 
                                    $final_height = $material_in > 0 ? round(($final_production / $material_in) * 100) : 5;
                                    $final_height = max(5, min(100, $final_height));
                                    ?>
                                    <div class="bar final-product" style="height: <?php echo $final_height; ?>%;"></div>
                                </div>
                                <span class="day-value"><?php echo number_format($final_production); ?></span>
                            </div>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color material-in"></span>
                                <span>Material In Quantity</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color production"></span>
                                <span>Production Quantity</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color final-product"></span>
                                <span>Final Production</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Production Overview Graph -->
                <div class="card">
                    <div class="card-header">
                        <h3>Production Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="progress-list">
                            <div class="progress-item">
                                <div class="progress-header">
                                    <span class="progress-label">
                                        <i class="fas fa-inbox" style="color: #3b82f6;"></i>
                                        Material In
                                    </span>
                                    <span class="progress-value"><?php echo number_format($material_in); ?></span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: 100%; background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);"></div>
                                </div>
                            </div>

                            <div class="progress-item">
                                <div class="progress-header">
                                    <span class="progress-label">
                                        <i class="fas fa-cog" style="color: #10b981;"></i>
                                        In Production
                                    </span>
                                    <span class="progress-value"><?php echo number_format($production_quantity); ?></span>
                                </div>
                                <div class="progress-bar-container">
                                    <?php 
                                    $prod_percentage = $material_in > 0 ? round(($production_quantity / $material_in) * 100) : 0;
                                    ?>
                                    <div class="progress-bar" style="width: <?php echo $prod_percentage; ?>%; background: linear-gradient(90deg, #10b981 0%, #059669 100%);"></div>
                                </div>
                                <span class="progress-percent"><?php echo $prod_percentage; ?>%</span>
                            </div>

                            <div class="progress-item">
                                <div class="progress-header">
                                    <span class="progress-label">
                                        <i class="fas fa-box-open" style="color: #f59e0b;"></i>
                                        Pending
                                    </span>
                                    <span class="progress-value"><?php echo number_format($pending_items); ?></span>
                                </div>
                                <div class="progress-bar-container">
                                    <?php 
                                    $pending_percentage = $material_in > 0 ? round(($pending_items / $material_in) * 100) : 0;
                                    ?>
                                    <div class="progress-bar" style="width: <?php echo $pending_percentage; ?>%; background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);"></div>
                                </div>
                                <span class="progress-percent"><?php echo $pending_percentage; ?>%</span>
                            </div>

                            <div class="progress-item">
                                <div class="progress-header">
                                    <span class="progress-label">
                                        <i class="fas fa-check-circle" style="color: #8b5cf6;"></i>
                                        Completed
                                    </span>
                                    <span class="progress-value"><?php echo number_format($completed_items); ?></span>
                                </div>
                                <div class="progress-bar-container">
                                    <?php 
                                    $completed_percentage = $material_in > 0 ? round(($completed_items / $material_in) * 100) : 0;
                                    ?>
                                    <div class="progress-bar" style="width: <?php echo $completed_percentage; ?>%; background: linear-gradient(90deg, #8b5cf6 0%, #7c3aed 100%);"></div>
                                </div>
                                <span class="progress-percent"><?php echo $completed_percentage; ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Production -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Production Entries</h3>
                    </div>
                    <div class="card-body">
                        <div class="activity-list">
                            <?php if (!empty($recentActivities)): ?>
                                <?php foreach ($recentActivities as $activity): 
                                    $timeAgo = '';
                                    if ($activity['created_at']) {
                                        $now = new DateTime();
                                        $created = $activity['created_at'];
                                        $diff = $now->diff($created);
                                        
                                        if ($diff->d > 0) {
                                            $timeAgo = $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
                                        } elseif ($diff->h > 0) {
                                            $timeAgo = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                        } elseif ($diff->i > 0) {
                                            $timeAgo = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                        } else {
                                            $timeAgo = 'Just now';
                                        }
                                    }
                                ?>
                                <div class="activity-item">
                                    <div class="activity-icon blue">
                                        <i class="fas fa-plus"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p><strong><?php echo htmlspecialchars($activity['part_code']); ?></strong> - <?php echo htmlspecialchars($activity['part_name']); ?></p>
                                        <span class="time"><?php echo $timeAgo; ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="activity-item">
                                    <div class="activity-icon blue">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p>No recent production entries</p>
                                        <span class="time">Start scanning to see activity</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

if (strtolower($_SESSION['role'] ?? '') !== 'administrator') {
    http_response_code(403);
    exit('Access denied. Administrator privileges required.');
}?>
<?php
require_once 'db.php';

// Total vehicles
$totalVehicles = $pdo->query(
    "SELECT COUNT(*) FROM vehicles"
)->fetchColumn();

// Total drivers
$totalDrivers = $pdo->query(
    "SELECT COUNT(*) FROM drivers"
)->fetchColumn();

// Active vehicles
$activeVehicles = $pdo->query(
    "SELECT COUNT(*) FROM vehicles WHERE status = 'Active'"
)->fetchColumn();

// Total shipments
$totalShipments = $pdo->query(
    "SELECT COUNT(*) FROM shipments"
)->fetchColumn();
// Shipment delivery statistics
$deliveredShipments = $pdo->query(
    "SELECT COUNT(*) FROM shipments WHERE status = 'Delivered'"
)->fetchColumn();

$inTransitShipments = $pdo->query(
    "SELECT COUNT(*) FROM shipments WHERE status = 'In Transit'"
)->fetchColumn();

$pendingShipments = $pdo->query(
    "SELECT COUNT(*) FROM shipments WHERE status = 'Pending'"
)->fetchColumn();

$cancelledShipments = $pdo->query(
    "SELECT COUNT(*) FROM shipments WHERE status = 'Cancelled'"
)->fetchColumn();
// Get the latest 5 system activities
$recentActivities = $pdo->query(
    "SELECT action, details, created_at
     FROM audit_logs
     ORDER BY created_at DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Tracking Dashboard</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            margin: 0;
            font-family: 'DM Sans', Arial, sans-serif;
            background: #edf2f8;
            color: #172b4d;
        }

        .sidebar {
            width: 240px;
            height: 100vh;
            background: linear-gradient(180deg, #10243e, #173d68);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            padding: 25px 18px;
            box-sizing: border-box;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar h2 {
            font-size: 19px;
            margin-bottom: 35px;
            color: white;
        }

        .sidebar a {
            display: block;
            color: #dce7f5;
            text-decoration: none;
            padding: 13px 12px;
            margin-bottom: 7px;
            border-radius: 7px;
            font-size: 14px;
            transition: background .2s;
        }

        .sidebar a:hover {
            background: #28547c;
            color: white;
        }

        .sidebar a:first-of-type {
            background: #2563eb;
            color: white;
        }

        .logout {
            margin-top: 35px;
            background: #8b2525;
        }

        .main-content {
            margin-left: 240px;
            padding: 35px;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .welcome-card {
            background: white;
            padding: 35px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 5px 20px rgba(16, 36, 62, .06);
        }

        .welcome-card h1 {
            color: #10243e;
            font-size: 27px;
            margin-top: 0;
        }

        .welcome-card h2 {
            color: #1d4ed8;
            font-size: 21px;
            margin-top: 30px;
        }

        .welcome-card p {
            color: #64748b;
            line-height: 1.7;
        }

        .welcome-card hr {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 25px 0;
        }

        footer {
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            margin-top: 35px;
        }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin: 25px 0;
}

.stat-card {
    background: #ffffff;
    padding: 22px;
    border-radius: 10px;
    border-left: 5px solid #2563eb;
    box-shadow: 0 5px 15px rgba(16, 36, 62, 0.07);
}

.stat-card h3 {
    color: #64748b;
    font-size: 14px;
    margin: 0 0 12px;
}

.stat-card p {
    color: #10243e;
    font-size: 30px;
    font-weight: 800;
    margin: 0;
}
        @media (max-width: 700px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            .welcome-card {
                padding: 22px;
            }
        }
.activity-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 5px 20px rgba(16, 36, 62, 0.06);
    margin-top: 25px;
}

.activity-card h2 {
    color: #173d68;
    margin-top: 0;
}

.activity-table {
    width: 100%;
    overflow-x: auto;
}

.activity-table table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.activity-table th,
.activity-table td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.activity-table th {
    background: #10243e;
    color: white;
}

.activity-table tr:hover {
    background: #f1f5f9;
}
/* Fix dashboard content overlapping the sidebar */
body {
    padding-left: 265px !important;
    padding-right: 25px !important;
    box-sizing: border-box;
}

.welcome-card,
.stats-grid,
.activity-card {
    width: 100%;
    max-width: none;
    box-sizing: border-box;
}

.activity-table {
    width: 100%;
    overflow-x: auto;
}

@media (max-width: 700px) {
    body {
        padding: 15px !important;
    }
}
    </style>
</head>

<body>

<div class="sidebar">
    <h2>Fleet Management</h2>

    <a href="dashboard.php">Dashboard</a>
    <a href="vehicles.php">Vehicle Management</a>
    <a href="drivers.php">Driver Management</a>
    <a href="gps_tracker.php">Live Fleet Tracking</a>
    <a href="shipments.php">Shipment Monitoring</a>
    <a href="reports.php">Reports</a>
    <a href="audit_logs.php">Audit Logs</a>

    <a href="logout.php" class="logout">Log Out</a>
</div>
<div class="activity-card">
    <h2>Recent System Activities</h2>

    <div class="activity-table">
        <table>
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Details</th>
                    <th>Date and Time</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($recentActivities)): ?>
                    <tr>
                        <td colspan="3">No recent activities found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentActivities as $activity): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($activity['action'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($activity['details'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($activity['created_at'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="main-content">

    <div class="welcome-card">
        <h1>Integrated Digital Fleet GPS Tracking System</h1>

        <h2>
            Welcome,
            <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
        </h2>

        <p>
            Your role:
            <?php echo htmlspecialchars($_SESSION['role']); ?>
        </p>

        <hr>
<div class="stats-grid">

    <div class="stat-card">
        <h3>Total Vehicles</h3>
        <p><?php echo $totalVehicles; ?></p>
    </div>

    <div class="stat-card">
        <h3>Total Drivers</h3>
        <p><?php echo $totalDrivers; ?></p>
    </div>

    <div class="stat-card">
        <h3>Active Vehicles</h3>
        <p><?php echo $activeVehicles; ?></p>
    </div>

    <div class="stat-card">
        <h3>Total Shipments</h3>
        <p><?php echo $totalShipments; ?></p>
    </div>
<div class="stat-card">
    <h3>Delivered Shipments</h3>
    <p><?= $deliveredShipments ?></p>
</div>

<div class="stat-card">
    <h3>In Transit</h3>
    <p><?= $inTransitShipments ?></p>
</div>

<div class="stat-card">
    <h3>Pending Shipments</h3>
    <p><?= $pendingShipments ?></p>
</div>

<div class="stat-card">
    <h3>Cancelled Shipments</h3>
    <p><?= $cancelledShipments ?></p>
</div>


</div>

        <h2>Administrator Dashboard</h2>

        <p>
            Select a module from the sidebar to manage your fleet tracking system.
        </p>
    </div>

    <footer>
        <p>Integrated Digital Fleet GPS Tracking System</p>
        <p>&copy; 2026</p>
    </footer>

</div>

</body>
</html>
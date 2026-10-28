<?php
session_start();

// Prevent users who are not logged in from accessing the dashboard.
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Fleet Tracking Dashboard</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <h1>Integrated Digital Fleet GPS Tracking System</h1>

    <hr>

    <h2>
        Welcome,
        <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
    </h2>

    <p>
        Your role:
        <?php echo htmlspecialchars($_SESSION['role']); ?>
    </p>

    <hr>

    <h2>Administrator Dashboard</h2>

    <p>Select a module to manage the fleet tracking system.</p>

    <h3>System Modules</h3>

<ul>
    <li><a href="vehicles.php">Vehicle Management</a></li>
    <li><a href="drivers.php">Driver Management</a></li>
    <li><a href="gps_tracker.php">Live Fleet Tracking</a></li>
    <li><a href="shipments.php">Shipment and Delivery Monitoring</a></li>
    <li><a href="reports.php">Reports</a></li>
    <li><a href="audit_logs.php">Audit Logs</a></li>
</ul>


    <hr>

    <p>
        <a href="logout.php">Log Out</a>
    </p>

    <footer>
        <p>Integrated Digital Fleet GPS Tracking System</p>
        <p>&copy; 2026</p>
    </footer>

</body>
</html>
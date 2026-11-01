<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get filter values from the search form.
$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$userFilter = trim($_GET['user_id'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

// Build the audit log query.
$sql = "SELECT audit_id, user_id, action, details, ip_address, created_at
        FROM audit_logs
        WHERE 1=1";

$params = [];

// Search activity details and shipment references.
if ($search !== '') {
    $sql .= " AND details LIKE ?";
    $params[] = '%' . $search . '%';
}

// Filter by action.
if ($actionFilter !== '') {
    $sql .= " AND action = ?";
    $params[] = $actionFilter;
}

// Filter by user ID.
if ($userFilter !== '' && ctype_digit($userFilter)) {
    $sql .= " AND user_id = ?";
    $params[] = (int) $userFilter;
}

// Filter by start date.
if ($startDate !== '') {
    $sql .= " AND created_at >= ?";
    $params[] = $startDate . ' 00:00:00';
}

// Filter by end date.
if ($endDate !== '') {
    $sql .= " AND created_at <= ?";
    $params[] = $endDate . ' 23:59:59';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Audit Logs</title>

    <style>
    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'DM Sans', Arial, sans-serif;
        margin: 0;
        padding: 25px 20px 25px 250px;
        color: #172b4d;
        background: #edf2f8;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 220px;
        height: 100vh;
        padding: 20px 10px;
        background: linear-gradient(180deg, #10243e, #173d68);
        color: white;
        overflow-y: auto;
        z-index: 1000;
    }

    .sidebar h2 {
        text-align: center;
        margin-bottom: 30px;
        color: white;
    }

    .sidebar nav a,
    .sidebar .logout-link {
        display: block;
        color: #dce7f5;
        text-decoration: none;
        padding: 13px 12px;
        border-radius: 7px;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .sidebar nav a:hover,
    .sidebar .logout-link:hover {
        background: #28547c;
        color: white;
    }

    .sidebar .logout-link {
        margin-top: 25px;
        background: #8b2525;
    }

    .page-content {
        width: 100%;
        max-width: none;
        margin: 0;
    }

    h1 {
        color: #10243e;
        font-size: 28px;
        margin: 0 0 10px;
    }

    .page-content > p {
        color: #64748b;
        margin-bottom: 25px;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(16, 36, 62, 0.06);
    }

    table {
        border-collapse: collapse;
        width: 100%;
        min-width: 800px;
        background: white;
        font-size: 13px;
    }

    th, td {
        padding: 14px 12px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        vertical-align: top;
    }

    th {
        background: #10243e;
        color: white;
        font-weight: 700;
    }

    tbody tr:nth-child(even) {
        background: #f8fafc;
    }

    tbody tr:hover {
        background: #edf4ff;
    }

    td:nth-child(4) {
        min-width: 250px;
        white-space: normal;
    }

    @media (max-width: 700px) {
        body {
            padding: 15px;
        }

        .sidebar {
            position: relative;
            width: 100%;
            height: auto;
            margin-bottom: 20px;
        }
    }
.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: end;
    background: white;
    padding: 20px;
    margin-bottom: 25px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.filter-form div {
    flex: 1;
    min-width: 150px;
}

.filter-form label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 600;
}

.filter-form input,
.filter-form select {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
}

.filter-form button,
.reset-btn {
    padding: 11px 18px;
    border: none;
    border-radius: 7px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
}

.reset-btn {
    background: #64748b;
}
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>
<main class="page-content">


<h1>System Audit Logs</h1>

<p>
    Review actions recorded by the fleet tracking system.
</p>
<form method="GET" class="filter-form">

    <div>
        <label>Search Details</label>
        <input
            type="text"
            name="search"
            placeholder="Search shipment reference..."
            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div>
        <label>Action Type</label>
        <select name="action">
            <option value="">All Actions</option>
            <option value="SHIPMENT CREATED"
                <?= $actionFilter === 'SHIPMENT CREATED' ? 'selected' : '' ?>>
                Shipment Created
            </option>
            <option value="SHIPMENT STATUS UPDATED"
                <?= $actionFilter === 'SHIPMENT STATUS UPDATED' ? 'selected' : '' ?>>
                Shipment Status Updated
            </option>
        </select>
    </div>

    <div>
        <label>User ID</label>
        <input
            type="number"
            name="user_id"
            min="1"
            placeholder="Enter user ID"
            value="<?= htmlspecialchars($userFilter, ENT_QUOTES, 'UTF-8') ?>"
        >
    </div>

    <div>
        <label>Start Date</label>
        <input type="date" name="start_date"
            value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <div>
        <label>End Date</label>
        <input type="date" name="end_date"
            value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <button type="submit">Apply Filters</button>

    <a href="audit_logs.php" class="reset-btn">Clear Filters</a>

</form>
    
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Audit ID</th>
                <th>User ID</th>
                <th>Action</th>
                <th>Details</th>
                <th>IP Address</th>
                <th>Date and Time</th>
            </tr>
        </thead>

        <tbody>
        <?php if (empty($logs)): ?>
            <tr>
                <td colspan="6">
                    No audit records have been recorded yet.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <?= (int) $log['audit_id'] ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            (string) ($log['user_id'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            (string) $log['action'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            (string) ($log['details'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            (string) ($log['ip_address'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            (string) ($log['created_at'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
</body>
</html>



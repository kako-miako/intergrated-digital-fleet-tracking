<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->query(
    "SELECT audit_id, user_id, action, details, ip_address, created_at
     FROM audit_logs
     ORDER BY created_at DESC"
);

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Audit Logs</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #222;
        }

        h1 {
            margin-bottom: 8px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 800px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #eee;
        }

        tr:nth-child(even) {
            background: #f8f8f8;
        }
    </style>
</head>
<body>

<h1>System Audit Logs</h1>

<p>
    Review actions recorded by the fleet tracking system.
</p>

<p>
    <a href="dashboard.php">Back to Dashboard</a>
</p>

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

</body>
</html>



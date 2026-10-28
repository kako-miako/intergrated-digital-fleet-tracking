<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$status = $_GET['status'] ?? '';

$allowedStatuses = ['Pending', 'In Transit', 'Delivered', 'Cancelled'];

if (!in_array($status, array_merge([''], $allowedStatuses), true)) {
    $status = '';
}

$conditions = [];
$params = [];

if ($startDate !== '') {
    $conditions[] = 'DATE(s.created_at) >= ?';
    $params[] = $startDate;
}

if ($endDate !== '') {
    $conditions[] = 'DATE(s.created_at) <= ?';
    $params[] = $endDate;
}

if ($status !== '') {
    $conditions[] = 's.status = ?';
    $params[] = $status;
}

$sql = "SELECT s.shipment_reference, v.registration_number,
               s.destination, s.status, s.proof_of_delivery,
               s.created_at, s.delivered_at
        FROM shipments s
        LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id";

if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY s.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="shipment_report.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Shipment Reference',
        'Vehicle',
        'Destination',
        'Status',
        'Proof of Delivery',
        'Created At',
        'Delivered At'
    ]);

    foreach ($shipments as $shipment) {
        fputcsv($output, [
            $shipment['shipment_reference'],
            $shipment['registration_number'] ?? 'Unassigned',
            $shipment['destination'],
            $shipment['status'],
            $shipment['proof_of_delivery'] ?? '',
            $shipment['created_at'],
            $shipment['delivered_at'] ?? ''
        ]);
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fleet Reports</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
        .panel { border: 1px solid #ccc; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        label { display: inline-block; margin: 8px 10px 8px 0; }
        input, select, button { padding: 9px; margin-top: 5px; }
        button, .button { cursor: pointer; }
        .button { display: inline-block; padding: 10px 14px; border: 1px solid #777; text-decoration: none; color: #222; border-radius: 4px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 750px; }
        th, td { border: 1px solid #ccc; padding: 9px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>

<h1>Fleet Reports</h1>
<p>View shipment and delivery records, filter reports, and export results.</p>

<p><a href="dashboard.php">Back to Dashboard</a></p>

<section class="panel">
    <h2>Filter Report</h2>

    <form method="get">
        <label>
            Start date<br>
            <input type="date" name="start_date"
                   value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <label>
            End date<br>
            <input type="date" name="end_date"
                   value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <label>
            Shipment status<br>
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $option): ?>
                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $status === $option ? 'selected' : '' ?>>
                        <?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit">Generate Report</button>
    </form>
</section>

<section class="panel">
    <h2>Report Results</h2>

    <p>Total shipments matching your filters: <strong><?= count($shipments) ?></strong></p>

    <?php
    $exportParams = $_GET;
    $exportParams['export'] = 'csv';
    $exportUrl = 'reports.php?' . http_build_query($exportParams);
    ?>

    <p>
        <a class="button"
           href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>">
            Export to CSV
        </a>
        <button type="button" onclick="window.print()">Print Report</button>
    </p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Shipment Reference</th>
                    <th>Vehicle</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th>Proof of Delivery</th>
                    <th>Created At</th>
                    <th>Delivered At</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$shipments): ?>
                <tr><td colspan="7">No shipments match the selected filters.</td></tr>
            <?php else: ?>
                <?php foreach ($shipments as $shipment): ?>
                    <tr>
                        <td><?= htmlspecialchars($shipment['shipment_reference'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($shipment['registration_number'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($shipment['destination'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($shipment['status'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($shipment['proof_of_delivery'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($shipment['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($shipment['delivered_at'] ?? 'Not delivered', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

</body>
</html>


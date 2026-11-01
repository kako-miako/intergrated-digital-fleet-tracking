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

    h2 {
        color: #173d68;
        font-size: 20px;
        margin-top: 0;
    }

    .page-content > p {
        color: #64748b;
        margin-bottom: 25px;
    }

    .panel {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 5px 20px rgba(16, 36, 62, 0.06);
    }

    label {
        display: inline-block;
        margin: 8px 15px 12px 0;
        font-weight: 600;
        font-size: 13px;
        color: #334155;
    }

    input, select {
        min-height: 43px;
        padding: 10px 12px;
        margin-top: 7px;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        background: #f8fafc;
        color: #172b4d;
    }

    input:focus, select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    button, .button {
        display: inline-block;
        padding: 12px 18px;
        margin-top: 10px;
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 7px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    button:hover, .button:hover {
        background: #173da8;
        color: white;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
        background: white;
        border-radius: 10px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 750px;
        font-size: 13px;
    }

    th, td {
        padding: 13px 12px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
    }

    th {
        background: #10243e;
        color: white;
    }

    tbody tr:hover {
        background: #f1f5f9;
    }

    @media print {
        body {
            padding: 0;
            background: white;
        }

        .sidebar,
        .panel:first-of-type,
        .button,
        button {
            display: none !important;
        }

        .panel {
            box-shadow: none;
            border: none;
        }

        table {
            min-width: 0;
            font-size: 10px;
        }
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

        .panel {
            padding: 18px;
        }

        input, select {
            max-width: 100%;
        }
    }
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>
<main class="page-content">


<h1>Fleet Reports</h1><p>View shipment and delivery records, filter reports, and export results.</p>


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
</main>
</body>
</html>


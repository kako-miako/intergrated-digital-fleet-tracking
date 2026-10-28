<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD A NEW SHIPMENT
    if ($action === 'add') {
        $reference = trim($_POST['shipment_reference'] ?? '');
        $vehicleId = ($_POST['vehicle_id'] ?? '') !== ''
            ? (int) $_POST['vehicle_id']
            : null;
        $destination = trim($_POST['destination'] ?? '');

        if ($reference === '' || $destination === '') {
            $error = 'Shipment reference and destination are required.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO shipments
                     (shipment_reference, vehicle_id, destination, status)
                     VALUES (?, ?, ?, 'Pending')"
                );

                $stmt->execute([
                    $reference,
                    $vehicleId,
                    $destination
                ]);

                $shipmentId = (int) $pdo->lastInsertId();

                // Record shipment creation in the audit log.
                $log = $pdo->prepare(
                    "INSERT INTO audit_logs
                     (user_id, action, details, ip_address)
                     VALUES (?, ?, ?, ?)"
                );

                $log->execute([
                    $_SESSION['user_id'],
                    'SHIPMENT CREATED',
                    "Shipment {$reference} (ID {$shipmentId}) was created.",
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

                $pdo->commit();
                $message = 'Shipment added successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Shipment creation failed: ' . $e->getMessage());
                $error = 'Could not add shipment. Check that the reference is unique and the vehicle is valid.';
            }
        }

    // UPDATE SHIPMENT STATUS
    } elseif ($action === 'update') {
        $shipmentId = (int) ($_POST['shipment_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $proof = trim($_POST['proof_of_delivery'] ?? '');

        $allowedStatuses = [
            'Pending',
            'In Transit',
            'Delivered',
            'Cancelled'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $error = 'Invalid shipment status.';
        } elseif ($status === 'Delivered' && $proof === '') {
            $error = 'Enter a proof-of-delivery reference before marking a shipment as delivered.';
        } else {
            try {
                $pdo->beginTransaction();

                // Retrieve the current shipment status.
                $find = $pdo->prepare(
                    "SELECT shipment_reference, status, delivered_at
                     FROM shipments
                     WHERE shipment_id = ?
                     FOR UPDATE"
                );

                $find->execute([$shipmentId]);
                $existing = $find->fetch(PDO::FETCH_ASSOC);

                if (!$existing) {
                    throw new RuntimeException('Shipment not found.');
                }

                $oldStatus = $existing['status'];
                $reference = $existing['shipment_reference'];

                // Update shipment details.
                $stmt = $pdo->prepare(
                    "UPDATE shipments
                     SET status = ?,
                         proof_of_delivery = ?,
                         delivered_at = CASE
                             WHEN ? = 'Delivered'
                                 THEN COALESCE(delivered_at, NOW())
                             ELSE NULL
                         END
                     WHERE shipment_id = ?"
                );

                $stmt->execute([
                    $status,
                    $proof !== '' ? $proof : null,
                    $status,
                    $shipmentId
                ]);

                // Record the status change in the audit log.
                $log = $pdo->prepare(
                    "INSERT INTO audit_logs
                     (user_id, action, details, ip_address)
                     VALUES (?, ?, ?, ?)"
                );

                $details = sprintf(
                    'Shipment %s (ID %d): status changed from %s to %s.',
                    $reference,
                    $shipmentId,
                    $oldStatus,
                    $status
                );

                $log->execute([
                    $_SESSION['user_id'],
                    'SHIPMENT STATUS UPDATED',
                    $details,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

                $pdo->commit();
                $message = 'Shipment updated and audit log recorded.';

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Shipment update failed: ' . $e->getMessage());
                $error = 'Could not update the shipment or record its audit log. Please check the details and try again.';
            }
        }
    }
}

// Retrieve vehicles for the shipment form.
$vehicles = $pdo->query(
    "SELECT vehicle_id, registration_number
     FROM vehicles
     ORDER BY registration_number"
)->fetchAll(PDO::FETCH_ASSOC);

// Retrieve shipment records.
$shipments = $pdo->query(
    "SELECT s.shipment_id,
            s.shipment_reference,
            s.vehicle_id,
            s.destination,
            s.status,
            s.proof_of_delivery,
            s.delivered_at,
            s.created_at,
            v.registration_number
     FROM shipments s
     LEFT JOIN vehicles v ON s.vehicle_id = v.vehicle_id
     ORDER BY s.shipment_id DESC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shipment and Delivery Monitoring</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #222;
        }

        h1, h2 {
            margin-bottom: 12px;
        }

        .panel {
            border: 1px solid #ccc;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-top: 12px;
        }

        input, select, button {
            padding: 9px;
            margin-top: 5px;
            max-width: 100%;
        }

        input, select {
            box-sizing: border-box;
            width: 100%;
        }

        button {
            cursor: pointer;
        }

        .success {
            background: #e8f5e9;
            padding: 10px;
        }

        .error {
            background: #ffebee;
            padding: 10px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 850px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 9px;
            text-align: left;
        }

        th {
            background: #eee;
        }

        .update-form {
            min-width: 190px;
        }

        .update-form button {
            width: 100%;
        }
    </style>
</head>

<body>

<h1>Shipment and Delivery Monitoring</h1>

<p>
    Register shipments, assign vehicles, track delivery status,
    and record proof of delivery.
</p>

<p><a href="dashboard.php">Back to Dashboard</a></p>

<?php if ($message !== ''): ?>
    <p class="success">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p class="error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php endif; ?>

<section class="panel">
    <h2>Add New Shipment</h2>

    <form method="post">
        <input type="hidden" name="action" value="add">

        <label for="shipment_reference">Shipment Reference</label>
        <input
            id="shipment_reference"
            name="shipment_reference"
            maxlength="50"
            required
        >

        <label for="vehicle_id">Assigned Vehicle</label>
        <select id="vehicle_id" name="vehicle_id">
            <option value="">Unassigned</option>

            <?php foreach ($vehicles as $vehicle): ?>
                <option value="<?= (int) $vehicle['vehicle_id'] ?>">
                    <?= htmlspecialchars(
                        $vehicle['registration_number'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="destination">Destination</label>
        <input
            id="destination"
            name="destination"
            maxlength="150"
            required
        >

        <button type="submit">Add Shipment</button>
    </form>
</section>

<section>
    <h2>Shipment Records</h2>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Vehicle</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th>Proof of Delivery</th>
                    <th>Delivered At</th>
                    <th>Update Shipment</th>
                </tr>
            </thead>

            <tbody>
            <?php if (!$shipments): ?>
                <tr>
                    <td colspan="7">
                        No shipments have been registered yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($shipments as $shipment): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars(
                                $shipment['shipment_reference'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $shipment['registration_number'] ?? 'Unassigned',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $shipment['destination'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $shipment['status'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $shipment['proof_of_delivery'] ?? 'Not provided',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $shipment['delivered_at'] ?? 'Not delivered',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <form method="post" class="update-form">
                                <input
                                    type="hidden"
                                    name="action"
                                    value="update"
                                >

                                <input
                                    type="hidden"
                                    name="shipment_id"
                                    value="<?= (int) $shipment['shipment_id'] ?>"
                                >

                                <label>Status</label>

                                <select name="status" required>
                                    <?php foreach (
                                        ['Pending', 'In Transit', 'Delivered', 'Cancelled']
                                        as $statusOption
                                    ): ?>
                                        <option
                                            value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $shipment['status'] === $statusOption ? 'selected' : '' ?>
                                        >
                                            <?= htmlspecialchars(
                                                $statusOption,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label>Proof-of-delivery reference</label>

                                <input
                                    name="proof_of_delivery"
                                    maxlength="255"
                                    value="<?= htmlspecialchars(
                                        $shipment['proof_of_delivery'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    placeholder="e.g. POD-001"
                                >

                                <button type="submit">Save Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

</body>
</html>


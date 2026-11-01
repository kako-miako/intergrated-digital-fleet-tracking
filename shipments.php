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

               // Record an audit entry only when the status changes.
if ($oldStatus !== $status) {

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
}

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
    <title>Shipment and Delivery Monitoring</title><style>
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
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 5px 20px rgba(16, 36, 62, 0.06);
    }

    label {
        display: block;
        margin-top: 15px;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 13px;
        color: #334155;
    }

    input, select {
        display: block;
        width: 100%;
        min-height: 43px;
        padding: 10px 12px;
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

    button {
        padding: 12px 18px;
        margin-top: 18px;
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 7px;
        font-weight: 700;
        cursor: pointer;
    }

    button:hover {
        background: #173da8;
    }

    .success, .error {
        padding: 12px 15px;
        border-radius: 7px;
        margin-bottom: 15px;
    }

    .success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }

    .error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .table-wrap {
        width: 100%;
        overflow-x: auto;
        background: white;
        border-radius: 10px;
    }

    table {
        border-collapse: collapse;
        width: 100%;
        min-width: 850px;
        background: white;
        font-size: 13px;
    }

    th, td {
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        vertical-align: top;
    }

    th {
        background: #10243e;
        color: white;
    }

    tbody tr:hover {
        background: #f1f5f9;
    }

    .update-form {
        min-width: 190px;
    }

    .update-form button {
        width: 100%;
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
    }
</style>
</head>

<body>

<?php include __DIR__ . '/sidebar.php'; ?>
<main class="page-content">


<h1>Shipment and Delivery Monitoring</h1>
<p>
    Register shipments, assign vehicles, track delivery status,
    and record proof of delivery.
</p>


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
</main>
</body>
</html>


<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

require __DIR__ . '/db.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registration = strtoupper(trim($_POST['registration_number'] ?? ''));
    $makeModel = trim($_POST['make_model'] ?? '');
    $vehicleType = trim($_POST['vehicle_type'] ?? '');
    $gpsDeviceId = trim($_POST['gps_device_id'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    $allowedTypes = ['Car', 'Van', 'Truck', 'Bus', 'Motorcycle', 'Other'];
    $allowedStatuses = ['Active', 'Inactive'];

    if (
        $registration === '' ||
        $makeModel === '' ||
        !in_array($vehicleType, $allowedTypes, true) ||
        !in_array($status, $allowedStatuses, true)
    ) {
        $message = 'Please complete all required fields correctly.';
        $messageType = 'error';
    } else {
        try {
            $sql = "INSERT INTO vehicles
                    (registration_number, make_model, vehicle_type, gps_device_id, status)
                    VALUES (:registration, :make_model, :vehicle_type, :gps_device_id, :status)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':registration' => $registration,
                ':make_model' => $makeModel,
                ':vehicle_type' => $vehicleType,
                ':gps_device_id' => $gpsDeviceId !== '' ? $gpsDeviceId : null,
                ':status' => $status
            ]);

            header('Location: vehicles.php?success=1');
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());

            if ($e->getCode() === '23000') {
                $message = 'That registration number or GPS device ID is already registered.';
            } else {
                $message = 'The vehicle could not be saved. Check the database configuration.';
            }

            $messageType = 'error';
        }
    }
}

if (isset($_GET['success'])) {
    $message = 'Vehicle registered successfully!';
    $messageType = 'success';
}

$stmt = $pdo->query(
    "SELECT vehicle_id, registration_number, make_model, vehicle_type,
            gps_device_id, status, created_at
     FROM vehicles
     ORDER BY vehicle_id DESC"
);

$vehicles = $stmt->fetchAll();

function escapeHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Management</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
<style>
    body {
        margin: 0;
        padding: 25px 25px 25px 265px;
        background: #edf2f8;
        color: #172b4d;
        font-family: 'DM Sans', Arial, sans-serif;
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
        box-sizing: border-box;
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

.container {
    width: 100%;
    max-width: none;
    margin: 0;
    box-sizing: border-box;
}

    .container > h1 {
        color: #10243e;
        font-size: 28px;
        margin-bottom: 8px;
    }

    .container > p {
        color: #64748b;
        margin-bottom: 25px;
    }

    .panel {
        background: #ffffff;
        padding: 25px;
        margin-bottom: 25px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(16,36,62,.06);
    }

    .panel h2 {
        color: #173d68;
        font-size: 20px;
        margin-top: 0;
        margin-bottom: 22px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 13px;
        color: #334155;
    }

    input, select {
        width: 100%;
        min-height: 45px;
        padding: 10px 12px;
        box-sizing: border-box;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        background: #f8fafc;
        color: #172b4d;
        outline: none;
    }

    input:focus, select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,.12);
        background: white;
    }

    button {
        display: inline-block;
        padding: 12px 20px;
        margin-top: 20px;
        border: none;
        border-radius: 7px;
        background: #2563eb;
        color: white;
        font-weight: 700;
        cursor: pointer;
        transition: background .2s;
    }

    button:hover {
        background: #173da8;
    }

    .table-wrap {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        font-size: 13px;
    }

    th, td {
        border-bottom: 1px solid #e2e8f0;
        padding: 13px 12px;
        text-align: left;
        white-space: nowrap;
    }

    th {
        background: #10243e;
        color: white;
        font-weight: 600;
    }

    tbody tr:hover {
        background: #f1f5f9;
    }

    .success {
        color: #166534;
        background: #dcfce7;
        border: 1px solid #bbf7d0;
        padding: 12px;
        border-radius: 7px;
    }

    .error {
        color: #991b1b;
        background: #fee2e2;
        border: 1px solid #fecaca;
        padding: 12px;
        border-radius: 7px;
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
/* Fix content being hidden behind the sidebar */
body {
    padding-left: 250px !important;
    padding-right: 20px !important;
    box-sizing: border-box;
}

.container {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    box-sizing: border-box;
}

.panel {
    width: 100%;
    box-sizing: border-box;
}

@media (max-width: 700px) {
    body {
        padding: 15px !important;
    }

    .sidebar {
        position: relative !important;
        width: 100% !important;
        height: auto !important;
    }
}
</style>
</head>

<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="container">

    <h1>Vehicle Management</h1>
    <p>Register and manage vehicles in your fleet.</p>

    
    <?php if ($message !== ''): ?>
        <p class="<?= escapeHtml($messageType) ?>">
            <?= escapeHtml($message) ?>
        </p>
    <?php endif; ?>

    <section class="panel">
        <h2>Register a Vehicle</h2>

        <form action="vehicles.php" method="POST">
            <div class="form-grid">

                <div>
                    <label for="registration_number">Registration Number *</label>
                    <input
                        type="text"
                        id="registration_number"
                        name="registration_number"
                        maxlength="20"
                        required
                    >
                </div>

                <div>
                    <label for="make_model">Make and Model *</label>
                    <input
                        type="text"
                        id="make_model"
                        name="make_model"
                        maxlength="100"
                        placeholder="e.g. Toyota Hiace"
                        required
                    >
                </div>

                <div>
                    <label for="vehicle_type">Vehicle Type *</label>
                    <select id="vehicle_type" name="vehicle_type" required>
                        <option value="">Select vehicle type</option>
                        <option value="Car">Car</option>
                        <option value="Van">Van</option>
                        <option value="Truck">Truck</option>
                        <option value="Bus">Bus</option>
                        <option value="Motorcycle">Motorcycle</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label for="gps_device_id">GPS Device ID</label>
                    <input
                        type="text"
                        id="gps_device_id"
                        name="gps_device_id"
                        maxlength="100"
                        placeholder="Optional"
                    >
                </div>

                <div>
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

            </div>

            <button type="submit">Register Vehicle</button>
        </form>
    </section>

    <section class="panel">
        <h2>Registered Vehicles</h2>

        <?php if (count($vehicles) === 0): ?>
            <p>No vehicles have been registered yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Registration</th>
                            <th>Make and Model</th>
                            <th>Type</th>
                            <th>GPS Device ID</th>
                            <th>Status</th>
                            <th>Date Added</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <tr>
                            <td><?= escapeHtml($vehicle['vehicle_id']) ?></td>
                            <td><?= escapeHtml($vehicle['registration_number']) ?></td>
                            <td><?= escapeHtml($vehicle['make_model']) ?></td>
                            <td><?= escapeHtml($vehicle['vehicle_type']) ?></td>
                            <td><?= escapeHtml($vehicle['gps_device_id'] ?? 'Not assigned') ?></td>
                            <td><?= escapeHtml($vehicle['status']) ?></td>
                            <td><?= escapeHtml($vehicle['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>
</body>
</html>
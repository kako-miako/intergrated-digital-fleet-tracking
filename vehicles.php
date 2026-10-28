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
        body {
            font-family: Arial, sans-serif;
            margin: 25px;
            background: #f4f4f4;
            color: #222;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .panel {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #bbb;
            border-radius: 4px;
        }

        button, .back-link {
            display: inline-block;
            padding: 10px 16px;
            margin-top: 15px;
            border: none;
            border-radius: 4px;
            background: #222;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #222;
            color: white;
        }

        .success {
            color: #176b2c;
            background: #e7f6e9;
            padding: 12px;
        }

        .error {
            color: #a00000;
            background: #fdeaea;
            padding: 12px;
        }
    </style>
</head>

<body>
<div class="container">

    <h1>Vehicle Management</h1>
    <p>Register and manage vehicles in your fleet.</p>

    <a class="back-link" href="dashboard.php">Back to Dashboard</a>

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
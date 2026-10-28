<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

require_once "db.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $vehicle_id = filter_input(INPUT_POST, "vehicle_id", FILTER_VALIDATE_INT);
    $driver_id = filter_input(INPUT_POST, "driver_id", FILTER_VALIDATE_INT);
    $destination = trim($_POST["destination"] ?? "");
    $start_location = trim($_POST["start_location"] ?? "");
    $status = $_POST["status"] ?? "Planned";

    if (!$vehicle_id || !$driver_id || $destination === "" || $start_location === "") {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif (!in_array($status, ["Planned", "In Progress", "Completed"], true)) {
        $message = "Invalid trip status.";
        $messageType = "error";
    } else {
        try {
            // Confirm that the selected vehicle exists.
            $vehicleCheck = $pdo->prepare(
                "SELECT vehicle_id FROM vehicles WHERE vehicle_id = ?"
            );
            $vehicleCheck->execute([$vehicle_id]);

            // Confirm that the selected driver exists.
            $driverCheck = $pdo->prepare(
                "SELECT driver_id FROM drivers WHERE driver_id = ?"
            );
            $driverCheck->execute([$driver_id]);

            if (!$vehicleCheck->fetch() || !$driverCheck->fetch()) {
                $message = "Please select a valid registered vehicle and driver.";
                $messageType = "error";
            } else {
                $sql = "INSERT INTO trips
        (vehicle_id, driver_id, origin, destination, status)
        VALUES
        (:vehicle_id, :driver_id, :origin, :destination, :status)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ":vehicle_id" => $vehicle_id,
                    ":driver_id" => $driver_id,
                    ":origin" => $start_location,
                    ":destination" => $destination,
                    ":status" => $status
                ]);

                header("Location: trips.php?saved=1");
                exit;
            }
       } catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
    }
}

if (isset($_GET["saved"])) {
    $message = "Trip created successfully!";
    $messageType = "success";
}

$vehicles = $pdo->query(
    "SELECT vehicle_id, registration_number, make_model
     FROM vehicles
     ORDER BY registration_number"
)->fetchAll();

$drivers = $pdo->query(
    "SELECT driver_id, full_name, licence_number
     FROM drivers
     ORDER BY full_name"
)->fetchAll();

$sql = "SELECT t.*, v.registration_number, v.make_model,
               d.full_name AS driver_name
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.vehicle_id
        JOIN drivers d ON t.driver_id = d.driver_id
        ORDER BY t.trip_id DESC";

$trips = $pdo->query($sql)->fetchAll();

function escapeHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip Management</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f4f7;
            margin: 0;
            padding: 25px;
            color: #222;
        }

        .container {
            max-width: 1150px;
            margin: auto;
        }

        .header, .card {
            background: white;
            padding: 22px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        h1, h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin: 14px 0 6px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            margin-top: 18px;
            padding: 11px 18px;
            background: #174a7e;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background: #103557;
        }

        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .success {
            background: #dff5e5;
            color: #17652c;
        }

        .error {
            background: #fde2e2;
            color: #8b1e1e;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #174a7e;
            color: white;
        }

        tr:nth-child(even) {
            background: #f7f7f7;
        }

        .back-link {
            color: #174a7e;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h1>Trip Management</h1>
        <p>Create trips by selecting registered vehicles and drivers.</p>
        <a class="back-link" href="dashboard.php">← Back to Dashboard</a>
    </div>

    <div class="card">
        <h2>Create a Trip</h2>

        <?php if ($message !== ""): ?>
            <div class="message <?= escapeHtml($messageType) ?>">
                <?= escapeHtml($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="trips.php">
            <label for="vehicle_id">Vehicle *</label>
            <select id="vehicle_id" name="vehicle_id" required>
                <option value="">Select a vehicle</option>
                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?= escapeHtml($vehicle["vehicle_id"]) ?>">
                        <?= escapeHtml($vehicle["registration_number"]) ?>
                        — <?= escapeHtml($vehicle["make_model"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="driver_id">Driver *</label>
            <select id="driver_id" name="driver_id" required>
                <option value="">Select a driver</option>
                <?php foreach ($drivers as $driver): ?>
                    <option value="<?= escapeHtml($driver["driver_id"]) ?>">
                        <?= escapeHtml($driver["full_name"]) ?>
                        — Licence: <?= escapeHtml($driver["licence_number"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="start_location">Starting Location *</label>
            <input type="text" id="start_location" name="start_location"
                   maxlength="255" required>

            <label for="destination">Destination *</label>
            <input type="text" id="destination" name="destination"
                   maxlength="255" required>

            <label for="status">Trip Status *</label>
            <select id="status" name="status" required>
                <option value="Planned">Planned</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
            </select>

            <button type="submit">Create Trip</button>
        </form>
    </div>

    <div class="card">
        <h2>Registered Trips</h2>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Trip ID</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Starting Location</th>
                        <th>Destination</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($trips) > 0): ?>
                    <?php foreach ($trips as $trip): ?>
                        <tr>
                            <td><?= escapeHtml($trip["trip_id"]) ?></td>
                            <td><?= escapeHtml($trip["registration_number"]) ?></td>
                            <td><?= escapeHtml($trip["driver_name"]) ?></td>
                            <td><?= escapeHtml($trip["origin"]) ?></td>
                            <td><?= escapeHtml($trip["destination"]) ?></td>
                            <td><?= escapeHtml($trip["status"]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No trips created yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>


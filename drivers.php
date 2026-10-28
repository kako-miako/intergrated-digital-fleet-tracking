<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.html");
    exit;
}

require_once "db.php";

$message = "";
$messageType = "";

function escapeHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// Load vehicles for the assignment dropdown.
$vehicleStmt = $pdo->query(
    "SELECT vehicle_id, registration_number, make_model
     FROM vehicles
     ORDER BY registration_number ASC"
);
$vehicles = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $licence_number = trim($_POST["licence_number"] ?? "");
    $status = $_POST["status"] ?? "Active";
    $vehicle_id = $_POST["vehicle_id"] ?? "";

    if ($full_name === "" || $phone === "" || $licence_number === "") {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif (!in_array($status, ["Active", "Inactive"], true)) {
        $message = "Invalid driver status.";
        $messageType = "error";
    } elseif (
        $vehicle_id !== "" &&
        (!ctype_digit((string)$vehicle_id) || (int)$vehicle_id < 1)
    ) {
        $message = "Please select a valid vehicle.";
        $messageType = "error";
    } else {
        try {
            // Confirm that the selected vehicle exists.
            if ($vehicle_id !== "") {
                $checkVehicle = $pdo->prepare(
                    "SELECT vehicle_id FROM vehicles WHERE vehicle_id = :vehicle_id"
                );
                $checkVehicle->execute([
                    ":vehicle_id" => (int)$vehicle_id
                ]);

                if (!$checkVehicle->fetch()) {
                    throw new InvalidArgumentException(
                        "The selected vehicle does not exist."
                    );
                }
            }

            $sql = "INSERT INTO drivers
                    (full_name, phone, licence_number, status, vehicle_id)
                    VALUES
                    (:full_name, :phone, :licence_number, :status, :vehicle_id)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":full_name" => $full_name,
                ":phone" => $phone,
                ":licence_number" => $licence_number,
                ":status" => $status,
                ":vehicle_id" => $vehicle_id === ""
                    ? null
                    : (int)$vehicle_id
            ]);

            header("Location: drivers.php?saved=1");
            exit;

        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            $messageType = "error";
        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $message = "That licence number may already be registered, or the vehicle assignment is invalid.";
            } else {
                error_log($e->getMessage());
                $message = "Unable to register the driver. Please check the database.";
            }
            $messageType = "error";
        }
    }
}

if (isset($_GET["saved"])) {
    $message = "Driver registered successfully!";
    $messageType = "success";
}

// Show drivers together with their assigned vehicle.
$stmt = $pdo->query(
    "SELECT
        d.driver_id,
        d.full_name,
        d.phone,
        d.licence_number,
        d.status,
        d.created_at,
        d.vehicle_id,
        v.registration_number,
        v.make_model
     FROM drivers d
     LEFT JOIN vehicles v ON d.vehicle_id = v.vehicle_id
     ORDER BY d.driver_id DESC"
);

$drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Management</title>

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

        .hint {
            color: #555;
            font-size: 13px;
            margin-top: 6px;
        }
    </style>
</head>

<body>
<div class="container">

    <div class="header">
        <h1>Driver Management</h1>
        <p>Register drivers and assign vehicles to them.</p>
        <a class="back-link" href="dashboard.php">← Back to Dashboard</a>
    </div>

    <div class="card">
        <h2>Register a Driver</h2>

        <?php if ($message !== ""): ?>
            <div class="message <?= escapeHtml($messageType) ?>">
                <?= escapeHtml($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="drivers.php">
            <label for="full_name">Full Name *</label>
            <input type="text" id="full_name" name="full_name"
                   maxlength="150" required>

            <label for="phone">Phone Number *</label>
            <input type="tel" id="phone" name="phone"
                   maxlength="30" required>

            <label for="licence_number">Driving Licence Number *</label>
            <input type="text" id="licence_number" name="licence_number"
                   maxlength="100" required>

            <label for="vehicle_id">Assigned Vehicle</label>
            <select id="vehicle_id" name="vehicle_id">
                <option value="">-- No vehicle assigned --</option>

                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?= escapeHtml($vehicle["vehicle_id"]) ?>">
                        <?= escapeHtml($vehicle["registration_number"]) ?>
                        - <?= escapeHtml($vehicle["make_model"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <p class="hint">
                Select a registered vehicle, or leave this blank if the driver
                has not yet been assigned one.
            </p>

            <label for="status">Status *</label>
            <select id="status" name="status" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>

            <button type="submit">Register Driver</button>
        </form>
    </div>

    <div class="card">
        <h2>Registered Drivers</h2>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>Licence Number</th>
                        <th>Assigned Vehicle</th>
                        <th>Status</th>
                        <th>Date Registered</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (count($drivers) > 0): ?>
                    <?php foreach ($drivers as $driver): ?>
                        <tr>
                            <td><?= escapeHtml($driver["driver_id"]) ?></td>
                            <td><?= escapeHtml($driver["full_name"]) ?></td>
                            <td><?= escapeHtml($driver["phone"]) ?></td>
                            <td><?= escapeHtml($driver["licence_number"]) ?></td>
                            <td>
                                <?php if (!empty($driver["vehicle_id"])): ?>
                                    <?= escapeHtml($driver["registration_number"]) ?>
                                    - <?= escapeHtml($driver["make_model"]) ?>
                                <?php else: ?>
                                    Not assigned
                                <?php endif; ?>
                            </td>
                            <td><?= escapeHtml($driver["status"]) ?></td>
                            <td><?= escapeHtml($driver["created_at"]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No drivers registered yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>


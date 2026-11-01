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

// Load only vehicles that have not already been assigned to another driver.
$vehicleStmt = $pdo->query(
    "SELECT v.vehicle_id, v.registration_number, v.make_model
     FROM vehicles v
     WHERE NOT EXISTS (
         SELECT 1
         FROM drivers d
         WHERE d.vehicle_id = v.vehicle_id
     )
     ORDER BY v.registration_number ASC"
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
} elseif (!preg_match('/^(0[17][0-9]{8}|254[17][0-9]{8})$/', $phone)) {
    $message = "Enter a valid Kenyan phone number (e.g. 0712345678).";
    $messageType = "error";
} elseif (!in_array($status, ["Active", "Inactive"], true)) {        $message = "Please select a valid vehicle.";
        $messageType = "error";
    } else {
        try {
            // Confirm that the selected vehicle exists.
            if ($vehicle_id !== "") {

                 // Check whether the vehicle is already assigned to another driver.
$checkAssignment = $pdo->prepare(
    "SELECT driver_id
     FROM drivers
     WHERE vehicle_id = :vehicle_id
     LIMIT 1"
);

$checkAssignment->execute([
    ":vehicle_id" => (int)$vehicle_id
]);

if ($checkAssignment->fetch()) {
    throw new InvalidArgumentException(
        "This vehicle is already assigned to another driver."
    );
}
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
    * {
        box-sizing: border-box;
    }

    body {
        font-family: 'DM Sans', Arial, sans-serif;
        background: #edf2f8;
        color: #172b4d;
        margin: 0;
        padding: 25px 20px 25px 250px;
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

    .container {
        width: 100%;
        max-width: none;
        margin: 0;
    }

    .header,
    .card {
        background: white;
        padding: 25px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 5px 20px rgba(16, 36, 62, 0.06);
    }

    h1 {
        color: #10243e;
        font-size: 28px;
        margin-top: 0;
        margin-bottom: 8px;
    }

    h2 {
        color: #173d68;
        font-size: 20px;
        margin-top: 0;
        margin-bottom: 20px;
    }

    .header p {
        color: #64748b;
        margin-bottom: 0;
    }

    label {
        display: block;
        margin: 14px 0 8px;
        font-weight: 600;
        font-size: 13px;
        color: #334155;
    }

    input,
    select {
        width: 100%;
        min-height: 45px;
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 7px;
        background: #f8fafc;
        color: #172b4d;
        outline: none;
    }

    input:focus,
    select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        background: white;
    }

    button {
        margin-top: 20px;
        padding: 12px 20px;
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

    .message {
        padding: 12px;
        margin-bottom: 15px;
        border-radius: 7px;
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

    .table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        font-size: 13px;
    }

    th,
    td {
        padding: 13px 12px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        white-space: nowrap;
    }

    th {
        background: #10243e;
        color: white;
    }

    tbody tr:hover {
        background: #f1f5f9;
    }

    .hint {
        color: #64748b;
        font-size: 13px;
        margin-top: 7px;
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

        .header,
        .card {
            padding: 18px;
        }
    }
</style>
</head>

<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="container">

    <div class="header">
        <h1>Driver Management</h1>
        <p>Register drivers and assign vehicles to them.</p>

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
       maxlength="12"
       pattern="(0[17][0-9]{8}|254[17][0-9]{8})"
       inputmode="numeric"
       oninput="this.value = this.value.replace(/[^0-9]/g, '')"
       placeholder="0712345678"
       title="Enter a valid Kenyan phone number"
       required>

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


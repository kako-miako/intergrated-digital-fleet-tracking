<?php
session_start();
header('Content-Type: application/json');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'POST requests only.'
    ]);
    exit;
}

$vehicleId = filter_input(INPUT_POST, 'vehicle_id', FILTER_VALIDATE_INT);
$latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
$longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

if (
    !$vehicleId ||
    $latitude === false || $latitude === null ||
    $longitude === false || $longitude === null ||
    $latitude < -90 || $latitude > 90 ||
    $longitude < -180 || $longitude > 180
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid vehicle ID or GPS coordinates.'
    ]);
    exit;
}

try {
    $check = $pdo->prepare(
        "SELECT vehicle_id FROM vehicles
         WHERE vehicle_id = :vehicle_id AND status = 'Active'"
    );
    $check->execute(['vehicle_id' => $vehicleId]);

    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Active vehicle not found.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO gps_locations
         (vehicle_id, latitude, longitude)
         VALUES (:vehicle_id, :latitude, :longitude)"
    );

    $stmt->execute([
        'vehicle_id' => $vehicleId,
        'latitude' => $latitude,
        'longitude' => $longitude
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'GPS location saved successfully.'
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save GPS location.'
    ]);
}
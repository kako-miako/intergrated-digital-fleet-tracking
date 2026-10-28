<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please log in again.'
    ]);
    exit;
}

require_once 'db.php';

try {
    $stmt = $pdo->query(
        "SELECT
            v.vehicle_id,
            v.registration_number,
            v.make_model,
            g.latitude,
            g.longitude,
            g.recorded_at
         FROM vehicles v
         LEFT JOIN gps_locations g
            ON g.location_id = (
                SELECT MAX(g2.location_id)
                FROM gps_locations g2
                WHERE g2.vehicle_id = v.vehicle_id
            )
         WHERE v.status = 'Active'
         ORDER BY v.registration_number"
    );

    echo json_encode([
        'success' => true,
        'vehicles' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to retrieve vehicle locations.'
    ]);
}
?>

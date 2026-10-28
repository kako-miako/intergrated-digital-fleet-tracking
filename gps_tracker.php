<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}

require_once 'db.php';

$stmt = $pdo->query(
    "SELECT vehicle_id, registration_number, make_model
     FROM vehicles
     WHERE status = 'Active'
     ORDER BY registration_number"
);
$vehicles = $stmt->fetchAll();

$locationStmt = $pdo->query(
"SELECT v.vehicle_id,
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

$latestLocations = $locationStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GPS Tracker</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 30px auto; padding: 15px; }
        label, select, button { display: block; width: 100%; margin-top: 12px; box-sizing: border-box; }
        select, button { padding: 12px; }
        button { cursor: pointer; }
        #result { margin-top: 20px; padding: 12px; background: #f1f1f1; }
    </style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

</head>
<body>
    <h1>Real GPS Tracker</h1>
<h2>Live Vehicle Map</h2>

<div id="map" style="height: 400px; width: 100%; border: 1px solid #ccc; border-radius: 8px;"></div>

<p id="mapStatus">Loading map...</p>

<h2>Live Fleet Locations</h2>

<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>Vehicle</th>
            <th>Latitude</th>
            <th>Longitude</th>
            <th>Last Update</th>
        </tr>
    </thead>
    <tbody id="locationsBody">
        <?php foreach ($latestLocations as $location): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($location['registration_number']) ?>
                </td>
                <td>
                    <?= $location['latitude'] !== null
                        ? htmlspecialchars((string)$location['latitude'])
                        : 'No location yet' ?>
                </td>
                <td>
                    <?= $location['longitude'] !== null
                        ? htmlspecialchars((string)$location['longitude'])
                        : 'No location yet' ?>
                </td>
                <td>
                    <?= $location['recorded_at'] !== null
                        ? htmlspecialchars($location['recorded_at'])
                        : 'No update yet' ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

    <p>This page uses your phone's GPS to obtain your actual location.</p>

    <label for="vehicle">Select vehicle</label>
    <select id="vehicle">
        <?php foreach ($vehicles as $vehicle): ?>
            <option value="<?= (int)$vehicle['vehicle_id'] ?>">
                <?= htmlspecialchars($vehicle['registration_number'] . ' — ' . $vehicle['make_model']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button id="start">Start GPS Tracking</button>
    <button id="stop" disabled>Stop GPS Tracking</button>

    <div id="result" role="status" aria-live="polite">
        GPS tracking has not started.
    </div>

    <script>
    let watchId = null;
    let sending = false;

    const result = document.getElementById('result');
    const startButton = document.getElementById('start');
    const stopButton = document.getElementById('stop');
    const vehicleSelect = document.getElementById('vehicle');

    function sendLocation(position) {
        if (sending) return;
        sending = true;

        const data = new URLSearchParams();
        data.append('vehicle_id', vehicleSelect.value);
        data.append('latitude', position.coords.latitude);
        data.append('longitude', position.coords.longitude);

        fetch('gps_receiver.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: data.toString()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                result.textContent =
                    'Location saved! Latitude: ' +
                    position.coords.latitude.toFixed(6) +
                    ', Longitude: ' +
                    position.coords.longitude.toFixed(6);
            } else {
                result.textContent = 'Server: ' + data.message;
            }
        })
        .catch((error) => {
result.textContent = 'Connection error: ' + error.message;
console.error('GPS request error:', error);
})

        .finally(() => {
            sending = false;
        });
    }

    function gpsError(error) {
        const messages = {
            1: 'Location permission was denied. Allow location access in your browser.',
            2: 'Your phone could not determine its location. Try moving outdoors.',
            3: 'Getting the location timed out. Please try again.'
        };
        result.textContent = messages[error.code] || 'Unable to get GPS location.';
    }

    startButton.addEventListener('click', () => {
        if (!navigator.geolocation) {
            result.textContent = 'This browser does not support location services.';
            return;
        }

        if (!vehicleSelect.value) {
            result.textContent = 'No active vehicle is available.';
            return;
        }

        result.textContent = 'Requesting your phone location. Please allow permission.';
        watchId = navigator.geolocation.watchPosition(
            sendLocation,
            gpsError,
            {enableHighAccuracy: true, maximumAge: 5000, timeout: 20000}
        );

        startButton.disabled = true;
        stopButton.disabled = false;
    });

    stopButton.addEventListener('click', () => {
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        startButton.disabled = false;
        stopButton.disabled = true;
        result.textContent = 'GPS tracking stopped.';
    });
    </script>
<script>
async function refreshVehicleLocations() {
    try {
        const response = await fetch('get_latest_locations.php', {
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Could not retrieve locations.');
        }

        const tbody = document.getElementById('locationsBody');

        tbody.replaceChildren();

        data.vehicles.forEach(vehicle => {
            const row = document.createElement('tr');

            const values = [
                vehicle.registration_number,
                vehicle.latitude !== null ? vehicle.latitude : 'No location yet',
                vehicle.longitude !== null ? vehicle.longitude : 'No location yet',
                vehicle.recorded_at !== null ? vehicle.recorded_at : 'No update yet'
            ];

            values.forEach(value => {
                const cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });

            tbody.appendChild(row);
        });

    } catch (error) {
        console.error('Location refresh error:', error);
    }
}

refreshVehicleLocations();
setInterval(refreshVehicleLocations, 5000);
</script>
<script>
const mapStatus = document.getElementById('mapStatus');

const map = L.map('map').setView([-1.286389, 36.817223], 6);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

const vehicleMarkers = new Map();

async function refreshVehicleMap() {
    try {
        const response = await fetch('get_latest_locations.php', {
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Unable to retrieve locations.');
        }

        let vehiclesWithLocations = 0;

        data.vehicles.forEach(vehicle => {
            if (vehicle.latitude === null || vehicle.longitude === null) {
                return;
            }

            const latitude = Number(vehicle.latitude);
            const longitude = Number(vehicle.longitude);

            if (
                !Number.isFinite(latitude) ||
                !Number.isFinite(longitude) ||
                latitude < -90 || latitude > 90 ||
                longitude < -180 || longitude > 180
            ) {
                return;
            }

            vehiclesWithLocations++;

            const position = [latitude, longitude];
            const label = String(vehicle.registration_number);

            if (vehicleMarkers.has(vehicle.vehicle_id)) {
                vehicleMarkers.get(vehicle.vehicle_id)
                    .setLatLng(position)
                    .setPopupContent(
                        '<strong>' + escapeMapText(label) + '</strong><br>' +
                        escapeMapText(String(vehicle.make_model || '')) + '<br>' +
                        'Latitude: ' + latitude.toFixed(6) + '<br>' +
                        'Longitude: ' + longitude.toFixed(6) + '<br>' +
                        'Last update: ' +
                        escapeMapText(String(vehicle.recorded_at || 'Unknown'))
                    );
            } else {
                const marker = L.marker(position).addTo(map);

                marker.bindPopup(
                    '<strong>' + escapeMapText(label) + '</strong><br>' +
                    escapeMapText(String(vehicle.make_model || '')) + '<br>' +
                    'Latitude: ' + latitude.toFixed(6) + '<br>' +
                    'Longitude: ' + longitude.toFixed(6) + '<br>' +
                    'Last update: ' +
                    escapeMapText(String(vehicle.recorded_at || 'Unknown'))
                );

                vehicleMarkers.set(vehicle.vehicle_id, marker);
            }
        });

        mapStatus.textContent = vehiclesWithLocations
            ? 'Map updated. Vehicles with locations: ' + vehiclesWithLocations
            : 'No saved vehicle locations are available yet.';

    } catch (error) {
        console.error('Map refresh error:', error);
        mapStatus.textContent = 'Could not update the map. Check the connection.';
    }
}

function escapeMapText(value) {
    return value.replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[character]);
}

refreshVehicleMap();
setInterval(refreshVehicleMap, 5000);
</script>

</body>
</html>


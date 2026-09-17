<?php

/**
 * BloomBot Climate Source Manager
 *
 * Chooses the most appropriate climate data source
 * for a plant based on its location and monitoring mode.
 */

header('Content-Type: application/json');

// --------------------------------------------------
// DATABASE CONNECTION
// --------------------------------------------------

require_once 'db_connect.php';

// --------------------------------------------------
// INPUT
// --------------------------------------------------

$latitude = isset($_GET['latitude']) ? (float) $_GET['latitude'] : null;
$longitude = isset($_GET['longitude']) ? (float) $_GET['longitude'] : null;
$monitoring_mode = $_GET['monitoring_mode'] ?? 'environmental_station';

if ($latitude === null || $longitude === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Plant latitude and longitude are required.'
    ]);
    exit;
}

// --------------------------------------------------
// HAVERSINE DISTANCE
// --------------------------------------------------

function calculateDistanceKm($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371;

    $lat1 = deg2rad($lat1);
    $lat2 = deg2rad($lat2);

    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLon = deg2rad($lon2 - $lon1);

    $a = sin($deltaLat / 2) ** 2
       + cos($lat1)
       * cos($lat2)
       * sin($deltaLon / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

// --------------------------------------------------
// PLANT SENSOR HAS HIGHEST PRIORITY
// --------------------------------------------------

if ($monitoring_mode === 'plant_sensor') {

    echo json_encode([
        'status' => 'success',
        'source' => [
            'name' => 'Plant Sensor',
            'type' => 'plant_sensor',
            'provider' => 'BloomBot Plant Sensor'
        ],
        'spatial_confidence' => 'Very High',
        'reason' => 'Direct measurements from the plant monitoring sensor.'
    ]);

    exit;
}

// --------------------------------------------------
// FIND NEAREST ENVIRONMENTAL STATION
// --------------------------------------------------

$query = "
    SELECT
        id,
        name,
        source_type,
        data_provider,
        location,
        county,
        town,
        latitude,
        longitude
    FROM monitoring_sources
    WHERE source_type = 'environmental_station'
    AND status = 'active'
";

$result = mysqli_query($conn, $query);

$nearestStation = null;
$nearestDistance = null;

if ($result) {

    while ($station = mysqli_fetch_assoc($result)) {

        if ($station['latitude'] === null || $station['longitude'] === null) {
            continue;
        }

        $distance = calculateDistanceKm(
            $latitude,
            $longitude,
            (float) $station['latitude'],
            (float) $station['longitude']
        );

        if ($nearestDistance === null || $distance < $nearestDistance) {
            $nearestDistance = $distance;
            $nearestStation = $station;
        }
    }
}

// --------------------------------------------------
// SOURCE SELECTION
// --------------------------------------------------
//
// The source is selected based on data quality and
// availability.
//
// Plant sensor:
//     Direct measurement from the plant.
//
// Environmental station:
//     Preferred when a trusted station is available.
//
// Location weather:
//     Used when there is no suitable local station.
//
// Distance affects spatial confidence, but is NOT
// treated as a scientifically validated cutoff.
//

if ($nearestStation && $nearestDistance <= 20) {

    if ($nearestDistance <= 5) {
        $confidence = 'High';
    } elseif ($nearestDistance <= 20) {
        $confidence = 'Moderate';
    } elseif ($nearestDistance <= 50) {
        $confidence = 'Low';
    } else {
        $confidence = 'Very Low';
    }

    echo json_encode([
        'status' => 'success',

        'source' => [
            'id' => (int) $nearestStation['id'],
            'name' => $nearestStation['name'],
            'type' => $nearestStation['source_type'],
            'provider' => $nearestStation['data_provider'],
            'location' => $nearestStation['location'],
            'county' => $nearestStation['county'],
            'town' => $nearestStation['town'],
            'latitude' => (float) $nearestStation['latitude'],
            'longitude' => (float) $nearestStation['longitude'],
            'distance_km' => round($nearestDistance, 2)
        ],

        'spatial_confidence' => $confidence,

        'selection_reason' =>
            'Nearest available trusted environmental station.',

        'note' =>
            'Spatial confidence is a prototype distance-based indicator and does not guarantee local climate equivalence.'
    ]);

    exit;
}

// --------------------------------------------------
// LOCATION-BASED WEATHER FALLBACK
// --------------------------------------------------

echo json_encode([
    'status' => 'success',

    'source' => [
        'name' => 'Open-Meteo',
        'type' => 'location_weather',
        'provider' => 'Open-Meteo / ECMWF',
        'latitude' => $latitude,
        'longitude' => $longitude
    ],

    'spatial_confidence' => 'High',

    'selection_reason' =>
        'No environmental station is currently registered for this location. Using location-specific weather data.',

    'note' =>
        'Weather data is requested using the plant location coordinates.'
]);
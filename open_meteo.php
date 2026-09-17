<?php

/**
 * BloomBot Open-Meteo Location Weather
 *
 * Returns location-specific climate data using
 * latitude and longitude.
 *
 * Provides:
 * - Current conditions
 * - Historical observations
 * - Forecast availability
 * - Data quality information
 */

header('Content-Type: application/json');

// --------------------------------------------------
// INPUT
// --------------------------------------------------

$latitude = isset($_GET['latitude']) ? (float) $_GET['latitude'] : null;
$longitude = isset($_GET['longitude']) ? (float) $_GET['longitude'] : null;

if ($latitude === null || $longitude === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Latitude and longitude are required.'
    ]);
    exit;
}

// --------------------------------------------------
// VALIDATE COORDINATES
// --------------------------------------------------

if ($latitude < -90 || $latitude > 90) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid latitude.'
    ]);
    exit;
}

if ($longitude < -180 || $longitude > 180) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid longitude.'
    ]);
    exit;
}

// --------------------------------------------------
// OPEN-METEO API
// --------------------------------------------------

$hourlyVariables = implode(',', [
    'temperature_2m',
    'relative_humidity_2m',
    'apparent_temperature',
    'precipitation',
    'rain',
    'precipitation_probability',
    'wind_speed_10m',
    'wind_direction_10m',
    'wind_gusts_10m',
    'et0_fao_evapotranspiration',
    'vapour_pressure_deficit',
    'soil_temperature_0_to_7cm',
    'soil_moisture_0_to_7cm',
    'shortwave_radiation'
]);

$url = 'https://api.open-meteo.com/v1/forecast'
     . '?latitude=' . urlencode($latitude)
     . '&longitude=' . urlencode($longitude)
     . '&hourly=' . urlencode($hourlyVariables)
     . '&forecast_days=3'
     . '&past_days=1'
     . '&timezone=auto'
     . '&temperature_unit=celsius'
     . '&wind_speed_unit=ms'
     . '&precipitation_unit=mm';

// --------------------------------------------------
// REQUEST
// --------------------------------------------------

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json'
    ]
]);

$response = curl_exec($ch);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);

    echo json_encode([
        'status' => 'error',
        'message' => 'Open-Meteo request failed.',
        'details' => $error
    ]);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// --------------------------------------------------
// HTTP VALIDATION
// --------------------------------------------------

if ($httpCode !== 200) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Open-Meteo returned an unexpected HTTP status.',
        'http_code' => $httpCode
    ]);
    exit;
}

// --------------------------------------------------
// JSON VALIDATION
// --------------------------------------------------

$data = json_decode($response, true);

if (!is_array($data)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON response from Open-Meteo.'
    ]);
    exit;
}

// --------------------------------------------------
// API ERROR
// --------------------------------------------------

if (isset($data['error']) && $data['error'] === true) {
    echo json_encode([
        'status' => 'error',
        'message' => $data['reason'] ?? 'Open-Meteo returned an error.'
    ]);
    exit;
}

// --------------------------------------------------
// CHECK HOURLY DATA
// --------------------------------------------------

if (!isset($data['hourly']) || !is_array($data['hourly'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Open-Meteo response did not contain hourly data.'
    ]);
    exit;
}

$hourly = $data['hourly'];

// --------------------------------------------------
// HELPER
// --------------------------------------------------

function getHourlyValue($hourly, $field, $index)
{
    if (
        isset($hourly[$field]) &&
        is_array($hourly[$field]) &&
        array_key_exists($index, $hourly[$field])
    ) {
        return $hourly[$field][$index];
    }

    return null;
}

// --------------------------------------------------
// FIND CURRENT HOUR
// --------------------------------------------------

$currentIndex = 0;

if (isset($hourly['time']) && is_array($hourly['time'])) {

    $now = time();
    $closestDifference = PHP_INT_MAX;

    foreach ($hourly['time'] as $index => $time) {

        $timestamp = strtotime($time);

        if ($timestamp === false) {
            continue;
        }

        $difference = abs($timestamp - $now);

        if ($difference < $closestDifference) {
            $closestDifference = $difference;
            $currentIndex = $index;
        }
    }
}

// --------------------------------------------------
// CURRENT CONDITIONS
// --------------------------------------------------

$current = [
    'time' => getHourlyValue(
        $hourly,
        'time',
        $currentIndex
    ),

    'temperature' => getHourlyValue(
        $hourly,
        'temperature_2m',
        $currentIndex
    ),

    'humidity' => getHourlyValue(
        $hourly,
        'relative_humidity_2m',
        $currentIndex
    ),

    'apparent_temperature' => getHourlyValue(
        $hourly,
        'apparent_temperature',
        $currentIndex
    ),

    'precipitation' => getHourlyValue(
        $hourly,
        'precipitation',
        $currentIndex
    ),

    'rain' => getHourlyValue(
        $hourly,
        'rain',
        $currentIndex
    ),

    'precipitation_probability' => getHourlyValue(
        $hourly,
        'precipitation_probability',
        $currentIndex
    ),

    'wind_speed' => getHourlyValue(
        $hourly,
        'wind_speed_10m',
        $currentIndex
    ),

    'wind_direction' => getHourlyValue(
        $hourly,
        'wind_direction_10m',
        $currentIndex
    ),

    'wind_gusts' => getHourlyValue(
        $hourly,
        'wind_gusts_10m',
        $currentIndex
    ),

    'et0' => getHourlyValue(
        $hourly,
        'et0_fao_evapotranspiration',
        $currentIndex
    ),

    'vpd' => getHourlyValue(
        $hourly,
        'vapour_pressure_deficit',
        $currentIndex
    ),

    'soil_temperature' => getHourlyValue(
        $hourly,
        'soil_temperature_0_to_7cm',
        $currentIndex
    ),

    'soil_moisture' => getHourlyValue(
        $hourly,
        'soil_moisture_0_to_7cm',
        $currentIndex
    ),

    'solar_radiation' => getHourlyValue(
        $hourly,
        'shortwave_radiation',
        $currentIndex
    )
];

// --------------------------------------------------
// HISTORICAL OBSERVATIONS
// --------------------------------------------------
//
// Open-Meteo gives us 1 past day + the forecast.
// We only include observations up to the current time
// here so Climate Intelligence V3 does not accidentally
// treat forecast values as historical observations.
//

$historicalObservations = [];

$now = time();

if (isset($hourly['time']) && is_array($hourly['time'])) {

    foreach ($hourly['time'] as $index => $timestamp) {

        $observationTimestamp = strtotime($timestamp);

        if ($observationTimestamp === false) {
            continue;
        }

        // Ignore future forecast hours
        if ($observationTimestamp > $now) {
            continue;
        }

        $historicalObservations[] = [
            'timestamp' => $timestamp,

            'temperature' => getHourlyValue(
                $hourly,
                'temperature_2m',
                $index
            ),

            'humidity' => getHourlyValue(
                $hourly,
                'relative_humidity_2m',
                $index
            ),

            'apparent_temperature' => getHourlyValue(
                $hourly,
                'apparent_temperature',
                $index
            ),

            'wind_speed' => getHourlyValue(
                $hourly,
                'wind_speed_10m',
                $index
            ),

            'wind_direction' => getHourlyValue(
                $hourly,
                'wind_direction_10m',
                $index
            ),

            'wind_gusts' => getHourlyValue(
                $hourly,
                'wind_gusts_10m',
                $index
            ),

            'precipitation' => getHourlyValue(
                $hourly,
                'precipitation',
                $index
            ),

            'rain' => getHourlyValue(
                $hourly,
                'rain',
                $index
            ),

            'precipitation_probability' => getHourlyValue(
                $hourly,
                'precipitation_probability',
                $index
            ),

            'et0' => getHourlyValue(
                $hourly,
                'et0_fao_evapotranspiration',
                $index
            ),

            'vpd' => getHourlyValue(
                $hourly,
                'vapour_pressure_deficit',
                $index
            ),

            'soil_temperature' => getHourlyValue(
                $hourly,
                'soil_temperature_0_to_7cm',
                $index
            ),

            'soil_moisture' => getHourlyValue(
                $hourly,
                'soil_moisture_0_to_7cm',
                $index
            ),

            // Open-Meteo is not providing these directly
            // in our current request.
            'heat_index' => null,
            'wet_bulb' => null,
            'wet_bulb_globe' => null,

            'solar_radiation' => getHourlyValue(
                $hourly,
                'shortwave_radiation',
                $index
            )
        ];
    }
}

// --------------------------------------------------
// FORECAST INFORMATION
// --------------------------------------------------

$forecastHours = 0;

if (isset($hourly['time']) && is_array($hourly['time'])) {
    $forecastHours = count($hourly['time']);
}

// --------------------------------------------------
// RESPONSE
// --------------------------------------------------

echo json_encode([
    'status' => 'success',

    'source' => [
        'name' => 'Open-Meteo',
        'provider' => 'Open-Meteo',
        'model' => 'Best Match',
        'data_type' => 'Location-based weather model'
    ],

    'location' => [
        'latitude' => $latitude,
        'longitude' => $longitude,
        'timezone' => $data['timezone'] ?? null
    ],

    'current_conditions' => $current,

    'observations' => $historicalObservations,

    'observation_summary' => [
        'observations_available' => count($historicalObservations),
        'historical_period' => 'Up to the current hour',
        'forecast_included_in_observations' => false
    ],

    'forecast' => [
        'hours_available' => $forecastHours
    ],

    'data_quality' => [
        'source_type' => 'Location-based weather model',
        'direct_sensor_measurement' => false,
        'note' => 'Values represent modelled weather conditions for the requested coordinates.'
    ]

], JSON_PRETTY_PRINT);

?>
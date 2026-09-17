<?php

header('Content-Type: application/json');

require_once 'db_connect.php';

function responseError($message, $extra = [])
{
    echo json_encode(array_merge([
        'status' => 'error',
        'message' => $message
    ], $extra));
    exit;
}

function numericValue($value)
{
    return is_numeric($value) ? (float)$value : null;
}

function conduitRequest($fromDate, $toDate)
{
    $envFile = __DIR__ . '/.env';

    if (!file_exists($envFile)) {
        responseError('.env file not found');
    }

    $env = [];

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }

    $apiKey = $env['CONDUIT_API_KEY'] ?? '';
    $email  = $env['CONDUIT_EMAIL'] ?? '';

    if (!$apiKey || !$email) {
        responseError('Conduit credentials missing from .env');
    }

    $url = 'https://conduit.jhubafrica.com/data.php';

    $postData = [
        'apikey'   => $apiKey,
        'email'    => $email,
        'fromdate' => $fromDate,
        'todate'   => $toDate
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($result === false) {
        responseError('Conduit request failed', [
            'curl_error' => $curlError
        ]);
    }

    if ($httpCode !== 200) {
        responseError('Conduit returned HTTP error', [
            'http_code' => $httpCode
        ]);
    }

    $json = json_decode($result, true);

    if (!is_array($json)) {
        responseError('Invalid JSON returned by Conduit');
    }

    return $json;
}


/*
|--------------------------------------------------------------------------
| GET LOCATION
|--------------------------------------------------------------------------
*/

$latitude = isset($_GET['latitude']) ? (float)$_GET['latitude'] : null;
$longitude = isset($_GET['longitude']) ? (float)$_GET['longitude'] : null;
$monitoringMode = $_GET['monitoring_mode'] ?? 'environmental_station';

if ($latitude === null || $longitude === null) {
    responseError('Latitude and longitude are required');
}

if ($latitude < -90 || $latitude > 90 ||
    $longitude < -180 || $longitude > 180) {
    responseError('Invalid coordinates');
}


/*
|--------------------------------------------------------------------------
| ASK SOURCE MANAGER
|--------------------------------------------------------------------------
*/

$managerUrl =
    'http://localhost/Bloombot/climate_source_manager.php' .
    '?latitude=' . urlencode($latitude) .
    '&longitude=' . urlencode($longitude) .
    '&monitoring_mode=' . urlencode($monitoringMode);

$managerResponse = @file_get_contents($managerUrl);

if ($managerResponse === false) {
    responseError('Could not contact climate source manager');
}

$sourceInfo = json_decode($managerResponse, true);

if (!is_array($sourceInfo) || ($sourceInfo['status'] ?? '') !== 'success') {
    responseError('Invalid response from climate source manager');
}

$source = $sourceInfo['source'] ?? null;

if (!$source) {
    responseError('No climate data source selected');
}


/*
|--------------------------------------------------------------------------
| PLANT SENSOR
|--------------------------------------------------------------------------
*/

if (($source['type'] ?? '') === 'plant_sensor') {

    echo json_encode([
        'status' => 'success',

        'source' => $source,

        'location' => [
            'latitude' => $latitude,
            'longitude' => $longitude
        ],

        'data' => null,

        'data_quality' => [
            'source_type' => 'Plant Sensor',
            'direct_sensor_measurement' => true,
            'note' => 'Direct plant-level sensor integration is reserved for sensor-connected deployments.'
        ]
    ], JSON_PRETTY_PRINT);

    exit;
}


/*
|--------------------------------------------------------------------------
| OPEN-METEO
|--------------------------------------------------------------------------
*/

if (($source['type'] ?? '') === 'location_weather') {

    $weatherUrl =
        'http://localhost/Bloombot/open_meteo.php' .
        '?latitude=' . urlencode($latitude) .
        '&longitude=' . urlencode($longitude);

    $weatherResponse = @file_get_contents($weatherUrl);

    if ($weatherResponse === false) {
        responseError('Could not contact Open-Meteo provider');
    }

    $weather = json_decode($weatherResponse, true);

    if (!is_array($weather) || ($weather['status'] ?? '') !== 'success') {
        responseError('Invalid Open-Meteo response');
    }

    $current = $weather['current_conditions'] ?? [];

    echo json_encode([
        'status' => 'success',

        'source' => $source,

        'location' => $weather['location'] ?? [
            'latitude' => $latitude,
            'longitude' => $longitude
        ],

        'data' => [
            'timestamp' => $current['time'] ?? null,

            'temperature' => numericValue($current['temperature'] ?? null),

            'humidity' => numericValue($current['humidity'] ?? null),

            'apparent_temperature' =>
                numericValue($current['apparent_temperature'] ?? null),

            'wind_speed' =>
                numericValue($current['wind_speed'] ?? null),

            'wind_direction' =>
                numericValue($current['wind_direction'] ?? null),

            'wind_gusts' =>
                numericValue($current['wind_gusts'] ?? null),

            'precipitation' =>
                numericValue($current['precipitation'] ?? null),

            'rain' =>
                numericValue($current['rain'] ?? null),

            'rain_probability' =>
                numericValue($current['precipitation_probability'] ?? null),

            'et0' =>
                numericValue($current['et0'] ?? null),

            'vpd' =>
                numericValue($current['vpd'] ?? null),

            'soil_temperature' =>
                numericValue($current['soil_temperature'] ?? null),

            'soil_moisture' =>
                numericValue($current['soil_moisture'] ?? null),

            'heat_index' => null,

            'wet_bulb' => null,

            'wet_bulb_globe' => null,

            'solar_radiation' =>
                numericValue($current['solar_radiation'] ?? null)
        ],

        'observations' => $weather['observations'] ?? [],

        'forecast' => $weather['forecast'] ?? [],

        'data_quality' => [
            'source_type' => 'Location-based weather model',
            'direct_sensor_measurement' => false,
            'note' => 'Values represent modelled weather conditions for the requested coordinates.'
        ]
    ], JSON_PRETTY_PRINT);

    exit;
}


/*
|--------------------------------------------------------------------------
| JKUAT CONDUIT
|--------------------------------------------------------------------------
*/

if (($source['type'] ?? '') === 'environmental_station') {

    $toDate = date('Y-m-d');
    $fromDate = date('Y-m-d', strtotime('-1 day'));

    $conduit = conduitRequest($fromDate, $toDate);

    /*
     * Conduit normally returns observations in a data array.
     */
    $rawObservations = [];

    if (isset($conduit['data']) && is_array($conduit['data'])) {
        $rawObservations = $conduit['data'];
    } elseif (array_is_list($conduit)) {
        $rawObservations = $conduit;
    }

    if (count($rawObservations) === 0) {
        responseError('No observations returned by Conduit');
    }

    $observations = [];

    foreach ($rawObservations as $row) {

        if (!is_array($row)) {
            continue;
        }

        $observations[] = [
            'timestamp' =>
                $row['ts'] ?? null,

            'temperature' =>
                numericValue($row['temp_sht'] ?? null),

            'humidity' =>
                numericValue($row['humidity_sht'] ?? null),

            'wind_speed' =>
                numericValue($row['wind_spd'] ?? null),

            'wind_direction' =>
                numericValue($row['wind_dir'] ?? null),

            'wind_gusts' =>
                numericValue($row['wind_gust'] ?? null),

            'heat_index' =>
                numericValue($row['heat_idx'] ?? null),

            'wet_bulb' =>
                numericValue($row['wet_bulb_temp'] ?? null),

            'wet_bulb_globe' =>
                numericValue($row['wet_bulb_globe_temp'] ?? null),

            'solar_visible' =>
                numericValue($row['si1145_vis'] ?? null),

            'solar_infrared' =>
                numericValue($row['si1145_ir'] ?? null),

            'uv' =>
                numericValue($row['si1145_uv'] ?? null),

            'pressure' =>
                numericValue($row['press_bmx'] ?? null),

            /*
             * Rainfall fields intentionally remain separate.
             * Their units still need validation.
             */
            'rainfall_rg1' =>
                numericValue($row['rg1'] ?? null),

            'rainfall_rg2' =>
                numericValue($row['rg2'] ?? null),

            'rainfall_units_validated' => false,

            'precipitation' => null,

            'et0' => null,

            'vpd' => null,

            'soil_temperature' => null,

            'soil_moisture' => null
        ];
    }


    /*
     * Most recent observation
     */
    $current = null;

    foreach ($observations as $observation) {

        if (
            $current === null ||
            (
                isset($observation['timestamp']) &&
                isset($current['timestamp']) &&
                strtotime($observation['timestamp']) >
                strtotime($current['timestamp'])
            )
        ) {
            $current = $observation;
        }
    }


    echo json_encode([
        'status' => 'success',

        'source' => $source,

        'location' => [
            'latitude' => $latitude,
            'longitude' => $longitude
        ],

        'data' => $current,

        'observations' => $observations,

        'forecast' => [],

        'data_quality' => [
            'source_type' => 'Environmental station',
            'direct_sensor_measurement' => true,
            'observation_count' => count($observations),
            'rainfall_units_validated' => false,
            'note' => 'Environmental observations are supplied by JKUAT Conduit. Rainfall field units remain unvalidated.'
        ]
    ], JSON_PRETTY_PRINT);

    exit;
}


/*
|--------------------------------------------------------------------------
| UNKNOWN SOURCE
|--------------------------------------------------------------------------
*/

responseError('Unsupported climate source type');
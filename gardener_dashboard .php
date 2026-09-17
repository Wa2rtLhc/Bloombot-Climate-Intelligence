<?php
include 'weather_api.php';
session_start();
include('db_connect.php');
require_once __DIR__ . '/notification_engine.php';

$climate_intelligence = null;
$climate_error = null;
// Timeout duration in seconds (e.g., 15 minutes)
$inactive = 900; 

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?message=Please log in to continue");
    exit();
}

// Check for session timeout
if (isset($_SESSION['last_activity'])) {
    $session_life = time() - $_SESSION['last_activity'];
    if ($session_life > $inactive) {
        // Destroy session and redirect
        session_unset();
        session_destroy();
        header("Location: login.php?message=Session expired, please log in again");
        exit();
    }
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'gardener') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$weather = null;
// Fetch latest sensor data including plant name
$sensor_query = mysqli_query($conn,
    "SELECT sd.*, p.name AS plant_name FROM sensor_data sd
     JOIN plants p ON sd.plant_id = p.id
     WHERE p.gardener_username = '$username'
     ORDER BY sd.timestamp DESC
     LIMIT 5"
);

$sensor_data = [];
if ($sensor_query && mysqli_num_rows($sensor_query) > 0) {
    while ($row = mysqli_fetch_assoc($sensor_query)) {
        $sensor_data[] = $row;
    }
}
/*
|--------------------------------------------------------------------------
| LIVE CLIMATE OBSERVATIONS
|--------------------------------------------------------------------------
| These are populated from Climate Intelligence for each plant.
| sensor_data remains reserved for direct plant-sensor measurements.
|--------------------------------------------------------------------------
*/
$climate_observations = [];
$plant_climate_data = [];
$recentAlertsQuery = "
    SELECT n.message, n.timestamp, p.name AS plant_name 
    FROM notifications n
    JOIN plants p ON n.plant_id = p.id
    WHERE p.gardener_username = ?
    AND n.timestamp >= NOW() - INTERVAL 5 MINUTE 
    ORDER BY n.timestamp DESC
";

$stmt = $conn->prepare($recentAlertsQuery);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$recentAlerts = [];
while ($row = $result->fetch_assoc()) {
    $recentAlerts[] = $row['plant_name'] . ": " . $row['message'];
}


/*
|--------------------------------------------------------------------------
| LOAD GARDENER PLANTS
|--------------------------------------------------------------------------
| We load plant climate intelligence BEFORE rendering the dashboard.
| This ensures that Recent Climate Observations, charts and plant cards
| all use the same live climate data.
|--------------------------------------------------------------------------
*/

$plants = [];

$plant_query = mysqli_query(
    $conn,
    "SELECT *
     FROM plants
     WHERE gardener_username = '$username'
     ORDER BY id DESC"
);

if ($plant_query && mysqli_num_rows($plant_query) > 0) {

    while ($plant = mysqli_fetch_assoc($plant_query)) {

        $plant_id = (int)$plant['id'];

        $plant_name = $plant['name'];

        $plant_latitude =
            $plant['latitude'] ?? null;

        $plant_longitude =
            $plant['longitude'] ?? null;

        $plant_monitoring_mode =
            $plant['monitoring_mode']
            ?? 'environmental_station';


        /*
        |--------------------------------------------------------------------------
        | DEFAULT CLIMATE VALUES
        |--------------------------------------------------------------------------
        */

        $plant_climate_data[$plant_id] = null;

        $plant['climate_intelligence'] = null;

        $plant['climate_error'] = null;


        /*
        |--------------------------------------------------------------------------
        | GET CLIMATE INTELLIGENCE
        |--------------------------------------------------------------------------
        */

        if (
            is_numeric($plant_latitude) &&
            is_numeric($plant_longitude)
        ) {

            $intelligence_file =
                __DIR__ . '/climate_intelligence.php';

            if (file_exists($intelligence_file)) {

                try {

                    $protocol =
                        (
                            isset($_SERVER['HTTPS']) &&
                            $_SERVER['HTTPS'] !== 'off'
                        )
                        ? 'https'
                        : 'http';

                    $host =
                        $_SERVER['HTTP_HOST'];

                    $base_path =
                        dirname($_SERVER['SCRIPT_NAME']);

                    $intelligence_url =
                        $protocol .
                        '://' .
                        $host .
                        $base_path .
                        '/climate_intelligence.php?' .
                        http_build_query([
                            'latitude' =>
                                $plant_latitude,

                            'longitude' =>
                                $plant_longitude,

                            'monitoring_mode' =>
                                $plant_monitoring_mode
                        ]);


                    $ch =
                        curl_init(
                            $intelligence_url
                        );

                    curl_setopt(
                        $ch,
                        CURLOPT_RETURNTRANSFER,
                        true
                    );

                    curl_setopt(
                        $ch,
                        CURLOPT_FOLLOWLOCATION,
                        true
                    );

                    curl_setopt(
                        $ch,
                        CURLOPT_TIMEOUT,
                        30
                    );


                    $intelligence_output =
                        curl_exec($ch);

                    $http_code =
                        curl_getinfo(
                            $ch,
                            CURLINFO_HTTP_CODE
                        );

                    curl_close($ch);


                    if (
                        $intelligence_output === false
                    ) {

                        $plant['climate_error'] =
                            'Unable to connect to the climate intelligence engine.';

                    } elseif (
                        $http_code !== 200
                    ) {

                        $plant['climate_error'] =
                            'Climate intelligence returned HTTP ' .
                            $http_code . '.';

                    } else {

                        $decoded =
                            json_decode(
                                trim($intelligence_output),
                                true
                            );


                        if (
                            is_array($decoded) &&
                            ($decoded['status'] ?? '') === 'success'
                        ) {

                            $plant_climate_data[$plant_id] =
                                $decoded;

                            $plant['climate_intelligence'] =
                                $decoded;


                            /*
                            |--------------------------------------------------------------------------
                            | STORE LIVE OBSERVATIONS
                            |--------------------------------------------------------------------------
                            */

                            $observations =
                                $decoded['observations']
                                ?? [];


                            foreach (
                                $observations
                                as $observation
                            ) {

                                $climate_observations[] = [

                                    'plant_id' =>
                                        $plant_id,

                                    'plant_name' =>
                                        $plant['name'],

                                    'source' =>
                                        $decoded['source']['name']
                                        ?? 'Environmental source',

                                    'timestamp' =>
                                        $observation['timestamp']
                                        ?? null,

                                    'temperature' =>
                                        isset(
                                            $observation['temperature']
                                        ) &&
                                        is_numeric(
                                            $observation['temperature']
                                        )
                                        ? (float)
                                          $observation['temperature']
                                        : null,

                                    'humidity' =>
                                        isset(
                                            $observation['humidity']
                                        ) &&
                                        is_numeric(
                                            $observation['humidity']
                                        )
                                        ? (float)
                                          $observation['humidity']
                                        : null,

                                    'wind_speed' =>
                                        isset(
                                            $observation['wind_speed']
                                        ) &&
                                        is_numeric(
                                            $observation['wind_speed']
                                        )
                                        ? (float)
                                          $observation['wind_speed']
                                        : null,

                                    'moisture' =>
                                        isset(
                                            $observation['soil_moisture']
                                        ) &&
                                        is_numeric(
                                            $observation['soil_moisture']
                                        )
                                        ? (float)
                                          $observation['soil_moisture']
                                        : null,

                                    'light_level' =>
                                        isset(
                                            $observation['solar_radiation']
                                        ) &&
                                        is_numeric(
                                            $observation['solar_radiation']
                                        )
                                        ? (float)
                                          $observation['solar_radiation']
                                        : null
                                ];
                            }

                        } else {

                            $plant['climate_error'] =
                                $decoded['message']
                                ?? 'Climate intelligence returned an invalid response.';
                        }
                    }

                } catch (Throwable $e) {

                    $plant['climate_error'] =
                        'Climate intelligence is temporarily unavailable.';
                }

            } else {

                $plant['climate_error'] =
                    'Climate intelligence engine not found.';
            }

        } else {

            $plant['climate_error'] =
                'Plant location is not configured.';
        }


        /*
        |--------------------------------------------------------------------------
        | STORE PLANT
        |--------------------------------------------------------------------------
        */

        $plants[] = $plant;
    }
}


/*
|--------------------------------------------------------------------------
| SORT CLIMATE OBSERVATIONS
|--------------------------------------------------------------------------
*/

usort(
    $climate_observations,
    function ($a, $b) {

        return strcmp(
            $b['timestamp'] ?? '',
            $a['timestamp'] ?? ''
        );
    }
);


/*
|--------------------------------------------------------------------------
| SELECT A DASHBOARD CLIMATE INTELLIGENCE RESULT
|--------------------------------------------------------------------------
| Used by the main Climate Intelligence KPI section.
| The plant-specific cards below still use their own climate data.
|--------------------------------------------------------------------------
*/

$climate_intelligence = null;

if (!empty($plants)) {

    foreach ($plants as $plant) {

        if (
            isset($plant['climate_intelligence']) &&
            is_array($plant['climate_intelligence']) &&
            ($plant['climate_intelligence']['status'] ?? '') === 'success'
        ) {

            $climate_intelligence =
                $plant['climate_intelligence'];

            break;
        }
    }
}

?>
<!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gardener Dashboard - Bloombot</title>
    <meta http-equiv="refresh" content="200">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/style.css?v=8">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
</head>
<body><header class="topnav">

    <div class="brand">
        <span class="brand-mark">🌿</span>
        <span>BloomBot</span>
    </div>

    <nav class="topnav-links">
        <a href="gardener_dashboard .php" class="active">Dashboard</a>
        <a href="about.html">About</a>
        <a href="contact.html">Contact</a>
        <a href="profile.php">Profile</a>
        <a href="gardener_reports.php">Reports</a>
    </nav>

    <div class="topnav-right">
        <span class="user-greeting">
            👋 <?= htmlspecialchars($username) ?>
        </span>

        <a href="logout.php" class="logout-link">
            Logout
        </a>
    </div>

</header><section class="page-header">

    <div>
        <span class="eyebrow">BLOOMBOT CLIMATE INTELLIGENCE</span>

        <h1>Welcome back, <?= htmlspecialchars($username) ?>.</h1>

        <p>
            Monitor your plants, understand the climate, and act before conditions become a problem.
        </p>
    </div>

    <div class="header-status">
        <span class="status-dot"></span>
        Monitoring active
    </div>

</section><div class="main">
    <aside class="sidebar" id="sidebar">

    <div class="sidebar-header">

        <div class="sidebar-title">
            <span class="sidebar-logo">🌿</span>

            <div>
                <strong>BloomBot</strong>
                <small>Gardener</small>
            </div>
        </div>

        <button
            type="button"
            class="sidebar-toggle"
            id="sidebarToggle"
            aria-label="Toggle menu"
            aria-expanded="true"
        >
            ☰
        </button>

    </div>


    <div class="sidebar-section">

        <span class="sidebar-label">MAIN</span>

        <a class="menu-button active" href="gardener_dashboard .php">
            <span class="menu-icon">⌂</span>
            <span class="menu-text">Dashboard</span>
        </a>

        <a class="menu-button" href="view_plants.php">
            <span class="menu-icon">🌱</span>
            <span class="menu-text">My Plants</span>
        </a>

        <a class="menu-button" href="add_plant.php">
            <span class="menu-icon">＋</span>
            <span class="menu-text">Add Plant</span>
        </a>

    </div>


    <div class="sidebar-section">

        <span class="sidebar-label">MONITORING</span>

        <a class="menu-button" href="sensor_data.php">
            <span class="menu-icon">◉</span>
            <span class="menu-text">Sensor Data</span>
        </a>

        <a class="menu-button" href="view_alerts.php">
            <span class="menu-icon">🔔</span>
            <span class="menu-text">Alerts</span>

            <?php if (!empty($recentAlerts)): ?>
                <span class="notification-count">
                    <?= count($recentAlerts) ?>
                </span>
            <?php endif; ?>

        </a>

        <a class="menu-button" href="set_threshold.php">
            <span class="menu-icon">⚙️</span>
            <span class="menu-text">Thresholds</span>
        </a>

    </div>


    <div class="sidebar-section">

        <span class="sidebar-label">ACCOUNT</span>

        <a class="menu-button" href="profile.php">
            <span class="menu-icon">👤</span>
            <span class="menu-text">Profile</span>
        </a>

        <a class="menu-button" href="gardener_settings.html">
            <span class="menu-icon">⚙️</span>
            <span class="menu-text">Settings</span>
        </a>

        <a class="menu-button logout-menu" href="logout.php">
            <span class="menu-icon">↪️</span>
            <span class="menu-text">Logout</span>
        </a>

    </div>

</aside><div class="content">
        <!-- ===============================
     BLOOMBOT CLIMATE INTELLIGENCE
     =============================== -->

<?php if ($climate_intelligence && isset($climate_intelligence['status']) && $climate_intelligence['status'] === 'success'): ?>

<section class="climate-intelligence modern-intelligence">

    <div class="intelligence-header">

        <div>
            <span class="eyebrow">BLOOMBOT INTELLIGENCE</span>
            <h2>Climate at a glance</h2>
        </div>

        <div class="intelligence-live">
            <span class="status-dot"></span>
            LIVE
        </div>

    </div>


    <div class="climate-hero">

        <?php
        $health_score = (int)($climate_intelligence['climate_health']['score'] ?? 0);
        ?>

        <div
            class="climate-score-ring"
            style="--score: <?= $health_score ?>;"
        >

            <div class="score-content">

                <strong>
                    <?= $health_score ?>
                </strong>

                <span>/100</span>

            </div>

        </div>


        <div class="climate-summary">

            <span class="card-eyebrow">
                CLIMATE HEALTH
            </span>

            <h3>
                <?= htmlspecialchars(
                    $climate_intelligence['climate_health']['status']
                    ?? 'Unknown'
                ) ?>
            </h3>

            <p>
                <?= htmlspecialchars(
                    $climate_intelligence['risk']['primary_driver']
                    ?? 'Conditions are being monitored'
                ) ?>
            </p>

        </div>

    </div>


    <div class="modern-climate-metrics">

        <div class="modern-metric">

            <div class="metric-icon">🌡️</div>

            <div>

                <span>Temperature</span>

                <strong>
                    <?= htmlspecialchars(
                        $climate_intelligence['current_conditions']['temperature']['value']
                        ?? '--'
                    ) ?>°C
                </strong>

                <small>
                    <?= htmlspecialchars(
                        $climate_intelligence['current_conditions']['temperature']['status']
                        ?? ''
                    ) ?>
                </small>

            </div>

        </div>


        <div class="modern-metric">

            <div class="metric-icon">💧</div>

            <div>

                <span>Humidity</span>

                <strong>
                    <?= htmlspecialchars(
                        $climate_intelligence['current_conditions']['humidity']['value']
                        ?? '--'
                    ) ?>%
                </strong>

                <small>
                    <?= htmlspecialchars(
                        $climate_intelligence['current_conditions']['humidity']['status']
                        ?? ''
                    ) ?>
                </small>

            </div>

        </div>


        <div class="modern-metric">

            <div class="metric-icon">💨</div>

            <div>

                <span>Airflow</span>

                <strong>
                    <?= htmlspecialchars(
                        $climate_intelligence['current_conditions']['wind']['value']
                        ?? '--'
                    ) ?>
                    m/s
                </strong>

                <small>
                    <?= htmlspecialchars(
                        $climate_intelligence['current_conditions']['wind']['status']
                        ?? ''
                    ) ?>
                </small>

            </div>

        </div>


        <div class="modern-metric risk-metric">

            <div class="metric-icon">⚠️</div>

            <div>

                <span>Risk</span>

                <strong>
                    <?= htmlspecialchars(
                        $climate_intelligence['risk']['level']
                        ?? 'Unknown'
                    ) ?>
                </strong>

                <small>
                    <?= htmlspecialchars(
                        $climate_intelligence['risk']['score']
                        ?? '--'
                    ) ?>
                    score
                </small>

            </div>

        </div>

    </div>


    <div class="climate-quick-insight">

        <span class="insight-icon">💡</span>

        <div>

            <span class="card-eyebrow">
                PRIMARY DRIVER
            </span>

            <strong>
                <?= htmlspecialchars(
                    $climate_intelligence['risk']['primary_driver']
                    ?? 'No major driver detected'
                ) ?>
            </strong>

        </div>

        <div class="insight-arrow">
            →
        </div>

    </div>

</section>


<?php elseif ($climate_error): ?>

<div class="climate-error">
    ⚠️ <?= htmlspecialchars($climate_error) ?>
</div>

<?php endif; ?>
    <?php if ($weather): ?>
        <div class="weather-widget">
           <h3>🌤 Current Weather in Nairobi</h3>
            <p>Weather in <?= ucfirst($weather['city']) ?>: <?= $weather['temperature'] ?>°C, <?= ucfirst($weather['description']) ?></p>
            <?php if ($weather['will_rain']): ?>
                <p style="color: #2b6cb0; font-weight: bold;">☔ Rain is expected today – you may not need to water your plants.</p>
            <?php else: ?>
                <p style="color: #38a169; font-weight: bold;">🌤 No rain expected – consider watering your plants.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

 <h3>Recent Climate Observations</h3>

<?php if (!empty($climate_observations)): ?>

<table border="1"
       width="100%"
       cellpadding="8"
       cellspacing="0">

    <tr style="background-color:#2c7a5d;color:white;">

        <th>Plant</th>
        <th>Source</th>
        <th>Temperature (°C)</th>
        <th>Humidity (%)</th>
        <th>Airflow (m/s)</th>
        <th>Timestamp</th>

    </tr>

    <?php

    /*
     * Show the newest observations first.
     */
    usort(
        $climate_observations,
        function ($a, $b) {
            return strcmp(
                $b['timestamp'] ?? '',
                $a['timestamp'] ?? ''
            );
        }
    );

    /*
     * Only show the latest 10 observations.
     */
    $recent_climate_observations =
        array_slice($climate_observations, 0, 5);

    ?>

    <?php foreach ($recent_climate_observations as $observation): ?>

    <tr>

        <td>
            <?= htmlspecialchars(
                $observation['plant_name']
            ) ?>
        </td>

        <td>
            <?= htmlspecialchars(
                $observation['source']
            ) ?>
        </td>

        <td>
            <?= $observation['temperature'] !== null
                ? htmlspecialchars(
                    $observation['temperature']
                )
                : '—'
            ?>
        </td>

        <td>
            <?= $observation['humidity'] !== null
                ? htmlspecialchars(
                    $observation['humidity']
                )
                : '—'
            ?>
        </td>

        <td>
            <?= $observation['wind_speed'] !== null
                ? htmlspecialchars(
                    $observation['wind_speed']
                )
                : '—'
            ?>
        </td>

        <td>
            <?= htmlspecialchars(
                $observation['timestamp'] ?? '—'
            ) ?>
        </td>

    </tr>

    <?php endforeach; ?>

</table>

<?php else: ?>

<p>
    No live climate observations are currently available.
</p>

<?php endif; ?>

    <div class="chart-box">
        <h3>Live Climate Trend</h3>

<p>
    Climate observations from the active monitoring source.
</p>
        <canvas id="sensorChart" width="100%" height="40"></canvas>
    </div>

  <section class="plants-section">

    <div class="section-heading">

        <div>
            <span class="eyebrow">YOUR GARDEN</span>

            <h2>Your Plants</h2>

            <p>
                BloomBot's live assessment of your monitored plants.
            </p>
        </div>

        <a href="add_plant.php" class="primary-button">
            + Add Plant
        </a>

    </div>

    <?php if (!empty($plants)): ?>

    <div class="plant-grid">

        <?php foreach ($plants as $plant): ?>
        <?php

        $plant_id =
            (int)$plant['id'];

        $plant_name =
            $plant['name'];

        $plant_type =
            $plant['type'];

        $plant_location =
            $plant['location'];

        $plant_latitude =
            $plant['latitude'] ?? null;

        $plant_longitude =
            $plant['longitude'] ?? null;

        $plant_monitoring_mode =
            $plant['monitoring_mode']
            ?? 'environmental_station';

        $climate_intelligence =
            $plant['climate_intelligence']
            ?? null;

        $climate_error =
            $plant['climate_error']
            ?? null;

        ?>
       <?php
        /*
        |--------------------------------------------------------------------------
        | GET PLANT THRESHOLDS
        |--------------------------------------------------------------------------
        */

        $threshold_sql = "
            SELECT *
            FROM thresholds
            WHERE plant_id = $plant_id
            LIMIT 1
        ";

        $threshold_res = mysqli_query($conn, $threshold_sql);

        $plant_threshold = $threshold_res
            ? mysqli_fetch_assoc($threshold_res)
            : null;


        /*
        |--------------------------------------------------------------------------
        | CHECK FOR REAL / EXISTING SENSOR DATA FIRST
        |--------------------------------------------------------------------------
        */

        $sensor_sql = "
            SELECT *
            FROM sensor_data
            WHERE plant_id = $plant_id
            ORDER BY timestamp DESC
            LIMIT 1
        ";

        $sensor_res = mysqli_query($conn, $sensor_sql);

        $sensor = $sensor_res
            ? mysqli_fetch_assoc($sensor_res)
            : null;


        /*
        |--------------------------------------------------------------------------
        | DEFAULT PLANT ASSESSMENT
        |--------------------------------------------------------------------------
        */

        $plant_status = "Monitoring";
        $plant_status_class = "monitoring";

        $assessment_source = "Live environmental assessment";

        $temperature_value = null;
        $humidity_value = null;
        $wind_value = null;

        $assessment_message =
            "BloomBot is monitoring the current environmental conditions.";

        $recommended_action =
            "Continue monitoring your plant and check soil moisture before watering.";


        /*
        |--------------------------------------------------------------------------
        | IF LIVE CLIMATE INTELLIGENCE IS AVAILABLE
        |--------------------------------------------------------------------------
        */

        if (
            is_array($climate_intelligence) &&
            ($climate_intelligence['status'] ?? '') === 'success'
        ) {

            $temperature_value =
                $climate_intelligence['current_conditions']['temperature']['value']
                ?? null;

            $humidity_value =
                $climate_intelligence['current_conditions']['humidity']['value']
                ?? null;

            $wind_value =
                $climate_intelligence['current_conditions']['wind']['value']
                ?? null;


            /*
            |--------------------------------------------------------------------------
            | START WITH THE GLOBAL CLIMATE RISK
            |--------------------------------------------------------------------------
            */

            $global_risk =
                $climate_intelligence['risk']['level']
                ?? 'Moderate';

            $plant_status = $global_risk;


            /*
            |--------------------------------------------------------------------------
            | PLANT-SPECIFIC TEMPERATURE CHECK
            |--------------------------------------------------------------------------
            */

            if (
                $plant_threshold &&
                is_numeric($temperature_value)
            ) {

                $temperature_min =
                    isset($plant_threshold['temperature_min'])
                    ? (float)$plant_threshold['temperature_min']
                    : null;

                $temperature_max =
                    isset($plant_threshold['temperature_max'])
                    ? (float)$plant_threshold['temperature_max']
                    : null;


                if (
                    $temperature_min !== null &&
                    $temperature_value < $temperature_min
                ) {

                    $plant_status = "Too Cold";
                    $plant_status_class = "cold";

                    $assessment_message =
                        "The current environmental temperature is below the preferred minimum for this plant.";

                    $recommended_action =
                        "Monitor the plant closely and consider whether additional warmth or protection is needed.";

                } elseif (
                    $temperature_max !== null &&
                    $temperature_value > $temperature_max
                ) {

                    $plant_status = "Too Hot";
                    $plant_status_class = "hot";

                    $assessment_message =
                        "The current environmental temperature is above the preferred maximum for this plant.";

                    $recommended_action =
                        "Monitor for heat stress and reduce excessive heat exposure where possible.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | TEMPERATURE IS WITHIN RANGE — CHECK CLIMATE RISK
                    |--------------------------------------------------------------------------
                    */

                    if ($global_risk === 'Critical') {

                        $plant_status = "Critical";
                        $plant_status_class = "critical";

                    } elseif ($global_risk === 'High') {

                        $plant_status = "High Risk";
                        $plant_status_class = "high";

                    } elseif ($global_risk === 'Moderate') {

                        $plant_status = "Moderate";
                        $plant_status_class = "moderate";

                    } else {

                        $plant_status = "Favorable";
                        $plant_status_class = "good";
                    }


                    $assessment_message =
                        "Temperature is currently within the plant's configured range. BloomBot is also evaluating the wider climate conditions.";

                    $recommended_action =
                        $climate_intelligence['crop_intelligence']['recommended_action']
                        ?? "Continue monitoring the plant.";
                }
            }


            /*
            |--------------------------------------------------------------------------
            | NO THRESHOLDS
            |--------------------------------------------------------------------------
            */

            elseif (!$plant_threshold) {

                $plant_status = $global_risk;

                if ($global_risk === 'Critical') {
                    $plant_status_class = "critical";
                } elseif ($global_risk === 'High') {
                    $plant_status_class = "high";
                } elseif ($global_risk === 'Moderate') {
                    $plant_status_class = "moderate";
                } else {
                    $plant_status_class = "good";
                }

                $assessment_message =
                    "Live climate data is available, but this plant does not have configured thresholds.";

                $recommended_action =
                    "Set plant thresholds so BloomBot can provide a more specific assessment.";
            }


            /*
            |--------------------------------------------------------------------------
            | HUMIDITY + LOW AIRFLOW WARNING
            |--------------------------------------------------------------------------
            */

            if (
                is_numeric($humidity_value) &&
                is_numeric($wind_value) &&
                $humidity_value >= 80 &&
                $wind_value <= 1
            ) {

                $plant_status = "Humidity Risk";
                $plant_status_class = "high";

                $assessment_message =
                    "High humidity combined with very low airflow may increase moisture retention around foliage.";

                $recommended_action =
                    "Monitor foliage closely and improve airflow around the plant where possible.";
            }
        }


        /*
        |--------------------------------------------------------------------------
        | REAL SENSOR DATA OVERRIDES THE ENVIRONMENTAL FALLBACK
        |--------------------------------------------------------------------------
        */

        if ($sensor) {

            $assessment_source = "Plant sensor data";

            $sensor_temperature =
                isset($sensor['temperature'])
                ? (float)$sensor['temperature']
                : null;

            $sensor_moisture =
                isset($sensor['moisture'])
                ? (float)$sensor['moisture']
                : null;

            $sensor_light =
                isset($sensor['light_level'])
                ? (float)$sensor['light_level']
                : null;


            if ($plant_threshold) {

                if (
                    $sensor_moisture !== null &&
                    $sensor_moisture < $plant_threshold['moisture_min']
                ) {

                    $plant_status = "Needs Water";
                    $plant_status_class = "water";

                    $assessment_message =
                        "The plant sensor indicates that soil moisture is below the configured minimum.";

                    $recommended_action =
                        "Check the soil and water the plant if the reading is confirmed.";

                } elseif (
                    $sensor_temperature !== null &&
                    $sensor_temperature < $plant_threshold['temperature_min']
                ) {

                    $plant_status = "Too Cold";
                    $plant_status_class = "cold";

                } elseif (
                    $sensor_temperature !== null &&
                    $sensor_temperature > $plant_threshold['temperature_max']
                ) {

                    $plant_status = "Too Hot";
                    $plant_status_class = "hot";

                } elseif (
                    $sensor_light !== null &&
                    $sensor_light < $plant_threshold['light_min']
                ) {

                    $plant_status = "Too Dark";
                    $plant_status_class = "moderate";

                } elseif (
                    $sensor_light !== null &&
                    $sensor_light > $plant_threshold['light_max']
                ) {

                    $plant_status = "Too Bright";
                    $plant_status_class = "moderate";

                } else {

                    $plant_status = "Healthy";
                    $plant_status_class = "good";

                    $assessment_message =
                        "The latest plant sensor readings are within the configured thresholds.";

                    $recommended_action =
                        "Continue monitoring the plant normally.";
                }
            }
        }
/*
|--------------------------------------------------------------------------
| CREATE GARDENER NOTIFICATION
|--------------------------------------------------------------------------
*/

if (
    is_array($climate_intelligence) &&
    ($climate_intelligence['status'] ?? '') === 'success'
) {

    $notification_result = generateClimateNotifications(
        $conn,
        $climate_intelligence,
        $plant_id,
        $plant_name,
        $plant_status,
        $assessment_message,
        $recommended_action,
        null,
        $sensor ? true : false
    );
}
?>

<article class="plant-card">

    <div class="plant-card-header">

        <div>
            <span class="plant-eyebrow">🌱 PLANT PROFILE</span>

            <h3>
                <?= htmlspecialchars($plant_name) ?>
            </h3>

            <p>
                <?= htmlspecialchars($plant_type) ?>
                •
                <?= htmlspecialchars($plant_location) ?>
            </p>
        </div>

        <span class="plant-status <?= htmlspecialchars($plant_status_class) ?>">
            <?= htmlspecialchars($plant_status) ?>
        </span>

    </div>


    <div class="plant-data-grid">

        <div class="plant-data-item">

            <span>🌡️ Temperature</span>

            <strong>
                <?php if ($sensor && isset($sensor['temperature'])): ?>

                    <?= htmlspecialchars($sensor['temperature']) ?>°C

                <?php elseif ($temperature_value !== null): ?>

                    <?= htmlspecialchars($temperature_value) ?>°C

                <?php else: ?>

                    —

                <?php endif; ?>
            </strong>

            <small>
                <?php if ($sensor): ?>
                    Plant sensor
                <?php else: ?>
                    Live environment
                <?php endif; ?>
            </small>

        </div>


        <div class="plant-data-item">

            <span>💨 Airflow</span>

            <strong>

                <?php if ($wind_value !== null): ?>

                    <?= htmlspecialchars($wind_value) ?> m/s

                <?php else: ?>

                    —

                <?php endif; ?>

            </strong>

            <small>
                Live environment
            </small>

        </div>


        <div class="plant-data-item">

            <span>🧠 Data Source</span>

            <strong>
                Live
            </strong>

            <small>
                <?= htmlspecialchars($assessment_source) ?>
            </small>

        </div>

    </div>


    <div class="assessment-grid">

    <div class="assessment-card assessment-main">

        <div class="assessment-icon">
            🧠
        </div>

        <div>
            <span class="card-eyebrow">BLOOMBOT ASSESSMENT</span>

            <h4>Current assessment</h4>

            <p>
                <?= htmlspecialchars($assessment_message) ?>
            </p>
        </div>

    </div>

    <div class="assessment-card action-card">

        <div class="assessment-icon">
            💡
        </div>

        <div>
            <span class="card-eyebrow">RECOMMENDED ACTION</span>

            <h4>What to do</h4>

            <p>
                <?= htmlspecialchars($recommended_action) ?>
            </p>
        </div>

    </div>

</div>


    <?php if ($plant_threshold): ?>

    <div class="plant-threshold-note">

        <span>⚙️</span>

        <span>
            Personalised using this plant's configured thresholds.
        </span>

    </div>

    <?php else: ?>

    <div class="plant-threshold-note warning">

        <span>⚠️</span>

        <span>
            No plant thresholds configured.
            <a href="set_threshold.php">Configure thresholds</a>
            for a more specific assessment.
        </span>

    </div>

    <?php endif; ?>

    </article>

<?php

    endforeach; ?>

<?php else: ?>

<p>You have no plants added yet.</p>

<?php endif; ?>
</div>

<div id="popup-alert"style="display:none; position:fixed; bottom:20px; right:20px;background-color:red;  padding:15px; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.3); z-index:9999;">
    <span id="popup-message"></span>
</div>

</div><?php
$timestamps = [];
$temperatures = [];
$humidities = [];
$wind_speeds = [];

foreach ($climate_observations as $observation) {

    $timestamps[] =
        $observation['timestamp'];

    $temperatures[] =
        $observation['temperature'];

    $humidities[] =
        $observation['humidity'];

    $wind_speeds[] =
        $observation['wind_speed'];
}
?><script>
const ctx = document.getElementById('sensorChart').getContext('2d');
const sensorChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($timestamps) ?>,
       datasets: [
    {
        label: 'Temperature (°C)',

        data: <?= json_encode($temperatures) ?>,

        borderColor: 'rgba(255, 99, 132, 1)',

        fill: false,

        tension: 0.3
    },

    {
        label: 'Humidity (%)',

        data: <?= json_encode($humidities) ?>,

        borderColor: 'rgba(54, 162, 235, 1)',

        fill: false,

        tension: 0.3
    },

    {
        label: 'Airflow (m/s)',

        data: <?= json_encode($wind_speeds) ?>,

        borderColor: 'rgba(75, 192, 120, 1)',

        fill: false,

        tension: 0.3
    }
]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Live Climate Trend'
            },
            legend: {
                position: 'top'
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Timestamp'
                }
            },
            y: {
                title: {
                    display: true,
                    text: 'Value'
                }
            }
        }
    }
});
</script><script>
const alerts = <?php echo json_encode($recentAlerts); ?>;
let alertIndex = 0;

function showAlert() {
    if (alertIndex < alerts.length) {
        const popup = document.getElementById("popup-alert");
        const message = document.getElementById("popup-message");
        message.innerText = alerts[alertIndex];
        popup.style.display = "block";

        setTimeout(() => {
            popup.style.display = "none";
            alertIndex++;
            showAlert();
        }, 8000);
    }
}

if (alerts.length > 0) {
    showAlert();
}
</script><script>
document.addEventListener("DOMContentLoaded", function () {

    const sidebarToggle = document.getElementById("sidebarToggle");

    if (sidebarToggle) {

        sidebarToggle.addEventListener("click", function () {

            document.body.classList.toggle("sidebar-collapsed");

            const isCollapsed =
                document.body.classList.contains("sidebar-collapsed");

            sidebarToggle.setAttribute(
                "aria-expanded",
                isCollapsed ? "false" : "true"
            );

        });

    }

});
</script>
</body>
</html>

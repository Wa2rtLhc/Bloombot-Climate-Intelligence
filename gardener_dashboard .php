<?php
include 'weather_api.php';
session_start();
include('db_connect.php');

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
// ===============================
// BLOOMBOT CLIMATE INTELLIGENCE
// ===============================
$climate_intelligence = null;
$climate_error = null;

$intelligence_url = __DIR__ . '/climate_intelligence.php';

if (file_exists($intelligence_url)) {

    ob_start();

    try {

        include $intelligence_url;

        $intelligence_output = ob_get_clean();

        $climate_intelligence = json_decode(
            trim($intelligence_output),
            true
        );

        if (
            !is_array($climate_intelligence) ||
            ($climate_intelligence['status'] ?? '') !== 'success'
        ) {
            $climate_error = 'Climate intelligence returned an invalid response.';
            $climate_intelligence = null;
        }

    } catch (Throwable $e) {

        ob_end_clean();

        $climate_error = 'Climate intelligence is temporarily unavailable.';
        $climate_intelligence = null;
    }

} else {

    $climate_error = 'Climate intelligence engine not found.';
}

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
?>
<!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gardener Dashboard - Bloombot</title>
    <meta http-equiv="refresh" content="200">
    <link rel="stylesheet" href="CSS/style.css?v=6">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body><div class="topnav">
    <a href="gardener_dashboard .php">Dashboard</a>
    <a href="about.html">About</a>
    <a href="contact.html">Contact Us</a>
    <a href="profile.php">My Profile</a>
    <a href="view_plants.php">My Plants</a>
    <a href="gardener_reports.php">Reports</a>
    <div class="topnav-right">
        <a href="logout.php">Logout</a>
    </div>
</div><div class="header">
    <h1>Welcome, Gardener!</h1>
</div><div class="main">
    <div class="sidebar">
        <h2>Menu</h2>
        <a class="menu-button" href="add_plant.php">🌿 Add Plants</a>
        <a class="menu-button" href="set_threshold.php">⚙ Set Thresholds</a>
        <a class="menu-button" href="view_alerts.php">🔔 View Alerts</a>
        <a class="menu-button" href="sensor_data.php">📊 View Sensor Data</a>
        <a class="menu-button" href="profile.php">👤 Profile</a>
        <a class="menu-button" href="gardener_settings.html"> ⚙ Settings</a>
    </div><div class="content">
        <!-- ===============================
     BLOOMBOT CLIMATE INTELLIGENCE
     =============================== -->

<?php if ($climate_intelligence && isset($climate_intelligence['status']) && $climate_intelligence['status'] === 'success'): ?>

<div class="climate-intelligence">

    <div class="intelligence-header">
        <div>
            <span class="eyebrow">LIVE CLIMATE INTELLIGENCE</span>
            <h2>Environmental Conditions</h2>
            <p>
                Powered by live JKUAT Conduit observations
            </p>
        </div>

        <div class="live-indicator">
            <span></span> LIVE
        </div>
    </div>

    <!-- KPI CARDS -->

    <div class="climate-kpis">

        <div class="climate-card">
            <div class="climate-icon">🌡️</div>
            <div>
                <span class="climate-label">Temperature</span>
                <strong>
                    <?= htmlspecialchars($climate_intelligence['current_conditions']['temperature']['value']) ?>°C
                </strong>
                <small>
                    <?= htmlspecialchars($climate_intelligence['current_conditions']['temperature']['status']) ?>
                </small>
            </div>
        </div>

        <div class="climate-card">
            <div class="climate-icon">💧</div>
            <div>
                <span class="climate-label">Humidity</span>
                <strong>
                    <?= htmlspecialchars($climate_intelligence['current_conditions']['humidity']['value']) ?>%
                </strong>
                <small>
                    <?= htmlspecialchars($climate_intelligence['current_conditions']['humidity']['status']) ?>
                </small>
            </div>
        </div>

        <div class="climate-card">
            <div class="climate-icon">💨</div>
            <div>
                <span class="climate-label">Airflow</span>
                <strong>
                    <?= htmlspecialchars($climate_intelligence['current_conditions']['wind']['value']) ?>
                    <small>m/s</small>
                </strong>
                <small>
                    <?= htmlspecialchars($climate_intelligence['current_conditions']['wind']['status']) ?>
                </small>
            </div>
        </div>

        <div class="climate-card health-card">
            <div class="climate-icon">🧠</div>
            <div>
                <span class="climate-label">Climate Health</span>
                <strong>
                    <?= htmlspecialchars($climate_intelligence['climate_health']['score']) ?>
                    <small>/100</small>
                </strong>
                <small>
                    <?= htmlspecialchars($climate_intelligence['climate_health']['status']) ?>
                </small>
            </div>
        </div>

    </div>


    <!-- RISK + INSIGHT -->

    <div class="intelligence-grid">

        <div class="risk-panel">

            <div class="panel-title">
                <span>Climate Risk</span>

                <span class="risk-badge">
                    <?= htmlspecialchars($climate_intelligence['risk']['level']) ?>
                </span>
            </div>

            <h3>
                <?= htmlspecialchars($climate_intelligence['risk']['primary_driver']) ?>
            </h3>

            <p>
                <?= htmlspecialchars($climate_intelligence['crop_intelligence']['concern']) ?>
            </p>

            <div class="recommendation">
                <strong>Recommended action</strong>

                <p>
                    <?= htmlspecialchars($climate_intelligence['crop_intelligence']['recommended_action']) ?>
                </p>
            </div>

        </div>


        <!-- WHAT CHANGED -->

        <div class="change-panel">

            <div class="panel-title">
                <span>What Changed?</span>
                <span>↗️</span>
            </div>

            <?php foreach ($climate_intelligence['what_changed'] as $change): ?>

                <div class="change-item">
                    <span>•</span>
                    <p><?= htmlspecialchars($change) ?></p>
                </div>

            <?php endforeach; ?>

        </div>

    </div>


    <!-- IRRIGATION -->

    <div class="irrigation-panel">

        <div>
            <span class="eyebrow">IRRIGATION INTELLIGENCE</span>

            <h3>
                <?= htmlspecialchars($climate_intelligence['irrigation']['status']) ?>
            </h3>

            <p>
                <?= htmlspecialchars($climate_intelligence['irrigation']['recommendation']) ?>
            </p>
        </div>

        <div class="irrigation-icon">
            💦
        </div>

    </div>


    <!-- EVIDENCE -->

    <div class="evidence-panel">

        <div class="panel-title">
            <span>Why BloomBot flagged this</span>
            <span>AI-ready intelligence</span>
        </div>

        <div class="evidence-grid">

            <?php foreach ($climate_intelligence['intelligence']['evidence'] as $evidence): ?>

                <div class="evidence-item">
                    <?= htmlspecialchars($evidence) ?>
                </div>

            <?php endforeach; ?>

        </div>

    </div>

</div>

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

    <h3>Recent Sensor Readings</h3>
    <?php if (!empty($sensor_data)): ?>
        <table border="1" width="100%" cellpadding="8" cellspacing="0">
            <tr style="background-color: #2c7a5d; color: white;">
                <th>Plant Name</th>
                <th>Temperature (°C)</th>
                <th>Moisture (%)</th>
                <th>Light Level (%)</th>
                <th>Timestamp</th>
            </tr>
            <?php foreach ($sensor_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['plant_name']) ?></td>
                    <td><?= htmlspecialchars($row['temperature']) ?></td>
                    <td><?= htmlspecialchars($row['moisture']) ?></td>
                    <td><?= htmlspecialchars($row['light_level']) ?></td>
                    <td><?= htmlspecialchars($row['timestamp']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No sensor data found for your plants.</p>
    <?php endif; ?>

    <div class="chart-box">
        <h3>Sensor Data Chart (Last 5 Readings)</h3>
        <canvas id="sensorChart" width="100%" height="40"></canvas>
    </div>

    <h2>Your Plants and Status</h2>
    <?php
    $plant_query = mysqli_query($conn, "SELECT * FROM plants WHERE gardener_username = '$username'");
    if (mysqli_num_rows($plant_query) > 0):
        while ($plant = mysqli_fetch_assoc($plant_query)):
            $plant_id = $plant['id'];
            $plant_name = $plant['name'];
            $sensor_sql = "SELECT * FROM sensor_data WHERE plant_id = $plant_id ORDER BY timestamp DESC LIMIT 1";
            $sensor_res = mysqli_query($conn, $sensor_sql);
            $sensor = mysqli_fetch_assoc($sensor_res);
            $threshold_sql = "SELECT * FROM thresholds WHERE plant_id = $plant_id LIMIT 1";
            $threshold_res = mysqli_query($conn, $threshold_sql);
            $plant_threshold = mysqli_fetch_assoc($threshold_res);

           
            $status = "Healthy";
            if ($sensor) {
                if ($plant_threshold) {
                    if ($sensor['moisture'] < $plant_threshold['moisture_min']) {
                        $status = "Needs Water";
                    } elseif ($sensor['temperature'] < $plant_threshold['temperature_min']) {
                        $status = "Too Cold";
                    } elseif ($sensor['temperature'] > $plant_threshold['temperature_max']) {
                        $status = "Too Hot";
                    } elseif ($sensor['light_level'] < $plant_threshold['light_min']) {
                        $status = "Too Dark";
                    } elseif ($sensor['light_level'] > $plant_threshold['light_max']) {
                        $status = "Too Bright";
                    }
                } else {
                    $status = "No Thresholds Set";
                }
            } else {
                $status = "No Sensor Data";
            }
    ?>
            <div style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
                <h3><?= htmlspecialchars($plant_name) ?></h3>
                <p><strong>Status:</strong> <?= $status ?></p>
                <?php if ($sensor): ?>
                    <p><strong>Last Reading:</strong> Temp: <?= $sensor['temperature'] ?>°C, Moisture: <?= $sensor['moisture'] ?>%, Light: <?= $sensor['light_level'] ?>%</p>
                <?php endif; ?>
            </div>
    <?php endwhile; else: ?>
        <p>You have no plants added yet.</p>
    <?php endif; ?>
</div>

<div id="popup-alert"style="display:none; position:fixed; bottom:20px; right:20px;background-color:red;  padding:15px; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.3); z-index:9999;">
    <span id="popup-message"></span>
</div>

</div><?php
$timestamps = [];
$temperatures = [];
$moistures = [];
$light_levels = [];

foreach ($sensor_data as $data) {
    $timestamps[] = $data['timestamp'];
    $temperatures[] = $data['temperature'];
    $moistures[] = $data['moisture'];
    $light_levels[] = $data['light_level'];
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
                label: 'Moisture (%)',
                data: <?= json_encode($moistures) ?>,
                borderColor: 'rgba(54, 162, 235, 1)',
                fill: false,
                tension: 0.3
            },
            {
                label: 'Light Level (%)',
                data: <?= json_encode($light_levels) ?>,
                borderColor: 'rgba(255, 206, 86, 1)',
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
                text: 'Latest Sensor Readings'
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
/*function simulateSensorData() {
    fetch('simulate_sensor_data.php')
        .then(response => response.json())
        .then(data => console.log("✅ " + data.message))
        .catch(error => console.error("❌ Error simulating sensor data:", error));
}
simulateSensorData();
setInterval(simulateSensorData, 5 * 60 * 1000);*/
</script>
</body>
</html>

<?php

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bloombot.';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['username'])) {
    die("Unauthorized. Please log in.");
}

$gardener_username = $_SESSION['username'];
/*
|--------------------------------------------------------------------------
| GEOCODE PLANT LOCATION
|--------------------------------------------------------------------------
| Converts County + Town into coordinates automatically.
*/

function geocodeLocation($town, $county)
{
    $query = urlencode($town . ', ' . $county . ', Kenya');

    $url = "https://nominatim.openstreetmap.org/search"
         . "?q={$query}"
         . "&format=jsonv2"
         . "&limit=1"
         . "&countrycodes=ke";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'User-Agent: BloomBot-Climate-Intelligence/1.0'
        ]
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $results = json_decode($response, true);

    if (
        !is_array($results) ||
        empty($results) ||
        !isset($results[0]['lat']) ||
        !isset($results[0]['lon'])
    ) {
        return null;
    }

    return [
        'latitude' => (float)$results[0]['lat'],
        'longitude' => (float)$results[0]['lon']
    ];
}


/*
|--------------------------------------------------------------------------
| HANDLE PLANT CREATION
|--------------------------------------------------------------------------
*/

if (isset($_POST['submit'])) {

    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? '');

    // Physical plant location
    $location = trim($_POST['location'] ?? '');

    // Geographic location
    $county = trim($_POST['county'] ?? '');
    $town = trim($_POST['town'] ?? '');

    // Monitoring configuration
    $monitoring_mode = $_POST['monitoring_mode'] ?? 'environmental_station';

    if ($monitoring_mode === 'plant_sensor') {
        $monitoring_source = 'Plant Sensor';
    } else {
        $monitoring_source = 'JKUAT Conduit Environmental Station';
    }

   /*
|--------------------------------------------------------------------------
| RESOLVE LOCATION
|--------------------------------------------------------------------------
*/

$coordinates = geocodeLocation($town, $county);

if ($coordinates) {

    $latitude = $coordinates['latitude'];
    $longitude = $coordinates['longitude'];

} else {

    $latitude = null;
    $longitude = null;
}

    $moisture_level = trim($_POST['moisture_level'] ?? '');
    $temperature = trim($_POST['temperature'] ?? '');
    $light_requirement = trim($_POST['light_requirement'] ?? '');

    $planted_date = $_POST['planted_date'] ?? '';
    $status = trim($_POST['status'] ?? '');
    $notes = trim($_POST['notes'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        empty($name) ||
        empty($type) ||
        empty($location) ||
        empty($county) ||
        empty($town) ||
        empty($moisture_level) ||
        empty($temperature) ||
        empty($light_requirement) ||
        empty($planted_date) ||
        empty($status)
    ) {

        echo "<script>
                alert('Please fill all required fields.');
                window.history.back();
              </script>";

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | INSERT PLANT
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO plants
        (
            name,
            type,
            location,
            county,
            town,
            monitoring_mode,
            monitoring_source,
            latitude,
            longitude,
            moisture_level,
            temperature,
            light_requirement,
            planted_date,
            status,
            notes,
            gardener_username
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        die("Database preparation failed: " . $conn->error);
    }


    $stmt->bind_param(
        "sssssssddsssssss",
        $name,
        $type,
        $location,
        $county,
        $town,
        $monitoring_mode,
        $monitoring_source,
        $latitude,
        $longitude,
        $moisture_level,
        $temperature,
        $light_requirement,
        $planted_date,
        $status,
        $notes,
        $gardener_username
    );


    /*
    |--------------------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------------------
    */

    if ($stmt->execute()) {

        $new_plant_id = $stmt->insert_id;

        $stmt->close();
        $conn->close();

        echo "<script>
                alert('Plant added successfully! BloomBot will use the selected monitoring source and location.');
                window.location.href='gardener_dashboard%20.php';
              </script>";

        exit;

    } else {

        $error = htmlspecialchars($stmt->error);

        $stmt->close();
        $conn->close();

        echo "<script>
                alert('Error adding plant: " . $error . "');
                window.history.back();
              </script>";

        exit;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Plant | BloomBot</title>

    <link
        rel="stylesheet"
        href="CSS/style.css?v=7"
    >

</head>


<body>

<div class="container">

    <h2>Add New Plant</h2>

    <p>
        Add your plant profile below. BloomBot will use live environmental
        conditions from the climate intelligence engine to assess the plant.
    </p>


    <form
        method="POST"
        action="add_plant.php"
    >

        <label>Plant Name</label>

        <input
            type="text"
            name="name"
            placeholder="e.g. Tomato A"
            required
        >


        <label>Plant Type</label>

        <input
            type="text"
            name="type"
            placeholder="e.g. Tomato"
            required
        >


       <label>Farm / Greenhouse / Plot</label>

<input
    type="text"
    name="location"
    placeholder="e.g. Greenhouse 1"
    required
>


<label>County</label>

<select name="county" required>

    <option value="">Select County</option>

    <option value="Nairobi">Nairobi</option>
    <option value="Kiambu">Kiambu</option>
    <option value="Kajiado">Kajiado</option>
    <option value="Machakos">Machakos</option>
    <option value="Nakuru">Nakuru</option>
    <option value="Uasin Gishu">Uasin Gishu</option>
    <option value="Trans Nzoia">Trans Nzoia</option>
    <option value="Kakamega">Kakamega</option>
    <option value="Kisumu">Kisumu</option>
    <option value="Mombasa">Mombasa</option>
    <option value="Other">Other</option>

</select>


<label>Town / City</label>

<input
    type="text"
    name="town"
    placeholder="e.g. Kitale"
    required
>


<label>Monitoring Method</label>

<select name="monitoring_mode" required>

    <option value="environmental_station">
        Environmental Station
    </option>

    <option value="plant_sensor">
        Plant Sensor
    </option>

</select>

<small>
    Choose Plant Sensor only if this plant has its own connected sensor.
    Environmental Station uses nearby environmental monitoring data.
</small>

        <label>Moisture Level</label>

        <input
            type="number"
            name="moisture_level"
            placeholder="Target moisture level"
            required
        >


        <label>Temperature</label>

        <input
            type="number"
            name="temperature"
            placeholder="Preferred temperature"
            required
        >


        <label>Light Requirement</label>

        <input
            type="text"
            name="light_requirement"
            placeholder="e.g. High"
            required
        >


        <label>Planted Date</label>

        <input
            type="date"
            name="planted_date"
            required
        >


        <label>Status</label>

        <input
            type="text"
            name="status"
            value="Healthy"
            required
        >


        <label>Notes</label>

        <textarea
            name="notes"
            rows="4"
            placeholder="Optional notes about this plant..."
        ></textarea>


        <button
            type="submit"
            name="submit"
        >
            Add Plant
        </button>

    </form>


    <a
        href="set_threshold.php"
        class="edit-button"
    >
        Set Plant Thresholds
    </a>

</div>

</body>

</html>
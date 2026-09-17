<?php

/*
|--------------------------------------------------------------------------
| BLOOMBOT NOTIFICATION ENGINE
|--------------------------------------------------------------------------
|
| Creates gardener notifications from meaningful climate/plant events.
| Prevents duplicate notifications within a 60-minute cooldown period.
|
*/

function createBloomBotNotification(
    mysqli $conn,
    int $plant_id,
    string $message,
    ?string $email = null
): bool {

    /*
    |--------------------------------------------------------------------------
    | CHECK FOR RECENT DUPLICATE
    |--------------------------------------------------------------------------
    */

    $check_sql = "
        SELECT id
        FROM notifications
        WHERE plant_id = ?
        AND message = ?
        AND timestamp >= (NOW() - INTERVAL 60 MINUTE)
        LIMIT 1
    ";

    $check = $conn->prepare($check_sql);

    if (!$check) {
        return false;
    }

    $check->bind_param(
        "is",
        $plant_id,
        $message
    );

    $check->execute();

    $result = $check->get_result();

    if ($result && $result->num_rows > 0) {

        $check->close();

        return false;
    }

    $check->close();


    /*
    |--------------------------------------------------------------------------
    | CREATE NOTIFICATION
    |--------------------------------------------------------------------------
    */

    $insert_sql = "
        INSERT INTO notifications
        (plant_id, email, message, status)
        VALUES (?, ?, ?, 'unread')
    ";

    $insert = $conn->prepare($insert_sql);

    if (!$insert) {
        return false;
    }

    $insert->bind_param(
        "iss",
        $plant_id,
        $email,
        $message
    );

    $success = $insert->execute();

    $insert->close();

    return $success;
}


/*
|--------------------------------------------------------------------------
| GENERATE NOTIFICATIONS FROM FINAL PLANT ASSESSMENT
|--------------------------------------------------------------------------
*/

function generateClimateNotifications(
    mysqli $conn,
    array $climate_intelligence,
    int $plant_id,
    string $plant_name,
    string $plant_status,
    string $assessment_message,
    string $recommended_action,
    ?string $email = null,
    bool $has_sensor = false
): array {

    $created = 0;
    $events = [];


    /*
    |--------------------------------------------------------------------------
    | 1. PLANT SENSOR — NEEDS WATER
    |--------------------------------------------------------------------------
    */

    if ($has_sensor && $plant_status === "Needs Water") {

        $message =
            "Watering concern for {$plant_name}: " .
            $assessment_message .
            " " .
            $recommended_action;

        $events[] = $message;
    }


    /*
    |--------------------------------------------------------------------------
    | 2. TOO HOT
    |--------------------------------------------------------------------------
    */

    if ($plant_status === "Too Hot") {

        $message =
            "Heat stress concern for {$plant_name}: " .
            $assessment_message .
            " " .
            $recommended_action;

        $events[] = $message;
    }


    /*
    |--------------------------------------------------------------------------
    | 3. TOO COLD
    |--------------------------------------------------------------------------
    */

    if ($plant_status === "Too Cold") {

        $message =
            "Low temperature concern for {$plant_name}: " .
            $assessment_message .
            " " .
            $recommended_action;

        $events[] = $message;
    }


    /*
    |--------------------------------------------------------------------------
    | 4. HUMIDITY + LOW AIRFLOW
    |--------------------------------------------------------------------------
    */

    $current_conditions =
        $climate_intelligence['current_conditions'] ?? [];

    $humidity =
        $current_conditions['humidity']['value'] ?? null;

    $wind =
        $current_conditions['wind']['value'] ?? null;


    if (
        !$has_sensor &&
        is_numeric($humidity) &&
        is_numeric($wind) &&
        $humidity >= 80 &&
        $wind <= 1
    ) {

        $message =
            "Humidity risk for {$plant_name}: " .
            "humidity is currently " .
            round((float)$humidity, 1) .
            "% while airflow is very low at " .
            round((float)$wind, 2) .
            " m/s. " .
            "Monitor foliage closely and improve airflow where possible.";

        $events[] = $message;
    }


    /*
    |--------------------------------------------------------------------------
    | 5. HIGH / CRITICAL CLIMATE RISK
    |--------------------------------------------------------------------------
    */

    $risk =
        $climate_intelligence['risk'] ?? [];

    $risk_level =
        $risk['level'] ?? '';

    if (
        !$has_sensor &&
        ($risk_level === "High" || $risk_level === "Critical")
    ) {

        $driver =
            $risk['primary_driver']
            ?? 'Multiple climate factors';

        $message =
            "Climate risk alert for {$plant_name}: " .
            "BloomBot has detected {$risk_level} environmental risk. " .
            "Primary driver: {$driver}. " .
            "Review the recommended action.";

        $events[] = $message;
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE EVENTS
    |--------------------------------------------------------------------------
    */

    foreach ($events as $message) {

        if (
            createBloomBotNotification(
                $conn,
                $plant_id,
                $message,
                $email
            )
        ) {

            $created++;
        }
    }


    return [
        'detected' => count($events),
        'created' => $created
    ];
}

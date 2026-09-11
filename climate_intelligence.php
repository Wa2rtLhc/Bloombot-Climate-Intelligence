<?php

/**
 * ============================================================
 * BLOOMBOT CLIMATE INTELLIGENCE ENGINE
 * VERSION 3.0
 * ============================================================
 *
 * Live data source:
 * JKUAT Conduit
 *
 * Pipeline:
 *
 * Live observations
 *       ↓
 * Climate analysis
 *       ↓
 * Baseline + trends + persistence
 *       ↓
 * Climate Health Score
 *       ↓
 * Crop intelligence
 *       ↓
 * Actionable recommendations
 *
 * NOTE:
 * Rain-gauge fields are intentionally NOT interpreted as
 * millimetres until their Conduit units are verified.
 */


header('Content-Type: application/json');


// ============================================================
// 1. LOAD ENVIRONMENT
// ============================================================

$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {

    echo json_encode([
        "status" => "error",
        "message" => ".env file not found"
    ], JSON_PRETTY_PRINT);

    exit;
}

$env = parse_ini_file($envFile);

$apiKey = $env['CONDUIT_API_KEY'] ?? '';
$email  = $env['CONDUIT_EMAIL'] ?? '';

if (empty($apiKey) || empty($email)) {

    echo json_encode([
        "status" => "error",
        "message" => "Conduit API credentials are missing"
    ], JSON_PRETTY_PRINT);

    exit;
}


// ============================================================
// 2. DATE RANGE
// ============================================================

$today = date('Y-m-d');

$yesterday = date(
    'Y-m-d',
    strtotime('-1 day')
);


// ============================================================
// 3. REQUEST CONDUIT DATA
// ============================================================

$url = "https://conduit.jhubafrica.com/data.php";

$postData = [

    'apikey'   => $apiKey,

    'email'    => $email,

    'fromdate' => $yesterday,

    'todate'   => $today
];


$ch = curl_init($url);

curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_POSTFIELDS =>
        http_build_query($postData),

    CURLOPT_TIMEOUT => 30,

    CURLOPT_HTTPHEADER => [

        'Content-Type: application/x-www-form-urlencoded'

    ]

]);


$response = curl_exec($ch);


if ($response === false) {

    echo json_encode([

        "status" => "error",

        "message" =>
            "Unable to connect to Conduit",

        "error" =>
            curl_error($ch)

    ], JSON_PRETTY_PRINT);

    curl_close($ch);

    exit;
}


$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);


// ============================================================
// 4. DECODE RESPONSE
// ============================================================

$data = json_decode(
    $response,
    true
);


if ($httpCode !== 200 || !is_array($data)) {

    echo json_encode([

        "status" => "error",

        "message" =>
            "Invalid Conduit response",

        "http_code" =>
            $httpCode

    ], JSON_PRETTY_PRINT);

    exit;
}


if (($data['status'] ?? '') !== 'success') {

    echo json_encode([

        "status" => "error",

        "message" =>
            "Conduit returned an unsuccessful response",

        "response" =>
            $data

    ], JSON_PRETTY_PRINT);

    exit;
}


$observations =
    $data['data'] ?? [];


if (empty($observations)) {

    echo json_encode([

        "status" => "error",

        "message" =>
            "No observations available"

    ], JSON_PRETTY_PRINT);

    exit;
}


// ============================================================
// 5. SORT OBSERVATIONS
// ============================================================

usort(

    $observations,

    function ($a, $b) {

        return
            strtotime($b['ts'] ?? '') <=>
            strtotime($a['ts'] ?? '');
    }

);


// ============================================================
// 6. HELPER FUNCTIONS
// ============================================================

function numericValue($value)
{
    return is_numeric($value)
        ? (float)$value
        : null;
}


function calculateAverage($values)
{
    $values = array_filter(
        $values,
        fn($value) =>
            $value !== null
    );

    if (count($values) === 0) {

        return null;
    }

    return
        array_sum($values) /
        count($values);
}


function calculateTrend($values)
{
    $values = array_values(

        array_filter(
            $values,
            fn($value) =>
                $value !== null
        )

    );


    if (count($values) < 4) {

        return [

            "direction" =>
                "Insufficient Data",

            "change" =>
                null
        ];
    }


    $half =
        floor(count($values) / 2);


    $recent =
        array_slice(
            $values,
            0,
            $half
        );


    $older =
        array_slice(
            $values,
            $half
        );


    $recentAverage =
        calculateAverage($recent);


    $olderAverage =
        calculateAverage($older);


    $change =
        $recentAverage -
        $olderAverage;


    if (abs($change) < 0.5) {

        $direction =
            "Stable";

    } elseif ($change > 0) {

        $direction =
            "Increasing";

    } else {

        $direction =
            "Decreasing";
    }


    return [

        "direction" =>
            $direction,

        "change" =>
            round($change, 2)
    ];
}


// ============================================================
// 7. EXTRACT VARIABLES
// ============================================================

$temperatures = [];

$humidities = [];

$winds = [];

$heatIndexes = [];

$wetBulbs = [];

$wetBulbGlobes = [];

$solarVisible = [];

$solarInfrared = [];

$uvValues = [];


foreach ($observations as $observation) {

    $temperatures[] =
        numericValue(
            $observation['temp_bmx'] ?? null
        );


    $humidities[] =
        numericValue(
            $observation['humidity_sht'] ?? null
        );


    $winds[] =
        numericValue(
            $observation['wind_spd'] ?? null
        );


    $heatIndexes[] =
        numericValue(
            $observation['heat_idx'] ?? null
        );


    $wetBulbs[] =
        numericValue(
            $observation['wet_bulb_temp'] ?? null
        );


    $wetBulbGlobes[] =
        numericValue(
            $observation['wet_bulb_globe_temp'] ?? null
        );


    $solarVisible[] =
        numericValue(
            $observation['si1145_vis'] ?? null
        );


    $solarInfrared[] =
        numericValue(
            $observation['si1145_ir'] ?? null
        );


    $uvValues[] =
        numericValue(
            $observation['si1145_uv'] ?? null
        );
}


// ============================================================
// 8. CURRENT CONDITIONS
// ============================================================

$current =
    $observations[0];


$temperature =
    numericValue(
        $current['temp_bmx'] ?? null
    );


$humidity =
    numericValue(
        $current['humidity_sht'] ?? null
    );


$wind =
    numericValue(
        $current['wind_spd'] ?? null
    );


$heatIndex =
    numericValue(
        $current['heat_idx'] ?? null
    );


$wetBulb =
    numericValue(
        $current['wet_bulb_temp'] ?? null
    );


$wetBulbGlobe =
    numericValue(
        $current['wet_bulb_globe_temp'] ?? null
    );


$solar =
    numericValue(
        $current['si1145_vis'] ?? null
    );


$infrared =
    numericValue(
        $current['si1145_ir'] ?? null
    );


$uv =
    numericValue(
        $current['si1145_uv'] ?? null
    );


// ============================================================
// 9. BASELINES
// ============================================================

$temperatureAverage =
    calculateAverage(
        $temperatures
    );


$humidityAverage =
    calculateAverage(
        $humidities
    );


$windAverage =
    calculateAverage(
        $winds
    );


$heatAverage =
    calculateAverage(
        $heatIndexes
    );


// Differences from baseline

$temperatureDifference =
    $temperature !== null &&
    $temperatureAverage !== null

        ? $temperature -
          $temperatureAverage

        : null;


$humidityDifference =
    $humidity !== null &&
    $humidityAverage !== null

        ? $humidity -
          $humidityAverage

        : null;


// ============================================================
// 10. TRENDS
// ============================================================

$temperatureTrend =
    calculateTrend(
        $temperatures
    );


$humidityTrend =
    calculateTrend(
        $humidities
    );


$windTrend =
    calculateTrend(
        $winds
    );


// ============================================================
// 11. PERSISTENCE
// ============================================================

$total =
    count($observations);


$highHumidityCount = 0;

$veryHighHumidityCount = 0;

$lowWindCount = 0;


foreach ($humidities as $value) {

    if ($value === null) {

        continue;
    }


    if ($value >= 80) {

        $highHumidityCount++;
    }


    if ($value >= 90) {

        $veryHighHumidityCount++;
    }
}


foreach ($winds as $value) {

    if ($value === null) {

        continue;
    }


    if ($value <= 1) {

        $lowWindCount++;
    }
}


$highHumidityPercentage =
    $total > 0

        ? ($highHumidityCount /
           $total) * 100

        : 0;


$veryHighHumidityPercentage =
    $total > 0

        ? ($veryHighHumidityCount /
           $total) * 100

        : 0;


$lowWindPercentage =
    $total > 0

        ? ($lowWindCount /
           $total) * 100

        : 0;


// ============================================================
// 12. CURRENT STATUS
// ============================================================

if ($temperature === null) {

    $temperatureStatus =
        "Unknown";

} elseif ($temperature < 10) {

    $temperatureStatus =
        "Cold";

} elseif ($temperature <= 28) {

    $temperatureStatus =
        "Favorable";

} elseif ($temperature <= 32) {

    $temperatureStatus =
        "Warm";

} else {

    $temperatureStatus =
        "Hot";
}


if ($humidity === null) {

    $humidityStatus =
        "Unknown";

} elseif ($humidity < 40) {

    $humidityStatus =
        "Low";

} elseif ($humidity <= 70) {

    $humidityStatus =
        "Comfortable";

} elseif ($humidity < 85) {

    $humidityStatus =
        "High";

} else {

    $humidityStatus =
        "Very High";
}


if ($wind === null) {

    $windStatus =
        "Unknown";

} elseif ($wind < 1) {

    $windStatus =
        "Very Low";

} elseif ($wind < 4) {

    $windStatus =
        "Moderate";

} else {

    $windStatus =
        "Strong";
}


if ($heatIndex === null) {

    $heatStatus =
        "Unknown";

} elseif ($heatIndex < 27) {

    $heatStatus =
        "Low";

} elseif ($heatIndex < 32) {

    $heatStatus =
        "Moderate";

} elseif ($heatIndex < 41) {

    $heatStatus =
        "High";

} else {

    $heatStatus =
        "Very High";
}


// ============================================================
// 13. CLIMATE HEALTH SCORE
// ============================================================

/*
 * Start with a perfect score.
 *
 * Points are deducted when conditions indicate
 * increased environmental stress.
 */

$climateScore = 100;

$scoreReasons = [];


// High humidity

if ($humidity !== null && $humidity >= 80) {

    $climateScore -= 12;

    $scoreReasons[] =
        "High humidity";
}


// Persistent humidity

if ($highHumidityPercentage >= 50) {

    $climateScore -= 10;

    $scoreReasons[] =
        "Frequent high humidity";
}


// Very high humidity

if ($veryHighHumidityPercentage >= 30) {

    $climateScore -= 8;

    $scoreReasons[] =
        "Repeated very high humidity";
}


// Low airflow

if ($wind !== null && $wind <= 1) {

    $climateScore -= 8;

    $scoreReasons[] =
        "Very low current airflow";
}


// Persistent low airflow

if ($lowWindPercentage >= 70) {

    $climateScore -= 10;

    $scoreReasons[] =
        "Persistent low airflow";
}


// Heat

if ($heatIndex !== null && $heatIndex >= 32) {

    $climateScore -= 15;

    $scoreReasons[] =
        "Elevated heat conditions";
}


// Strong wind

if ($wind !== null && $wind >= 8) {

    $climateScore -= 8;

    $scoreReasons[] =
        "Strong wind";
}


// Keep score between 0 and 100

$climateScore =
    max(
        0,
        min(
            100,
            $climateScore
        )
    );


// Score interpretation

if ($climateScore >= 80) {

    $climateHealth =
        "Good";

} elseif ($climateScore >= 60) {

    $climateHealth =
        "Moderate";

} elseif ($climateScore >= 40) {

    $climateHealth =
        "Warning";

} else {

    $climateHealth =
        "Critical";
}


// ============================================================
// 14. RISK SCORING
// ============================================================

$riskScore = 0;

$riskFactors = [];


// Humidity

if ($humidity !== null && $humidity >= 80) {

    $riskScore += 2;

    $riskFactors[] =
        "High humidity";
}


// Persistent humidity

if ($highHumidityPercentage >= 50) {

    $riskScore += 2;

    $riskFactors[] =
        "Frequent high humidity";
}


// Humidity + low airflow

if (
    $humidity !== null &&
    $humidity >= 80 &&
    $wind !== null &&
    $wind <= 1
) {

    $riskScore += 2;

    $riskFactors[] =
        "High humidity with very low airflow";
}


// Persistent low airflow

if ($lowWindPercentage >= 70) {

    $riskScore += 1;

    $riskFactors[] =
        "Persistent low airflow";
}


// Heat

if ($heatIndex !== null && $heatIndex >= 32) {

    $riskScore += 3;

    $riskFactors[] =
        "Elevated heat index";
}


// Strong wind

if ($wind !== null && $wind >= 8) {

    $riskScore += 1;

    $riskFactors[] =
        "Strong wind";
}


// Risk level

if ($riskScore <= 2) {

    $riskLevel =
        "Low";

} elseif ($riskScore <= 5) {

    $riskLevel =
        "Moderate";

} elseif ($riskScore <= 8) {

    $riskLevel =
        "High";

} else {

    $riskLevel =
        "Critical";
}


// ============================================================
// 15. PRIMARY DRIVER
// ============================================================

$primaryDriver =
    "Stable conditions";


if (
    $humidity !== null &&
    $humidity >= 80 &&
    $wind !== null &&
    $wind <= 1
) {

    $primaryDriver =
        "Humidity + Low Airflow";

} elseif (
    $humidity !== null &&
    $humidity >= 80
) {

    $primaryDriver =
        "Humidity";

} elseif (
    $heatIndex !== null &&
    $heatIndex >= 32
) {

    $primaryDriver =
        "Heat Stress";

} elseif (
    $wind !== null &&
    $wind >= 8
) {

    $primaryDriver =
        "Strong Wind";
}


// ============================================================
// 16. "WHAT CHANGED?" ANALYSIS
// ============================================================

$changes = [];


if (
    $temperatureDifference !== null &&
    abs($temperatureDifference) >= 1
) {

    if ($temperatureDifference > 0) {

        $changes[] =
            "Temperature is currently " .
            round(
                $temperatureDifference,
                1
            ) .
            "°C above the recent average.";

    } else {

        $changes[] =
            "Temperature is currently " .
            round(
                abs($temperatureDifference),
                1
            ) .
            "°C below the recent average.";
    }
}


if (
    $humidityDifference !== null &&
    abs($humidityDifference) >= 5
) {

    if ($humidityDifference > 0) {

        $changes[] =
            "Humidity is currently " .
            round(
                $humidityDifference,
                1
            ) .
            " percentage points above the recent average.";

    } else {

        $changes[] =
            "Humidity is currently " .
            round(
                abs($humidityDifference),
                1
            ) .
            " percentage points below the recent average.";
    }
}


if (
    $temperatureTrend['direction'] ===
    "Increasing"
) {

    $changes[] =
        "Temperature is trending upward.";
}


if (
    $humidityTrend['direction'] ===
    "Increasing"
) {

    $changes[] =
        "Humidity is trending upward.";
}


if (
    $humidityTrend['direction'] ===
    "Decreasing"
) {

    $changes[] =
        "Humidity is trending downward.";
}


if (empty($changes)) {

    $changes[] =
        "No major climate shift was detected in the available observations.";
}


// ============================================================
// 17. CROP INTELLIGENCE
// ============================================================

/*
 * We currently don't know which crop the gardener has selected.
 *
 * Therefore V3 creates a general crop-risk interpretation.
 *
 * Once connected to the BloomBot plants table,
 * this can become crop-specific.
 */

$cropRisk =
    "Low";


$cropConcern =
    "No major environmental stress detected.";


$cropAction =
    "Continue normal monitoring.";


if (
    $humidity !== null &&
    $humidity >= 80 &&
    $wind !== null &&
    $wind <= 1
) {

    $cropRisk =
        "Moderate";


    $cropConcern =
        "High humidity combined with low airflow may increase moisture retention around crop foliage.";


    $cropAction =
        "Monitor foliage closely and improve airflow around crops where possible.";
}


if (
    $heatIndex !== null &&
    $heatIndex >= 32
) {

    $cropRisk =
        "High";


    $cropConcern =
        "Elevated heat conditions may increase crop water demand and heat stress.";


    $cropAction =
        "Monitor crop water requirements and signs of heat stress.";
}


// ============================================================
// 18. IRRIGATION INTELLIGENCE
// ============================================================

$irrigationStatus =
    "Monitor";


$irrigationRecommendation =
    "Use soil moisture readings and crop requirements before irrigating.";


if (
    $humidity !== null &&
    $humidity >= 80 &&
    $heatIndex !== null &&
    $heatIndex < 30
) {

    $irrigationStatus =
        "Review";


    $irrigationRecommendation =
        "Avoid automatically increasing irrigation solely because of climate conditions; check soil moisture first.";
}


if (
    $heatIndex !== null &&
    $heatIndex >= 32
) {

    $irrigationStatus =
        "High Attention";


    $irrigationRecommendation =
        "Check soil moisture more frequently because elevated heat may increase water demand.";
}


// ============================================================
// 19. INTELLIGENCE INSIGHTS
// ============================================================

$insights = [];


// Humidity

if (
    $humidity !== null &&
    $humidity >= 80 &&
    $wind !== null &&
    $wind <= 1
) {

    $insights[] =
        "High humidity is currently occurring alongside very low airflow.";

}


elseif (
    $humidity !== null &&
    $humidity >= 80
) {

    $insights[] =
        "Current humidity is high and may increase moisture retention around crops.";
}


// Temperature trend

if (
    $temperatureTrend['direction'] ===
    "Increasing"
) {

    $insights[] =
        "Temperature is trending upward compared with the earlier observations.";
}


if (
    $temperatureTrend['direction'] ===
    "Decreasing"
) {

    $insights[] =
        "Temperature is trending downward compared with the earlier observations.";
}


// Heat

if (
    $heatIndex !== null &&
    $heatIndex >= 32
) {

    $insights[] =
        "Elevated heat conditions have been detected.";
}


// Low airflow persistence

if ($lowWindPercentage >= 70) {

    $insights[] =
        "Low airflow has been persistent across most available observations.";
}


// Fallback

if (empty($insights)) {

    $insights[] =
        "Current environmental conditions appear relatively stable.";
}


// ============================================================
// 20. RECOMMENDATIONS
// ============================================================

$recommendations = [];


if (
    $humidity !== null &&
    $humidity >= 80 &&
    $wind !== null &&
    $wind <= 1
) {

    $recommendations[] =
        "Monitor crop foliage and maintain airflow where possible.";
}


if (
    $temperatureTrend['direction'] ===
    "Increasing"
) {

    $recommendations[] =
        "Monitor irrigation demand as temperature increases.";
}


if (
    $heatIndex !== null &&
    $heatIndex >= 32
) {

    $recommendations[] =
        "Monitor crops for signs of heat stress and check soil moisture more frequently.";
}


if (
    $wind !== null &&
    $wind >= 8
) {

    $recommendations[] =
        "Monitor exposed crops and irrigation efficiency during strong winds.";
}


if (empty($recommendations)) {

    $recommendations[] =
        "Continue normal monitoring and compare future readings with the current baseline.";
}


// ============================================================
// 21. EXPLAINABLE EVIDENCE
// ============================================================

$evidence = [];


if ($humidity !== null) {

    $evidence[] =
        "Current humidity: " .
        $humidity .
        "%";
}


if ($wind !== null) {

    $evidence[] =
        "Current wind speed: " .
        $wind .
        " m/s";
}


if ($humidityAverage !== null) {

    $evidence[] =
        "Recent average humidity: " .
        round(
            $humidityAverage,
            2
        ) .
        "%";
}


if ($temperatureAverage !== null) {

    $evidence[] =
        "Recent average temperature: " .
        round(
            $temperatureAverage,
            2
        ) .
        "°C";
}


$evidence[] =
    "Low airflow observed in " .
    round(
        $lowWindPercentage,
        1
    ) .
    "% of observations";


// ============================================================
// 22. FINAL RESPONSE
// ============================================================

$output = [

    "status" =>
        "success",


    "engine" => [

        "name" =>
            "BloomBot Climate Intelligence",

        "version" =>
            "3.0",

        "data_source" =>
            "JKUAT Conduit",

        "observations_analyzed" =>
            $total
    ],


    "timestamp" =>
        $current['ts'] ?? null,


    "current_conditions" => [

        "temperature" => [

            "value" =>
                $temperature,

            "unit" =>
                "°C",

            "status" =>
                $temperatureStatus
        ],


        "humidity" => [

            "value" =>
                $humidity,

            "unit" =>
                "%",

            "status" =>
                $humidityStatus
        ],


        "wind" => [

            "value" =>
                $wind,

            "unit" =>
                "m/s",

            "status" =>
                $windStatus
        ],


        "heat_index" => [

            "value" =>
                $heatIndex,

            "unit" =>
                "°C",

            "status" =>
                $heatStatus
        ],


        "wet_bulb" => [

            "value" =>
                $wetBulb,

            "unit" =>
                "°C"
        ],


        "wet_bulb_globe" => [

            "value" =>
                $wetBulbGlobe,

            "unit" =>
                "°C"
        ]
    ],


    "climate_health" => [

        "score" =>
            $climateScore,

        "status" =>
            $climateHealth,

        "reasons" =>
            $scoreReasons
    ],


    "baseline" => [

        "temperature_average" =>
            $temperatureAverage !== null
                ? round(
                    $temperatureAverage,
                    2
                )
                : null,


        "humidity_average" =>
            $humidityAverage !== null
                ? round(
                    $humidityAverage,
                    2
                )
                : null,


        "wind_average" =>
            $windAverage !== null
                ? round(
                    $windAverage,
                    2
                )
                : null,


        "heat_index_average" =>
            $heatAverage !== null
                ? round(
                    $heatAverage,
                    2
                )
                : null
    ],


    "trends" => [

        "temperature" =>
            $temperatureTrend,

        "humidity" =>
            $humidityTrend,

        "wind" =>
            $windTrend
    ],


    "persistence" => [

        "high_humidity_percentage" =>
            round(
                $highHumidityPercentage,
                1
            ),

        "very_high_humidity_percentage" =>
            round(
                $veryHighHumidityPercentage,
                1
            ),

        "low_wind_percentage" =>
            round(
                $lowWindPercentage,
                1
            )
    ],


    "what_changed" =>
        $changes,


    "risk" => [

        "score" =>
            $riskScore,

        "level" =>
            $riskLevel,

        "primary_driver" =>
            $primaryDriver,

        "factors" =>
            $riskFactors
    ],


    "crop_intelligence" => [

        "risk" =>
            $cropRisk,

        "concern" =>
            $cropConcern,

        "recommended_action" =>
            $cropAction
    ],


    "irrigation" => [

        "status" =>
            $irrigationStatus,

        "recommendation" =>
            $irrigationRecommendation
    ],


    "intelligence" => [

        "insights" =>
            $insights,

        "recommendations" =>
            $recommendations,

        "evidence" =>
            $evidence
    ],


    "solar" => [

        "visible" =>
            $solar,

        "infrared" =>
            $infrared,

        "uv" =>
            $uv
    ],


    "data_quality" => [

        "rainfall_interpretation" =>
            "Pending Conduit field validation",

        "ml_prediction" =>
            "Not yet enabled",

        "note" =>
            "Current intelligence uses transparent rule-based analysis of live environmental observations."
    ]

];


echo json_encode(
    $output,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_UNICODE
);
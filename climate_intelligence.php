<?php

/**
 * ============================================================
 * BLOOMBOT CLIMATE INTELLIGENCE ENGINE
 * VERSION 3.1 - LOCATION AWARE
 * ============================================================
 *
 * Data pipeline:
 *
 * Plant location
 *       ↓
 * Climate Data Provider
 *       ↓
 * Climate Source Manager
 *       ↓
 * ┌───────────────────────────────┐
 * │ Plant Sensor                  │
 * │ Nearby Environmental Station  │
 * │ Location-based Weather Model  │
 * └───────────────────────────────┘
 *       ↓
 * Normalized observations
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
 * IMPORTANT:
 * This engine does NOT call JKUAT Conduit directly anymore.
 *
 * The climate_data_provider.php file decides which source
 * should be used based on the plant's location.
 */


header('Content-Type: application/json');


// ============================================================
// 1. INPUT
// ============================================================

$latitude = isset($_GET['latitude'])
    ? (float) $_GET['latitude']
    : null;

$longitude = isset($_GET['longitude'])
    ? (float) $_GET['longitude']
    : null;

$monitoringMode =
    $_GET['monitoring_mode']
    ?? 'environmental_station';


// ------------------------------------------------------------
// VALIDATE LOCATION
// ------------------------------------------------------------

if ($latitude === null || $longitude === null) {

    echo json_encode([

        "status" => "error",

        "message" =>
            "Latitude and longitude are required."

    ], JSON_PRETTY_PRINT);

    exit;
}


if ($latitude < -90 || $latitude > 90) {

    echo json_encode([

        "status" => "error",

        "message" =>
            "Invalid latitude."

    ], JSON_PRETTY_PRINT);

    exit;
}


if ($longitude < -180 || $longitude > 180) {

    echo json_encode([

        "status" => "error",

        "message" =>
            "Invalid longitude."

    ], JSON_PRETTY_PRINT);

    exit;
}


// ============================================================
// 2. REQUEST UNIFIED CLIMATE DATA
// ============================================================

$providerUrl =
    'http://localhost/Bloombot/climate_data_provider.php'
    . '?latitude=' . urlencode($latitude)
    . '&longitude=' . urlencode($longitude)
    . '&monitoring_mode=' . urlencode($monitoringMode);


$providerResponse =
    @file_get_contents($providerUrl);


if ($providerResponse === false) {

    echo json_encode([

        "status" => "error",

        "message" =>
            "Unable to connect to BloomBot Climate Data Provider.",

        "provider_url" =>
            $providerUrl

    ], JSON_PRETTY_PRINT);

    exit;
}


// ============================================================
// 3. DECODE PROVIDER RESPONSE
// ============================================================

$providerData =
    json_decode(
        $providerResponse,
        true
    );


if (!is_array($providerData)) {

    echo json_encode([

        "status" => "error",

        "message" =>
            "Invalid response from Climate Data Provider."

    ], JSON_PRETTY_PRINT);

    exit;
}


if (($providerData['status'] ?? '') !== 'success') {

    echo json_encode([

        "status" => "error",

        "message" =>
            "Climate Data Provider returned an unsuccessful response.",

        "provider_response" =>
            $providerData

    ], JSON_PRETTY_PRINT);

    exit;
}


// ============================================================
// 4. GET NORMALIZED OBSERVATIONS
// ============================================================

$observations =
    $providerData['observations']
    ?? [];


if (empty($observations)) {

    echo json_encode([

        "status" => "error",

        "message" =>
            "No climate observations are available for analysis.",

        "source" =>
            $providerData['source']
            ?? null

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
            strtotime($b['timestamp'] ?? '') <=>
            strtotime($a['timestamp'] ?? '');

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
        floor(
            count($values) / 2
        );


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
        calculateAverage(
            $recent
        );


    $olderAverage =
        calculateAverage(
            $older
        );


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
            round(
                $change,
                2
            )

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
            $observation['temperature']
            ?? null
        );


    $humidities[] =
        numericValue(
            $observation['humidity']
            ?? null
        );


    $winds[] =
        numericValue(
            $observation['wind_speed']
            ?? null
        );


    $heatIndexes[] =
        numericValue(
            $observation['heat_index']
            ?? null
        );


    $wetBulbs[] =
        numericValue(
            $observation['wet_bulb']
            ?? null
        );


    $wetBulbGlobes[] =
        numericValue(
            $observation['wet_bulb_globe']
            ?? null
        );


    /*
     * Open-Meteo does not provide the same visible/infrared/UV
     * fields as Conduit.
     *
     * Therefore these remain null for location-weather data.
     */

    $solarVisible[] =
        numericValue(
            $observation['solar_visible']
            ?? null
        );


    $solarInfrared[] =
        numericValue(
            $observation['solar_infrared']
            ?? null
        );


    $uvValues[] =
        numericValue(
            $observation['uv']
            ?? null
        );
}


// ============================================================
// 8. CURRENT CONDITIONS
// ============================================================

$current =
    $observations[0];


$temperature =
    numericValue(
        $current['temperature']
        ?? null
    );


$humidity =
    numericValue(
        $current['humidity']
        ?? null
    );


$wind =
    numericValue(
        $current['wind_speed']
        ?? null
    );


$heatIndex =
    numericValue(
        $current['heat_index']
        ?? null
    );


$wetBulb =
    numericValue(
        $current['wet_bulb']
        ?? null
    );


$wetBulbGlobe =
    numericValue(
        $current['wet_bulb_globe']
        ?? null
    );


$solar =
    numericValue(
        $current['solar_visible']
        ?? null
    );


$infrared =
    numericValue(
        $current['solar_infrared']
        ?? null
    );


$uv =
    numericValue(
        $current['uv']
        ?? null
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

        ? (
            $highHumidityCount /
            $total
        ) * 100

        : 0;


$veryHighHumidityPercentage =
    $total > 0

        ? (
            $veryHighHumidityCount /
            $total
        ) * 100

        : 0;


$lowWindPercentage =
    $total > 0

        ? (
            $lowWindCount /
            $total
        ) * 100

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
        "Unavailable";

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

$climateScore = 100;

$scoreReasons = [];


if (
    $humidity !== null &&
    $humidity >= 80
) {

    $climateScore -= 12;

    $scoreReasons[] =
        "High humidity";

}


if (
    $highHumidityPercentage >= 50
) {

    $climateScore -= 10;

    $scoreReasons[] =
        "Frequent high humidity";

}


if (
    $veryHighHumidityPercentage >= 30
) {

    $climateScore -= 8;

    $scoreReasons[] =
        "Repeated very high humidity";

}


if (
    $wind !== null &&
    $wind <= 1
) {

    $climateScore -= 8;

    $scoreReasons[] =
        "Very low current airflow";

}


if (
    $lowWindPercentage >= 70
) {

    $climateScore -= 10;

    $scoreReasons[] =
        "Persistent low airflow";

}


if (
    $heatIndex !== null &&
    $heatIndex >= 32
) {

    $climateScore -= 15;

    $scoreReasons[] =
        "Elevated heat conditions";

}


if (
    $wind !== null &&
    $wind >= 8
) {

    $climateScore -= 8;

    $scoreReasons[] =
        "Strong wind";

}


$climateScore =
    max(
        0,
        min(
            100,
            $climateScore
        )
    );


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


if (
    $humidity !== null &&
    $humidity >= 80
) {

    $riskScore += 2;

    $riskFactors[] =
        "High humidity";

}


if (
    $highHumidityPercentage >= 50
) {

    $riskScore += 2;

    $riskFactors[] =
        "Frequent high humidity";

}


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


if (
    $lowWindPercentage >= 70
) {

    $riskScore += 1;

    $riskFactors[] =
        "Persistent low airflow";

}


if (
    $heatIndex !== null &&
    $heatIndex >= 32
) {

    $riskScore += 3;

    $riskFactors[] =
        "Elevated heat index";

}


if (
    $wind !== null &&
    $wind >= 8
) {

    $riskScore += 1;

    $riskFactors[] =
        "Strong wind";

}


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
// 16. WHAT CHANGED?
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


if (
    $humidity !== null &&
    $humidity >= 80 &&
    $wind !== null &&
    $wind <= 1
) {

    $insights[] =
        "High humidity is currently occurring alongside very low airflow.";

} elseif (
    $humidity !== null &&
    $humidity >= 80
) {

    $insights[] =
        "Current humidity is high and may increase moisture retention around crops.";

}


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


if (
    $heatIndex !== null &&
    $heatIndex >= 32
) {

    $insights[] =
        "Elevated heat conditions have been detected.";

}


if ($lowWindPercentage >= 70) {

    $insights[] =
        "Low airflow has been persistent across most available observations.";

}


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
// 22. SOURCE INFORMATION
// ============================================================

$source =
    $providerData['source']
    ?? null;


$dataQuality =
    $providerData['data_quality']
    ?? [];


// ============================================================
// 23. FINAL RESPONSE
// ============================================================

$output = [

    "status" =>
        "success",


    "engine" => [

        "name" =>
            "BloomBot Climate Intelligence",

        "version" =>
            "3.1",

        "data_source" =>
            $source['provider']
            ?? "Unknown",

        "source_type" =>
            $source['type']
            ?? $source['data_type']
            ?? "Unknown",

        "observations_analyzed" =>
            $total

    ],


    "location" => [

        "latitude" =>
            $latitude,

        "longitude" =>
            $longitude

    ],


    "source" =>
        $source,

        "observations" =>
            $observations,


    "timestamp" =>
        $current['timestamp']
        ?? null,


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
            $dataQuality['rainfall_interpretation']
            ?? "Not available",

        "ml_prediction" =>
            "Not yet enabled",

        "source_type" =>
            $dataQuality['source_type']
            ?? null,

        "direct_sensor_measurement" =>
            $dataQuality['direct_sensor_measurement']
            ?? null,

        "note" =>
            $dataQuality['note']
            ?? "Climate intelligence currently uses transparent rule-based analysis of normalized environmental observations."

    ]

];


echo json_encode(

    $output,

    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_UNICODE

);

?>
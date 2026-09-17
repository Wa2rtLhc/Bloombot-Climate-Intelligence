<?php

header('Content-Type: application/json');


// ============================================================
// 1. INPUT VALIDATION
// ============================================================

$latitude = isset($_GET['latitude'])
    ? (float) $_GET['latitude']
    : null;

$longitude = isset($_GET['longitude'])
    ? (float) $_GET['longitude']
    : null;

$monitoring_mode =
    $_GET['monitoring_mode']
    ?? 'environmental_station';


if ($latitude === null || $longitude === null) {

    echo json_encode([
        'status' => 'error',
        'message' => 'Latitude and longitude are required.'
    ], JSON_PRETTY_PRINT);

    exit;
}


// ============================================================
// 2. GET CLIMATE DATA FROM PROVIDER
// ============================================================

$providerUrl =
    'http://localhost/Bloombot/climate_data_provider.php'
    . '?latitude=' . urlencode($latitude)
    . '&longitude=' . urlencode($longitude)
    . '&monitoring_mode=' . urlencode($monitoring_mode);


$ch = curl_init($providerUrl);

curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_CONNECTTIMEOUT => 5,

    CURLOPT_TIMEOUT => 30,

    CURLOPT_FOLLOWLOCATION => true

]);


$providerResponse =
    curl_exec($ch);


$providerHttpCode =
    curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );


$providerError =
    curl_error($ch);


curl_close($ch);


if (
    $providerResponse === false ||
    $providerHttpCode < 200 ||
    $providerHttpCode >= 300
) {

    echo json_encode([

        'status' => 'error',

        'message' =>
            'Unable to retrieve climate data.',

        'details' =>
            $providerError

    ], JSON_PRETTY_PRINT);

    exit;
}


$providerData =
    json_decode(
        $providerResponse,
        true
    );


if (
    !is_array($providerData) ||
    ($providerData['status'] ?? '') !== 'success'
) {

    echo json_encode([

        'status' => 'error',

        'message' =>
            'Invalid climate data provider response.',

        'provider_response' =>
            $providerData

    ], JSON_PRETTY_PRINT);

    exit;
}


// ============================================================
// 3. SOURCE INFORMATION
// ============================================================

$source =
    $providerData['source']
    ?? [];

$sourceDataQuality =
    $providerData['data_quality']
    ?? [];


// ============================================================
// 4. OBSERVATIONS
// ============================================================

$observations =
    $providerData['observations']
    ?? [];


if (!is_array($observations)) {

    $observations = [];

}


// ============================================================
// 5. SORT OBSERVATIONS — NEWEST FIRST
// ============================================================

usort(

    $observations,

    function ($a, $b) {

        $timeA =
            strtotime(
                $a['timestamp']
                ?? ''
            );

        $timeB =
            strtotime(
                $b['timestamp']
                ?? ''
            );

        return $timeB <=> $timeA;
    }

);


// ============================================================
// 6. HELPER FUNCTIONS
// ============================================================

function numericValue($value)
{
    if (
        $value === null ||
        $value === '' ||
        !is_numeric($value)
    ) {

        return null;
    }

    return (float) $value;
}


function calculateAverage($values)
{
    $clean = [];

    foreach ($values as $value) {

        if (
            $value !== null &&
            is_numeric($value)
        ) {

            $clean[] =
                (float) $value;
        }
    }


    if (count($clean) === 0) {

        return null;
    }


    return array_sum($clean)
        / count($clean);
}


function calculateTrend($values)
{
    $clean = [];

    foreach ($values as $value) {

        if (
            $value !== null &&
            is_numeric($value)
        ) {

            $clean[] =
                (float) $value;
        }
    }


    if (count($clean) < 2) {

        return [

            'direction' =>
                'stable',

            'change' =>
                0

        ];
    }


    /*
     * Observations are sorted newest first.
     */

    $current =
        $clean[0];

    $oldest =
        $clean[count($clean) - 1];


    $change =
        $current - $oldest;


    if ($change > 0.5) {

        $direction =
            'rising';

    } elseif ($change < -0.5) {

        $direction =
            'falling';

    } else {

        $direction =
            'stable';
    }


    return [

        'direction' =>
            $direction,

        'change' =>
            round(
                $change,
                2
            )

    ];
}


// ============================================================
// 7. EXTRACT NORMALIZED CLIMATE ARRAYS
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

$currentObservation =
    $observations[0]
    ?? [];


/*
 * IMPORTANT:
 * climate_data_provider.php already normalizes the data.
 *
 * We therefore use:
 *
 * temperature
 * humidity
 * wind_speed
 * heat_index
 * wet_bulb
 * wet_bulb_globe
 * solar_visible
 */


$temperature =
    numericValue(
        $currentObservation['temperature']
        ?? null
    );


$humidity =
    numericValue(
        $currentObservation['humidity']
        ?? null
    );


$wind =
    numericValue(
        $currentObservation['wind_speed']
        ?? null
    );


$heatIndex =
    numericValue(
        $currentObservation['heat_index']
        ?? null
    );


$wetBulb =
    numericValue(
        $currentObservation['wet_bulb']
        ?? null
    );


$wetBulbGlobe =
    numericValue(
        $currentObservation['wet_bulb_globe']
        ?? null
    );


$solar =
    numericValue(
        $currentObservation['solar_visible']
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


$baselines = [

    'temperature' =>
        $temperatureAverage !== null
            ? round(
                $temperatureAverage,
                2
            )
            : null,

    'humidity' =>
        $humidityAverage !== null
            ? round(
                $humidityAverage,
                2
            )
            : null,

    'wind_speed' =>
        $windAverage !== null
            ? round(
                $windAverage,
                2
            )
            : null

];


// ============================================================
// 10. TRENDS
// ============================================================

$trends = [

    'temperature' =>
        calculateTrend(
            $temperatures
        ),

    'humidity' =>
        calculateTrend(
            $humidities
        ),

    'wind_speed' =>
        calculateTrend(
            $winds
        )

];


// ============================================================
// 11. PERSISTENCE
// ============================================================

$recentTemperatures =
    array_slice(
        $temperatures,
        0,
        min(
            6,
            count($temperatures)
        )
    );


$recentHumidity =
    array_slice(
        $humidities,
        0,
        min(
            6,
            count($humidities)
        )
    );


$recentWind =
    array_slice(
        $winds,
        0,
        min(
            6,
            count($winds)
        )
    );


$humidityPersistence =
    calculateAverage(
        $recentHumidity
    );


$temperaturePersistence =
    calculateAverage(
        $recentTemperatures
    );


$windPersistence =
    calculateAverage(
        $recentWind
    );


// ============================================================
// 12. CONDITION STATUS
// ============================================================

if ($temperature === null) {

    $temperatureStatus =
        'Unknown';

} elseif ($temperature >= 32) {

    $temperatureStatus =
        'High';

} elseif ($temperature <= 10) {

    $temperatureStatus =
        'Low';

} else {

    $temperatureStatus =
        'Favorable';
}


if ($humidity === null) {

    $humidityStatus =
        'Unknown';

} elseif ($humidity >= 85) {

    $humidityStatus =
        'Very High';

} elseif ($humidity >= 70) {

    $humidityStatus =
        'High';

} elseif ($humidity < 35) {

    $humidityStatus =
        'Low';

} else {

    $humidityStatus =
        'Favorable';
}


if ($wind === null) {

    $windStatus =
        'Unknown';

} elseif ($wind <= 1) {

    $windStatus =
        'Very Low';

} elseif ($wind <= 3) {

    $windStatus =
        'Low';

} elseif ($wind <= 8) {

    $windStatus =
        'Favorable';

} else {

    $windStatus =
        'High';
}


// ============================================================
// 13. CLIMATE HEALTH SCORE
// ============================================================

$healthScore = 100;


if ($temperature !== null) {

    if ($temperature >= 35) {

        $healthScore -= 30;

    } elseif ($temperature >= 32) {

        $healthScore -= 20;

    } elseif ($temperature <= 5) {

        $healthScore -= 30;

    } elseif ($temperature <= 10) {

        $healthScore -= 15;
    }
}


if ($humidity !== null) {

    if ($humidity >= 90) {

        $healthScore -= 25;

    } elseif ($humidity >= 80) {

        $healthScore -= 15;

    } elseif ($humidity < 30) {

        $healthScore -= 20;

    } elseif ($humidity < 40) {

        $healthScore -= 10;
    }
}


if ($wind !== null) {

    if ($wind <= 0.5) {

        $healthScore -= 15;

    } elseif ($wind <= 1) {

        $healthScore -= 8;
    }
}


$healthScore =
    max(
        0,
        min(
            100,
            $healthScore
        )
    );


if ($healthScore >= 80) {

    $healthStatus =
        'Healthy';

} elseif ($healthScore >= 60) {

    $healthStatus =
        'Watch';

} elseif ($healthScore >= 40) {

    $healthStatus =
        'Stressed';

} else {

    $healthStatus =
        'Critical';
}


// ============================================================
// 14. RULE-BASED CLIMATE RISK
// ============================================================

$riskScore = 0;

$riskDrivers = [];


if ($temperature !== null) {

    if ($temperature >= 35) {

        $riskScore += 35;

        $riskDrivers[] =
            'High temperature';

    } elseif ($temperature >= 32) {

        $riskScore += 20;

        $riskDrivers[] =
            'Elevated temperature';

    } elseif ($temperature <= 5) {

        $riskScore += 30;

        $riskDrivers[] =
            'Low temperature';

    } elseif ($temperature <= 10) {

        $riskScore += 15;

        $riskDrivers[] =
            'Cool conditions';
    }
}


if ($humidity !== null) {

    if ($humidity >= 90) {

        $riskScore += 30;

        $riskDrivers[] =
            'Very high humidity';

    } elseif ($humidity >= 80) {

        $riskScore += 20;

        $riskDrivers[] =
            'High humidity';

    } elseif ($humidity < 30) {

        $riskScore += 25;

        $riskDrivers[] =
            'Low humidity';
    }
}


if ($wind !== null) {

    if ($wind <= 0.5) {

        $riskScore += 20;

        $riskDrivers[] =
            'Very low airflow';

    } elseif ($wind <= 1) {

        $riskScore += 10;

        $riskDrivers[] =
            'Low airflow';
    }
}


$riskScore =
    min(
        100,
        $riskScore
    );


if ($riskScore >= 70) {

    $riskLevel =
        'Critical';

} elseif ($riskScore >= 45) {

    $riskLevel =
        'High';

} elseif ($riskScore >= 20) {

    $riskLevel =
        'Moderate';

} else {

    $riskLevel =
        'Low';
}


// ============================================================
// 15. PRIMARY DRIVER
// ============================================================

$primaryDriver =
    !empty($riskDrivers)
        ? $riskDrivers[0]
        : 'No major climate risk detected';


// ============================================================
// 16. WHAT CHANGED
// ============================================================

$whatChanged = [];


if (
    isset(
        $trends['temperature']['direction']
    ) &&
    $trends['temperature']['direction']
        !== 'stable'
) {

    $whatChanged[] =
        'Temperature is '
        . $trends['temperature']['direction'];
}


if (
    isset(
        $trends['humidity']['direction']
    ) &&
    $trends['humidity']['direction']
        !== 'stable'
) {

    $whatChanged[] =
        'Humidity is '
        . $trends['humidity']['direction'];
}


if (
    isset(
        $trends['wind_speed']['direction']
    ) &&
    $trends['wind_speed']['direction']
        !== 'stable'
) {

    $whatChanged[] =
        'Airflow is '
        . $trends['wind_speed']['direction'];
}


if (empty($whatChanged)) {

    $whatChanged[] =
        'Climate conditions are relatively stable';
}


// ============================================================
// 17. CROP INTELLIGENCE
// ============================================================

$cropIntelligence = [

    'temperature' => [

        'value' =>
            $temperature,

        'status' =>
            $temperatureStatus

    ],

    'humidity' => [

        'value' =>
            $humidity,

        'status' =>
            $humidityStatus

    ],

    'wind' => [

        'value' =>
            $wind,

        'status' =>
            $windStatus

    ]

];


// ============================================================
// 18. IRRIGATION INTELLIGENCE
// ============================================================

if (
    $humidity !== null &&
    $humidity >= 80
) {

    $irrigationStatus =
        'Monitor';

    $irrigationMessage =
        'High atmospheric humidity may reduce immediate irrigation demand.';

} elseif (
    $humidity !== null &&
    $humidity < 40
) {

    $irrigationStatus =
        'Consider';

    $irrigationMessage =
        'Low atmospheric humidity may increase plant water demand.';

} else {

    $irrigationStatus =
        'Normal';

    $irrigationMessage =
        'No strong atmospheric signal for immediate irrigation.';
}


$irrigationIntelligence = [

    'status' =>
        $irrigationStatus,

    'message' =>
        $irrigationMessage

];


// ============================================================
// 19. INSIGHTS
// ============================================================

$insights = [];


if (
    $humidity !== null &&
    $humidity >= 80 &&
    $wind !== null &&
    $wind <= 1
) {

    $insights[] =
        'High humidity combined with low airflow may increase crop disease pressure.';
}


if (
    $temperature !== null &&
    $temperature >= 32
) {

    $insights[] =
        'Elevated temperature may increase plant water demand and heat stress.';
}


if (
    $humidity !== null &&
    $humidity < 40
) {

    $insights[] =
        'Dry atmospheric conditions may increase water loss from plants.';
}


if (empty($insights)) {

    $insights[] =
        'Current climate conditions show no major immediate environmental warning.';
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
        'Monitor plants closely for fungal or disease-related stress.';
}


if (
    $temperature !== null &&
    $temperature >= 32
) {

    $recommendations[] =
        'Check plant moisture and provide appropriate shade or irrigation if needed.';
}


if (
    $humidity !== null &&
    $humidity < 40
) {

    $recommendations[] =
        'Monitor soil moisture because atmospheric dryness may increase water demand.';
}


if (empty($recommendations)) {

    $recommendations[] =
        'Continue normal monitoring and review new climate observations.';
}


// ============================================================
// 21. EVIDENCE
// ============================================================

$evidence = [

    'observation_count' =>
        count($observations),

    'temperature_average' =>
        $temperatureAverage !== null
            ? round(
                $temperatureAverage,
                2
            )
            : null,

    'humidity_average' =>
        $humidityAverage !== null
            ? round(
                $humidityAverage,
                2
            )
            : null,

    'wind_average' =>
        $windAverage !== null
            ? round(
                $windAverage,
                2
            )
            : null,

    'temperature_trend' =>
        $trends['temperature'],

    'humidity_trend' =>
        $trends['humidity'],

    'wind_trend' =>
        $trends['wind_speed']

];


// ============================================================
// 21.5 MACHINE LEARNING PREDICTION
// ============================================================

$mlPrediction = null;

$mlUrl =
    'http://localhost/Bloombot/ml/ml_predict.php';


$mlInput = [

    'temperature' =>
        $temperature,

    'humidity' =>
        $humidity,

    'wind_speed' =>
        $wind,

    'heat_index' =>
        $heatIndex,

    'wet_bulb' =>
        $wetBulb,

    'solar_radiation' =>
        $solar

];


if (
    $temperature !== null &&
    $humidity !== null &&
    $wind !== null &&
    $heatIndex !== null &&
    $wetBulb !== null &&
    $solar !== null
) {

    $ch =
        curl_init(
            $mlUrl
        );


    curl_setopt_array(

        $ch,

        [

            CURLOPT_POST =>
                true,

            CURLOPT_POSTFIELDS =>
                json_encode(
                    $mlInput
                ),

            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_CONNECTTIMEOUT =>
                5,

            CURLOPT_TIMEOUT =>
                15,

            CURLOPT_HTTPHEADER =>
                [

                    'Content-Type: application/json',

                    'Accept: application/json'

                ]

        ]

    );


    $mlResponse =
        curl_exec(
            $ch
        );


    $mlHttpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    $mlError =
        curl_error(
            $ch
        );


    curl_close(
        $ch
    );


    if (
        $mlResponse !== false &&
        $mlHttpCode >= 200 &&
        $mlHttpCode < 300
    ) {

        $decodedML =
            json_decode(
                $mlResponse,
                true
            );


        if (
            is_array($decodedML) &&
            ($decodedML['status'] ?? '')
                === 'success'
        ) {

            $mlPrediction =
                $decodedML;

        }

    }

}


// ============================================================
// 22. SOURCE INFORMATION
// ============================================================

$sourceInformation = [

    'name' =>
        $source['name']
        ?? 'Unknown',

    'provider' =>
        $source['provider']
        ?? 'Unknown',

    'source_type' =>
        $source['source_type']
        ?? $source['type']
        ?? 'Unknown',

    'data_type' =>
        $source['data_type']
        ?? 'Unknown',

    'location' =>
        $source['location']
        ?? null,

    'latitude' =>
        $source['latitude']
        ?? $latitude,

    'longitude' =>
        $source['longitude']
        ?? $longitude

];


// ============================================================
// 23. FINAL RESPONSE
// ============================================================

$response = [

    'status' =>
        'success',

    'engine' =>
        'BloomBot Climate Intelligence',

    'version' =>
        '3.1',

    'generated_at' =>
        gmdate(
            'Y-m-d\TH:i:s\Z'
        ),


    // --------------------------------------------------------
    // LOCATION
    // --------------------------------------------------------

    'location' => [

        'latitude' =>
            $latitude,

        'longitude' =>
            $longitude

    ],


    'monitoring_mode' =>
        $monitoring_mode,


    // --------------------------------------------------------
    // SOURCE
    // --------------------------------------------------------

    'source' =>
        $sourceInformation,


    // --------------------------------------------------------
    // CURRENT CONDITIONS
    // --------------------------------------------------------

    'current_conditions' => [

        'temperature' => [

            'value' =>
                $temperature,

            'status' =>
                $temperatureStatus

        ],

        'humidity' => [

            'value' =>
                $humidity,

            'status' =>
                $humidityStatus

        ],

        'wind' => [

            'value' =>
                $wind,

            'status' =>
                $windStatus

        ],

        'heat_index' =>
            $heatIndex,

        'wet_bulb' =>
            $wetBulb,

        'wet_bulb_globe' =>
            $wetBulbGlobe,

        'solar_radiation' =>
            $solar

    ],


    // --------------------------------------------------------
    // CLIMATE HEALTH
    // --------------------------------------------------------

    'climate_health' => [

        'score' =>
            $healthScore,

        'status' =>
            $healthStatus

    ],


    // --------------------------------------------------------
    // RISK
    // --------------------------------------------------------

    'risk' => [

        'score' =>
            $riskScore,

        'level' =>
            $riskLevel,

        'primary_driver' =>
            $primaryDriver,

        'drivers' =>
            $riskDrivers

    ],


    // --------------------------------------------------------
    // MACHINE LEARNING
    // --------------------------------------------------------

    'ml_prediction' =>
        $mlPrediction,


    // --------------------------------------------------------
    // TRENDS
    // --------------------------------------------------------

    'trends' =>
        $trends,


    // --------------------------------------------------------
    // BASELINES
    // --------------------------------------------------------

    'baselines' =>
        $baselines,


    // --------------------------------------------------------
    // WHAT CHANGED
    // --------------------------------------------------------

    'what_changed' =>
        $whatChanged,


    // --------------------------------------------------------
    // CROP INTELLIGENCE
    // --------------------------------------------------------

    'crop_intelligence' =>
        $cropIntelligence,


    // --------------------------------------------------------
    // IRRIGATION INTELLIGENCE
    // --------------------------------------------------------

    'irrigation_intelligence' =>
        $irrigationIntelligence,


    // --------------------------------------------------------
    // INSIGHTS
    // --------------------------------------------------------

    'insights' =>
        $insights,


    // --------------------------------------------------------
    // RECOMMENDATIONS
    // --------------------------------------------------------

    'recommendations' =>
        $recommendations,


    // --------------------------------------------------------
    // EVIDENCE
    // --------------------------------------------------------

    'evidence' =>
        $evidence,


    // --------------------------------------------------------
    // OBSERVATIONS
    // --------------------------------------------------------

    'observations' =>
        $observations,


    // --------------------------------------------------------
    // DATA QUALITY
    // --------------------------------------------------------

    'data_quality' => [

        'observation_count' =>
            count($observations),

        'source_type' =>
            $sourceDataQuality['source_type']
            ?? $sourceInformation['source_type'],

        'direct_sensor_measurement' =>
            $sourceDataQuality['direct_sensor_measurement']
            ?? null,

        'ml_prediction' =>
            $mlPrediction

    ]

];


echo json_encode(

    $response,

    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_UNICODE

);
 ?>
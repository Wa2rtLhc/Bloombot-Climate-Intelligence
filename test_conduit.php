<?php

$env = parse_ini_file(__DIR__ . '/.env');

$apiKey = $env['CONDUIT_API_KEY'];
$email  = $env['CONDUIT_EMAIL'];

$url = 'https://conduit.jhubafrica.com/data.php';

$data = [
    'apikey' => $apiKey,
    'email' => $email,
    'fromdate' => '2025-06-01',
    'todate' => '2025-06-02'
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'cURL Error: ' . curl_error($ch);
} else {
    echo "HTTP Status Code: $httpCode\n";
    echo "Raw Response:\n$response\n";

    $result = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        echo "Parsed Response:\n";
        print_r($result);
    } else {
        echo "Failed to parse JSON.\n";
        echo "JSON Error: " . json_last_error_msg();
    }
}

curl_close($ch);
?>
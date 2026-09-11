<?php

function getWeather($city = "Nairobi") {

    // Load the .env file
    $envFile = __DIR__ . '/.env';

    if (!file_exists($envFile)) {
        return null;
    }

    $env = parse_ini_file($envFile);

    // Get the OpenWeatherMap API key
    $apiKey = $env['OPENWEATHERMAP_API_KEY'] ?? '';

    // Make sure the API key exists
    if (empty($apiKey)) {
        return null;
    }

    // OpenWeatherMap API URL
    $url = "https://api.openweathermap.org/data/2.5/weather?q="
        . urlencode($city)
        . "&appid="
        . urlencode($apiKey)
        . "&units=metric";

    // Turn on error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Get weather data
    $response = file_get_contents($url);

    if ($response === FALSE) {
        return null;
    }

    // Convert JSON response into an array
    $data = json_decode($response, true);

    if (!isset($data['weather'][0])) {
        return null;
    }

    // Get weather description
    $desc = strtolower($data['weather'][0]['description']);

    // Check whether rain is mentioned or rain data exists
    $willRain = strpos($desc, 'rain') !== false || isset($data['rain']);

    return [
        'temperature' => $data['main']['temp'],
        'humidity' => $data['main']['humidity'],
        'description' => $data['weather'][0]['description'],
        'wind_speed' => $data['wind']['speed'],
        'will_rain' => $willRain,
        'city' => $data['name']
    ];
}

?>
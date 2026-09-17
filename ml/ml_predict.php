<?php

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON input'
    ]);
    exit;
}

$python = 'python';
$script = __DIR__ . DIRECTORY_SEPARATOR . 'predict.py';

$command = '"' . $python . '" "' . $script . '"';

$descriptorspec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$process = proc_open(
    $command,
    $descriptorspec,
    $pipes,
    __DIR__
);

if (!is_resource($process)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Could not start ML prediction service'
    ]);
    exit;
}

fwrite(
    $pipes[0],
    json_encode($input)
);

fclose($pipes[0]);

$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);

$returnCode = proc_close($process);

if ($returnCode !== 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ML prediction failed',
        'details' => trim($error)
    ]);
    exit;
}

$result = json_decode($output, true);

if (!is_array($result)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid response from ML model',
        'raw_output' => $output
    ]);
    exit;
}

echo json_encode(
    $result,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_UNICODE
);
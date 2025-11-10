<?php
require(__DIR__ . '/../../config.php');
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

header('Content-Type: application/json');

// Access Moodle session for storing token
global $SESSION;

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;

// Handle login to AI backend
if ($action === 'login') {
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');
    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing credentials']);
        exit;
    }
    $loginUrl = 'https://swiftlearn.co.uk/opt/adeptus_ai_backend/public/api/auth/login';
    $postData = json_encode(['email' => $email, 'password' => $password]);
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $postData,
        ],
    ]);
    $result = file_get_contents($loginUrl, false, $context);
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Login request failed']);
        exit;
    }
    $resultData = json_decode($result, true);
    if (!empty($resultData['token'])) {
        $SESSION->ai_token = $resultData['token'];
        echo json_encode(['success' => true]);
        exit;
    }
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

// Ensure user is authenticated with AI backend
$token = $SESSION->ai_token ?? '';
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

// Process AI prompt
$prompt = trim($input['prompt'] ?? '');
if (!$prompt) {
    echo json_encode(['error' => 'Missing prompt']);
    exit;
}

// Proxy request to AI backend, including auth header
$backendUrl = 'https://swiftlearn.co.uk/opt/adeptus_ai_backend/public/report-ai?prompt=' . urlencode($prompt);
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
    ],
];
$response = file_get_contents($backendUrl, false, stream_context_create($opts));
$data = json_decode($response, true);

echo json_encode([
    'reply' => $data['reply'] ?? 'No reply',
    'tableHtml' => $data['tableHtml'] ?? null,
]);

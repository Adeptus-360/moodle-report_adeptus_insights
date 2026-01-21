<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI backend endpoint for Adeptus Insights.
 *
 * Handles authentication and communication with the AI service.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/api_config.php');
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
    $loginurl = \report_adeptus_insights\api_config::get_ai_login_endpoint();
    $postdata = json_encode(['email' => $email, 'password' => $password]);
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $postdata,
        ],
    ]);
    $result = file_get_contents($loginurl, false, $context);
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Login request failed']);
        exit;
    }
    $resultdata = json_decode($result, true);
    if (!empty($resultdata['token'])) {
        $SESSION->ai_token = $resultdata['token'];
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
$backendurl = \report_adeptus_insights\api_config::get_ai_report_endpoint() . '?prompt=' . urlencode($prompt);
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
    ],
];
$response = file_get_contents($backendurl, false, stream_context_create($opts));
$data = json_decode($response, true);

echo json_encode([
    'reply' => $data['reply'] ?? 'No reply',
    'tableHtml' => $data['tableHtml'] ?? null,
]);

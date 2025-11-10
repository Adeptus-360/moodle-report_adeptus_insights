<?php
/**
 * Adeptus Insights - Backend Connectivity Test
 * Simple test to verify backend API is accessible
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check for valid login
require_login();

// Check capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Set up page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/test_backend.php'));
$PAGE->set_title('Backend Connectivity Test');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

echo '<h1>Backend Connectivity Test</h1>';

// Test 1: Direct backend URL
echo '<h2>1. Direct Backend Test</h2>';
$backend_url = 'https://ai-backend.stagingwithswift.com/api/subscription/config';

echo '<p>Testing: ' . $backend_url . '</p>';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $backend_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo '<p style="color: red;">✗ Backend connection failed</p>';
    echo '<p>Error: ' . $error . '</p>';
} else {
    echo '<p style="color: green;">✓ Backend connection successful</p>';
    echo '<p>HTTP Code: ' . $http_code . '</p>';
    echo '<p>Response: ' . substr($response, 0, 500) . '...</p>';
}

// Test 2: API Proxy Test
echo '<h2>2. API Proxy Test</h2>';
$proxy_url = 'https://ai-backend.stagingwithswift.com/api_proxy.php/subscription/config';

echo '<p>Testing: ' . $proxy_url . '</p>';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $proxy_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo '<p style="color: red;">✗ API Proxy connection failed</p>';
    echo '<p>Error: ' . $error . '</p>';
} else {
    echo '<p style="color: green;">✓ API Proxy connection successful</p>';
    echo '<p>HTTP Code: ' . $http_code . '</p>';
    echo '<p>Response: ' . substr($response, 0, 500) . '...</p>';
}

// Test 3: Plans Endpoint Test
echo '<h2>3. Plans Endpoint Test</h2>';
$plans_url = 'https://ai-backend.stagingwithswift.com/api/subscription/plans';

echo '<p>Testing: ' . $plans_url . '</p>';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $plans_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo '<p style="color: red;">✗ Plans endpoint connection failed</p>';
    echo '<p>Error: ' . $error . '</p>';
} else {
    echo '<p style="color: green;">✓ Plans endpoint connection successful</p>';
    echo '<p>HTTP Code: ' . $http_code . '</p>';
    echo '<p>Response: ' . substr($response, 0, 500) . '...</p>';
}

echo '<h2>Summary</h2>';
echo '<p><a href="diagnostic.php" class="btn btn-primary">Back to Diagnostic</a></p>';

echo $OUTPUT->footer(); 
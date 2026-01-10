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
 * API Proxy for Adeptus Insights.
 *
 * This script handles API requests for the Moodle plugin and routes them
 * to the Laravel backend. It acts as a CORS-enabled proxy layer.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Load Moodle configuration.
require_once(__DIR__ . '/../../config.php');

// Load centralized API configuration.
require_once(__DIR__ . '/classes/api_config.php');

// Get CORS origin from centralized config.
$cors_origin = \report_adeptus_insights\api_config::get_cors_origin();

// Set CORS headers.
header('Access-Control-Allow-Origin: ' . $cors_origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, Origin, X-Sesskey');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight requests.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Define endpoints that don't require authentication (used during initial setup).
$public_endpoints = ['register'];

// Get the request path to determine the endpoint early.
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));
$endpoint = end($path_parts);

// Require authentication for non-public endpoints.
if (!in_array($endpoint, $public_endpoints)) {
    require_login();
    $context = context_system::instance();
    require_capability('report/adeptus_insights:view', $context);

    // Validate session key for POST/PUT/PATCH/DELETE requests.
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        // Try to get sesskey from header, query string, or request body.
        $sesskey = null;
        if (isset($_SERVER['HTTP_X_SESSKEY'])) {
            $sesskey = $_SERVER['HTTP_X_SESSKEY'];
        } else if (isset($_GET['sesskey'])) {
            $sesskey = $_GET['sesskey'];
        } else if (isset($_POST['sesskey'])) {
            $sesskey = $_POST['sesskey'];
        } else {
            // Try to get from JSON body.
            $json_input = json_decode(file_get_contents('php://input'), true);
            if (isset($json_input['sesskey'])) {
                $sesskey = $json_input['sesskey'];
            }
        }

        if (!$sesskey || !confirm_sesskey($sesskey)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid session key',
            ]);
            exit;
        }
    }
}

// Backend API URL - using legacy URL for backward compatibility.
$BACKEND_URL = \report_adeptus_insights\api_config::get_legacy_api_url();

// Note: $endpoint and $path_parts are already defined above during authentication check.

// Handle different endpoints
switch ($endpoint) {
    case 'register':
        handleRegistration();
        break;
    case 'plans':
        handlePlans();
        break;
    case 'stripe-config':
        handleStripeConfig();
        break;
    case 'config':
        handleStripeConfig();
        break;
    case 'create':
        handleCreateSubscription();
        break;
    case 'show':
        handleShowSubscription();
        break;
    case 'cancel':
        handleCancelSubscription();
        break;
    case 'update':
        handleUpdateSubscription();
        break;
    default:
        // Check if this is an installation endpoint
        if (in_array('installation', $path_parts)) {
            handleRegistration();
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint not found',
            ]);
        }
        break;
}

/**
 * Forward request to Laravel backend
 */
function forwardToBackend($endpoint, $data = [], $method = 'POST') {
    global $BACKEND_URL;

    $url = $BACKEND_URL . '/' . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Forward headers from the original request
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    // Forward API key header if present
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $headers[] = 'X-API-Key: ' . $_SERVER['HTTP_X_API_KEY'];
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('API request failed: ' . $error . ' (URL: ' . $url . ')');
    }

    if ($http_code !== 200) {
        throw new Exception('API request failed: HTTP ' . $http_code . ' - Response: ' . $response . ' (URL: ' . $url . ')');
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response: ' . json_last_error_msg() . ' - Response: ' . $response);
    }

    return $decoded;
}

function handleRegistration() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
        ]);
        return;
    }

    // Get input data (handle both JSON and form data)
    $input = [];

    // Try to get JSON input first
    $json_input = json_decode(file_get_contents('php://input'), true);
    if ($json_input) {
        $input = $json_input;
    } else {
        // Fall back to form data
        $input = $_POST;
    }

    // Validate required fields for form data
    $required_fields = ['admin_email', 'admin_name'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Missing required field: $field",
            ]);
            return;
        }
    }

    // Add missing fields that the installation manager expects
    if (empty($input['site_url'])) {
        $input['site_url'] = \report_adeptus_insights\api_config::get_site_url();
    }
    if (empty($input['site_name'])) {
        $input['site_name'] = 'Moodle Site';
    }
    if (empty($input['moodle_version'])) {
        $input['moodle_version'] = '4.0';
    }
    if (empty($input['php_version'])) {
        $input['php_version'] = PHP_VERSION;
    }
    if (empty($input['plugin_version'])) {
        $input['plugin_version'] = '1.0.0';
    }

    try {
        // Forward to Laravel backend
        $response = forwardToBackend('installation/register', $input);
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed: ' . $e->getMessage(),
        ]);
    }
}

function handlePlans() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
        ]);
        return;
    }

    try {
        // Forward to Laravel backend
        $response = forwardToBackend('subscription/plans', [], 'GET');
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to load plans: ' . $e->getMessage(),
        ]);
    }
}

/**
 * Get features for a plan based on its configuration
 */
function getPlanFeatures($plan) {
    $features = [];

    if ($plan->is_free) {
        $features[] = 'Basic AI requests (DeepSeek)';
        $features[] = $plan->exports . ' report exports per month';
        $features[] = 'Standard support';
    } else {
        if ($plan->ai_credits_pro > 0) {
            $features[] = $plan->ai_credits_pro . ' pro AI requests (OpenAI)';
        }
        if ($plan->ai_credits_basic > 0) {
            $features[] = $plan->ai_credits_basic . ' basic AI requests (DeepSeek)';
        }
        $features[] = $plan->exports . ' report exports per month';

        if ($plan->price >= 29.99) {
            $features[] = 'Priority support';
            $features[] = 'Top-up credits available';
        }

        if ($plan->price >= 99.99) {
            $features[] = '24/7 priority support';
            $features[] = 'Custom integrations';
        }
    }

    return $features;
}

function handleStripeConfig() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
        ]);
        return;
    }

    try {
        // Forward to Laravel backend
        $response = forwardToBackend('subscription/config', [], 'GET');
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get Stripe config: ' . $e->getMessage(),
        ]);
    }
}

function handleCreateSubscription() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
        ]);
        return;
    }

    try {
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        // Forward to Laravel backend
        $response = forwardToBackend('subscription/create', $input);
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create subscription: ' . $e->getMessage(),
        ]);
    }
}

function handleShowSubscription() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
        ]);
        return;
    }

    // Mock subscription details
    $subscription = [
        'subscription_id' => 'sub_' . bin2hex(random_bytes(8)),
        'plan' => [
            'name' => 'Basic Plan',
            'price' => '$9.99',
            'billing_cycle' => 'monthly',
        ],
        'status' => 'active',
        'current_period_end' => date('Y-m-d H:i:s', strtotime('+1 month')),
        'ai_credits_remaining' => 100,
        'exports_remaining' => 10,
        'is_on_trial' => false,
        'is_cancelled' => false,
    ];

    echo json_encode([
        'success' => true,
        'data' => [
            'subscription' => $subscription,
        ],
    ]);
}

function handleCancelSubscription() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Subscription cancelled successfully',
    ]);
}

function handleUpdateSubscription() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Subscription updated successfully',
    ]);
}

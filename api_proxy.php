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
 * Authentication is handled internally based on endpoint type (public vs protected).
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
// Load Moodle configuration.
require_once(__DIR__ . '/../../config.php');

// Get CORS origin from centralized config (class autoloaded).
$corsorigin = \report_adeptus_insights\api_config::get_cors_origin();

// Set CORS headers.
header('Access-Control-Allow-Origin: ' . $corsorigin);
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, Origin, X-Sesskey');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Get request method early for use throughout the script.
$requestmethod = isset($_SERVER['REQUEST_METHOD']) ? clean_param($_SERVER['REQUEST_METHOD'], PARAM_ALPHA) : 'GET';

// Handle preflight requests.
if ($requestmethod === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Define endpoints that don't require authentication (used during initial setup).
$publicendpoints = ['register'];

// Get the request path to determine the endpoint early.
$requesturi = isset($_SERVER['REQUEST_URI']) ? clean_param($_SERVER['REQUEST_URI'], PARAM_URL) : '';
$path = parse_url($requesturi, PHP_URL_PATH);
$pathparts = explode('/', trim($path, '/'));
$endpoint = end($pathparts);

// Require authentication for non-public endpoints.
if (!in_array($endpoint, $publicendpoints)) {
    require_login();
    $context = context_system::instance();
    require_capability('report/adeptus_insights:view', $context);

    // Validate session key for POST/PUT/PATCH/DELETE requests.
    if (in_array($requestmethod, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        // Try to get sesskey from header, query string, or request body.
        $sesskey = null;
        if (isset($_SERVER['HTTP_X_SESSKEY'])) {
            $sesskey = clean_param($_SERVER['HTTP_X_SESSKEY'], PARAM_ALPHANUM);
        } else {
            // Try optional_param first (handles both GET and POST).
            $sesskey = optional_param('sesskey', null, PARAM_ALPHANUM);
        }
        if (empty($sesskey)) {
            // Try to get from JSON body.
            $jsoninput = json_decode(file_get_contents('php://input'), true);
            if (isset($jsoninput['sesskey'])) {
                $sesskey = clean_param($jsoninput['sesskey'], PARAM_ALPHANUM);
            }
        }

        if (!$sesskey || !confirm_sesskey($sesskey)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => get_string('error_invalid_sesskey', 'report_adeptus_insights'),
            ]);
            exit;
        }
    }
}

// Backend API URL - using legacy URL for backward compatibility.
$backendurl = \report_adeptus_insights\api_config::get_legacy_api_url();

// Note: $endpoint and $pathparts are already defined above during authentication check.

// Handle different endpoints
switch ($endpoint) {
    case 'register':
        report_adeptus_insights_handle_registration();
        break;
    case 'plans':
        report_adeptus_insights_handle_plans();
        break;
    case 'stripe-config':
        report_adeptus_insights_handle_stripe_config();
        break;
    case 'config':
        report_adeptus_insights_handle_stripe_config();
        break;
    case 'create':
        report_adeptus_insights_handle_create_subscription();
        break;
    case 'show':
        report_adeptus_insights_handle_show_subscription();
        break;
    case 'cancel':
        report_adeptus_insights_handle_cancel_subscription();
        break;
    case 'update':
        report_adeptus_insights_handle_update_subscription();
        break;
    default:
        // Check if this is an installation endpoint
        if (in_array('installation', $pathparts)) {
            report_adeptus_insights_handle_registration();
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => get_string('error_endpoint_not_found', 'report_adeptus_insights'),
            ]);
        }
        break;
}

/**
 * Forward request to Laravel backend.
 *
 * Uses Moodle's curl wrapper for proper proxy support.
 *
 * @param string $endpoint The API endpoint.
 * @param array $data Request data.
 * @param string $method HTTP method.
 * @return array Decoded response.
 * @throws Exception On connection or response errors.
 */
function report_adeptus_insights_forward_to_backend($endpoint, $data = [], $method = 'POST') {
    global $backendurl;

    $url = $backendurl . '/' . $endpoint;

    // Use Moodle's curl wrapper for proxy support.
    $curl = new \curl();

    // Set headers.
    $curl->setHeader('Content-Type: application/json');
    $curl->setHeader('Accept: application/json');

    // Forward API key header if present.
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $curl->setHeader('X-API-Key: ' . clean_param($_SERVER['HTTP_X_API_KEY'], PARAM_ALPHANUMEXT));
    }

    // Set curl options.
    $options = [
        'CURLOPT_TIMEOUT' => 30,
        'CURLOPT_SSL_VERIFYPEER' => true,
    ];

    // Make request based on method.
    if ($method === 'GET') {
        $response = $curl->get($url, [], $options);
    } else if ($method === 'POST') {
        $response = $curl->post($url, json_encode($data), $options);
    } else {
        // For PUT, DELETE, PATCH etc.
        $options['CURLOPT_CUSTOMREQUEST'] = $method;
        $response = $curl->post($url, json_encode($data), $options);
    }

    // Get response info.
    $info = $curl->get_info();
    $httpcode = $info['http_code'] ?? 0;
    $error = $curl->get_errno() ? $curl->error : '';

    if ($response === false || $error) {
        throw new Exception('API request failed: ' . $error . ' (URL: ' . $url . ')');
    }

    if ($httpcode !== 200) {
        throw new Exception('API request failed: HTTP ' . $httpcode . ' - Response: ' . $response . ' (URL: ' . $url . ')');
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response: ' . json_last_error_msg() . ' - Response: ' . $response);
    }

    return $decoded;
}

/**
 * Handle plugin registration endpoint.
 */
function report_adeptus_insights_handle_registration() {
    global $requestmethod;
    if ($requestmethod !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_method_not_allowed', 'report_adeptus_insights'),
        ]);
        return;
    }

    // Get input data (handle both JSON and form data)
    $input = [];

    // Try to get JSON input first.
    $jsoninput = json_decode(file_get_contents('php://input'), true);
    if ($jsoninput) {
        // Clean each input value from JSON.
        $input = [];
        foreach ($jsoninput as $key => $value) {
            $input[clean_param($key, PARAM_ALPHANUMEXT)] = is_string($value) ? clean_param($value, PARAM_TEXT) : $value;
        }
    } else {
        // Fall back to form data using Moodle's parameter functions.
        $input = [
            'admin_email' => optional_param('admin_email', '', PARAM_EMAIL),
            'admin_name' => optional_param('admin_name', '', PARAM_TEXT),
            'site_url' => optional_param('site_url', '', PARAM_URL),
            'site_name' => optional_param('site_name', '', PARAM_TEXT),
            'moodle_version' => optional_param('moodle_version', '', PARAM_TEXT),
            'php_version' => optional_param('php_version', '', PARAM_TEXT),
            'plugin_version' => optional_param('plugin_version', '', PARAM_TEXT),
        ];
    }

    // Validate required fields for form data
    $requiredfields = ['admin_email', 'admin_name'];
    foreach ($requiredfields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => get_string('error_missing_required_field', 'report_adeptus_insights', $field),
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
        $response = report_adeptus_insights_forward_to_backend('installation/register', $input);
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_registration_failed', 'report_adeptus_insights', $e->getMessage()),
        ]);
    }
}

/**
 * Handle subscription plans endpoint.
 */
function report_adeptus_insights_handle_plans() {
    global $requestmethod;
    if ($requestmethod !== 'GET' && $requestmethod !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_method_not_allowed', 'report_adeptus_insights'),
        ]);
        return;
    }

    try {
        // Forward to Laravel backend
        $response = report_adeptus_insights_forward_to_backend('subscription/plans', [], 'GET');
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_load_plans_failed', 'report_adeptus_insights', $e->getMessage()),
        ]);
    }
}

/**
 * Get features for a plan based on its configuration.
 *
 * @param object $plan The plan object.
 * @return array List of feature descriptions.
 */
function report_adeptus_insights_get_plan_features($plan) {
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

/**
 * Handle Stripe configuration endpoint.
 */
function report_adeptus_insights_handle_stripe_config() {
    global $requestmethod;
    if ($requestmethod !== 'GET' && $requestmethod !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_method_not_allowed', 'report_adeptus_insights'),
        ]);
        return;
    }

    try {
        // Forward to Laravel backend
        $response = report_adeptus_insights_forward_to_backend('subscription/config', [], 'GET');
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_get_stripe_config_failed', 'report_adeptus_insights', $e->getMessage()),
        ]);
    }
}

/**
 * Handle create subscription endpoint.
 */
function report_adeptus_insights_handle_create_subscription() {
    global $requestmethod;
    if ($requestmethod !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_method_not_allowed', 'report_adeptus_insights'),
        ]);
        return;
    }

    try {
        // Get input data from JSON body (primary method for API).
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            // Fall back to Moodle parameter functions.
            $input = [
                'plan_id' => optional_param('plan_id', '', PARAM_ALPHANUMEXT),
                'payment_method_id' => optional_param('payment_method_id', '', PARAM_ALPHANUMEXT),
            ];
        }

        // Forward to Laravel backend
        $response = report_adeptus_insights_forward_to_backend('subscription/create', $input);
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_create_subscription_failed', 'report_adeptus_insights', $e->getMessage()),
        ]);
    }
}

/**
 * Handle show subscription details endpoint.
 */
function report_adeptus_insights_handle_show_subscription() {
    global $requestmethod;
    if ($requestmethod !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_method_not_allowed', 'report_adeptus_insights'),
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

/**
 * Handle cancel subscription endpoint.
 */
function report_adeptus_insights_handle_cancel_subscription() {
    global $requestmethod;
    if ($requestmethod !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_method_not_allowed', 'report_adeptus_insights'),
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => get_string('subscription_cancelled_success', 'report_adeptus_insights'),
    ]);
}

/**
 * Handle update subscription endpoint.
 */
function report_adeptus_insights_handle_update_subscription() {
    global $requestmethod;
    if ($requestmethod !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_method_not_allowed', 'report_adeptus_insights'),
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => get_string('subscription_updated_success', 'report_adeptus_insights'),
    ]);
}

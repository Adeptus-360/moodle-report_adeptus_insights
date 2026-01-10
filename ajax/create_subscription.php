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
 * Create subscription AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');

// Check for valid login
require_login();

// Check capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Verify session key
if (!confirm_sesskey()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    debugging('No JSON input received');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

debugging('Subscription creation request received: ' . json_encode($input));

// Validate required fields
$required_fields = ['plan_id', 'payment_method_id', 'billing_email'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        debugging('Missing required field: ' . $field);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required field: ' . $field]);
        exit;
    }
}

// Validate email
if (!filter_var($input['billing_email'], FILTER_VALIDATE_EMAIL)) {
    debugging('Invalid email address: ' . $input['billing_email']);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

try {
    debugging('Creating installation manager');

    // Get installation manager
    $installation_manager = new \report_adeptus_insights\installation_manager();

    debugging('Checking if installation is registered');

    // Check if installation is registered
    if (!$installation_manager->is_registered()) {
        debugging('Installation is not registered');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => get_string('not_registered', 'report_adeptus_insights')]);
        exit;
    }

    debugging('Installation is registered, creating subscription');

    // Create subscription
    $result = $installation_manager->create_subscription(
        $input['plan_id'],
        $input['payment_method_id'],
        $input['billing_email']
    );

    debugging('Subscription creation result: ' . json_encode($result));

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
        ]);
    } else {
        debugging('Subscription creation failed: ' . $result['message']);
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message'],
        ]);
    }
} catch (\Exception $e) {
    debugging('Subscription creation exception: ' . $e->getMessage());
    debugging('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
    ]);
}

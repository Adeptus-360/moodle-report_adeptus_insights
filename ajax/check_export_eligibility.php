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
 * Check export eligibility AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/installation_manager.php');

// Require login and capability
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/adeptus_insights:view', $context);

// Get parameters
$format = required_param('format', PARAM_ALPHA);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'eligible' => false, 'message' => 'Invalid session key']);
    exit;
}

try {
    // Get installation manager
    $installation_manager = new \report_adeptus_insights\installation_manager();

    // Get subscription details (contains tier, exports_remaining, etc.)
    $subscription = $installation_manager->get_subscription_details();

    $response = [
        'success' => true,
        'eligible' => true,
        'message' => 'Export allowed',
    ];

    // Determine if user is on free plan (same logic as check_subscription_status.php)
    $is_free_plan = true; // Default to free
    if ($subscription) {
        $plan_name = strtolower($subscription['plan_name'] ?? '');
        $is_free_plan = (strpos($plan_name, 'free') !== false ||
                         strpos($plan_name, 'trial') !== false ||
                         ($subscription['price'] ?? 0) == 0);
    }

    // Check if user is on free plan
    if ($is_free_plan) {
        // Free plan users can only export PDF
        if ($format !== 'pdf') {
            $response = [
                'success' => true,
                'eligible' => false,
                'message' => 'This export format requires a premium subscription. PDF exports are available on the free plan.',
            ];
        }
    } else {
        // Check export limits for paid users
        $exports_remaining = $subscription['exports_remaining'] ?? 50;

        if ($exports_remaining <= 0) {
            $exports_limit = $subscription['plan_exports_limit'] ?? 50;
            $response = [
                'success' => true,
                'eligible' => false,
                'message' => 'You have reached your monthly export limit of ' . $exports_limit . ' exports.',
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
} catch (Exception $e) {
    error_log('Error in check_export_eligibility.php: ' . $e->getMessage());

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'eligible' => false,
        'message' => 'Error checking export eligibility: ' . $e->getMessage(),
    ]);
}

exit;

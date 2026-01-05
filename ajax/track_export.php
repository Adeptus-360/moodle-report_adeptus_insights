<?php
// This file is part of Moodle - http://moodle.org/
//
// Track export usage for free plan users

// Early logging - before any Moodle loads
$early_log = '/tmp/track_export_debug.log';
file_put_contents($early_log, "\n=== EARLY: " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
file_put_contents($early_log, "track_export.php ENTERED\n", FILE_APPEND);

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
$report_name = required_param('report_name', PARAM_TEXT);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid session key']);
    exit;
}

header('Content-Type: application/json');

try {
    // Debug logging
    $debug_log = '/tmp/track_export_debug.log';
    file_put_contents($debug_log, "\n\n=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
    file_put_contents($debug_log, "Track export called for format: $format, report: $report_name\n", FILE_APPEND);

    // Get installation manager
    $installation_manager = new \report_adeptus_insights\installation_manager();
    $subscription = $installation_manager->get_subscription_details();

    file_put_contents($debug_log, "Subscription: " . print_r($subscription, true) . "\n", FILE_APPEND);

    // Check if user is on free plan
    // Subscription can be either array or object depending on source
    $is_free_plan = true;
    $tier = 'free';
    if ($subscription) {
        // Handle both array and object formats
        if (is_array($subscription)) {
            // Array format - check plan_name or tier
            $plan_name = $subscription['plan_name'] ?? '';
            $tier = $subscription['tier'] ?? '';
            // If no tier field, determine from plan_name
            if (empty($tier) && !empty($plan_name)) {
                $tier = (stripos($plan_name, 'free') !== false) ? 'free' : 'pro';
            }
        } else {
            // Object format
            $tier = $subscription->tier ?? 'free';
        }
        $is_free_plan = ($tier === 'free');
    }

    file_put_contents($debug_log, "Tier: $tier, Is free plan: " . ($is_free_plan ? 'true' : 'false') . "\n", FILE_APPEND);
    
    if ($is_free_plan) {
        // For free plan users, track exports in Moodle database
        // Insert export record
        $export_record = new stdClass();
        $export_record->userid = $USER->id;
        $export_record->reportname = $report_name;
        $export_record->format = $format;
        $export_record->exportedat = time();
        
        $DB->insert_record('adeptus_export_tracking', $export_record);
        
        // Count total exports for this user
        $total_exports = $DB->count_records('adeptus_export_tracking', array('userid' => $USER->id));
        
        echo json_encode([
            'success' => true,
            'message' => 'Export tracked successfully',
            'exports_used' => $total_exports,
            'is_free_plan' => true
        ]);
    } else {
        // For paid plan users, track via backend API
        $api_key = $installation_manager->get_api_key();
        $backend_api_url = $installation_manager->get_api_url();

        file_put_contents($debug_log, "Paid plan - API URL: $backend_api_url\n", FILE_APPEND);
        file_put_contents($debug_log, "API Key present: " . ($api_key ? 'yes (length: ' . strlen($api_key) . ')' : 'no') . "\n", FILE_APPEND);

        if (!$api_key) {
            file_put_contents($debug_log, "ERROR: No API key available\n", FILE_APPEND);
            throw new Exception('No API key available');
        }

        // Call backend API to track export
        $full_url = $backend_api_url . '/subscription/track-export';
        $post_data = json_encode([
            'report_name' => $report_name,
            'format' => $format
        ]);

        file_put_contents($debug_log, "Full URL: $full_url\n", FILE_APPEND);
        file_put_contents($debug_log, "POST data: $post_data\n", FILE_APPEND);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $full_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $api_key,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $backend_response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        file_put_contents($debug_log, "HTTP Code: $http_code\n", FILE_APPEND);
        file_put_contents($debug_log, "Curl Error: " . ($curl_error ?: 'none') . "\n", FILE_APPEND);
        file_put_contents($debug_log, "Response: $backend_response\n", FILE_APPEND);

        if ($http_code === 200 && $backend_response) {
            $backend_data = json_decode($backend_response, true);

            file_put_contents($debug_log, "SUCCESS - Backend data: " . print_r($backend_data, true) . "\n", FILE_APPEND);

            echo json_encode([
                'success' => true,
                'message' => 'Export tracked successfully',
                'exports_used' => $backend_data['exports_used'] ?? 0,
                'exports_remaining' => $backend_data['exports_remaining'] ?? 0,
                'is_free_plan' => false
            ]);
        } else {
            file_put_contents($debug_log, "FAILED - Backend API call failed with code $http_code\n", FILE_APPEND);
            throw new Exception('Backend API call failed');
        }
    }
    
} catch (Exception $e) {
    error_log('Error in track_export.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error tracking export: ' . $e->getMessage()
    ]);
}

exit;
?>

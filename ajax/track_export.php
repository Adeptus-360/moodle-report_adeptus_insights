<?php
// This file is part of Moodle - http://moodle.org/
//
// Track export usage for free plan users

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
    // Get installation manager
    $installation_manager = new \report_adeptus_insights\installation_manager();
    $subscription = $installation_manager->get_subscription_details();

    // Check if user is on free plan (using object notation - subscription is an object)
    $is_free_plan = true;
    if ($subscription) {
        $tier = $subscription->tier ?? 'free';
        $is_free_plan = ($tier === 'free');
    }
    
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
        
        if (!$api_key) {
            throw new Exception('No API key available');
        }
        
        // Call backend API to track export
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $backend_api_url . '/subscription/track-export');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'report_name' => $report_name,
            'format' => $format
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $api_key,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $backend_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $backend_response) {
            $backend_data = json_decode($backend_response, true);
            
            echo json_encode([
                'success' => true,
                'message' => 'Export tracked successfully',
                'exports_used' => $backend_data['exports_used'] ?? 0,
                'exports_remaining' => $backend_data['exports_remaining'] ?? 0,
                'is_free_plan' => false
            ]);
        } else {
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

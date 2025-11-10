<?php
// This file is part of Moodle - http://moodle.org/
//
// Check if user is eligible to export in the requested format

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
    
    // Get subscription status
    $usage_stats = $installation_manager->get_usage_stats();
    
    $response = [
        'success' => true,
        'eligible' => true,
        'message' => 'Export allowed'
    ];
    
    // Check if user is on free plan
    if ($usage_stats['is_free_plan']) {
        // Free plan users can only export PDF
        if ($format !== 'pdf') {
            $response = [
                'success' => true,
                'eligible' => false,
                'message' => 'This export format requires a premium subscription. PDF exports are available on the free plan.'
            ];
        }
    }
    
    // Check export limits for paid users
    if (!$usage_stats['is_free_plan']) {
        $exports_used = $usage_stats['exports_used_this_month'] ?? 0;
        $exports_limit = $usage_stats['plan_exports_limit'] ?? 100;
        
        if ($exports_used >= $exports_limit) {
            $response = [
                'success' => true,
                'eligible' => false,
                'message' => 'You have reached your monthly export limit of ' . $exports_limit . ' exports.'
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
        'message' => 'Error checking export eligibility: ' . $e->getMessage()
    ]);
}

exit;
?>

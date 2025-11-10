<?php
// This file is part of Moodle - http://moodle.org/
//
// Manage recent reports - clear all or remove individual

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Set content type
header('Content-Type: application/json');

// Get parameters
$action = required_param('action', PARAM_TEXT); // 'clear_all' or 'remove_single' - changed to PARAM_TEXT to allow underscores
$reportid = optional_param('reportid', '', PARAM_TEXT); // Changed to PARAM_TEXT for string report names
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Trim whitespace from action parameter
$action = trim($action);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session key']);
    exit;
}

try {
    $userid = $USER->id;
    
    if ($action === 'clear_all') {
        // Clear all recent reports for this user
        $DB->delete_records('adeptus_report_history', ['userid' => $userid]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'All recent reports cleared successfully',
            'action' => 'clear_all'
        ]);
        
    } elseif ($action === 'remove_single') {
        if (empty($reportid)) {
            echo json_encode(['success' => false, 'message' => 'Report ID is required']);
            exit;
        }
        
        // Remove all history entries for this specific report for this user
        $deleted = $DB->delete_records('adeptus_report_history', [
            'userid' => $userid,
            'reportid' => $reportid
        ]);
        
        if ($deleted) {
            echo json_encode([
                'success' => true, 
                'message' => 'Recent report removed successfully',
                'action' => 'remove_single',
                'reportid' => $reportid
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Recent report not found']);
        }
        
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid action: ' . $action . '. Expected: clear_all or remove_single'
        ]);
    }

} catch (Exception $e) {
    error_log('Error in manage_recent_reports.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}

exit; 
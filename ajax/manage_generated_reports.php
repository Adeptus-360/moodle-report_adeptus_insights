<?php
/**
 * AJAX endpoint to manage generated reports
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check for valid login
require_login();

// Check capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Check session key
confirm_sesskey();

// Set JSON response headers
header('Content-Type: application/json');

try {
    $action = required_param('action', PARAM_TEXT);
    $userid = $USER->id;
    
    switch ($action) {
        case 'remove_single':
            $reportid = required_param('reportid', PARAM_TEXT);
            
            // Remove the specific generated report
            $deleted = $DB->delete_records('adeptus_generated_reports', [
                'userid' => $userid,
                'reportid' => $reportid
            ]);
            
            if ($deleted) {
                echo json_encode(['success' => true, 'message' => 'Generated report removed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Generated report not found or already removed']);
            }
            break;
            
        case 'clear_all':
            // Remove all generated reports for the user
            $deleted = $DB->delete_records('adeptus_generated_reports', ['userid' => $userid]);
            
            if ($deleted > 0) {
                echo json_encode(['success' => true, 'message' => 'All generated reports cleared successfully']);
            } else {
                echo json_encode(['success' => true, 'message' => 'No generated reports to clear']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action . '. Expected: clear_all or remove_single']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Error in manage_generated_reports.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error managing generated reports: ' . $e->getMessage()
    ]);
}
?>

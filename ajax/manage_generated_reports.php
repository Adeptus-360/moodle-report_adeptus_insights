<?php
/**
 * AJAX endpoint to manage generated (wizard) reports via backend API
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../classes/api_config.php');

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

    // Get API key and backend URL
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installation_manager = new \report_adeptus_insights\installation_manager();
    $api_key = $installation_manager->get_api_key();
    $backendApiUrl = \report_adeptus_insights\api_config::get_backend_url();

    if (empty($api_key)) {
        echo json_encode([
            'success' => false,
            'message' => 'API key not configured'
        ]);
        exit;
    }

    switch ($action) {
        case 'remove_single':
            $slug = required_param('slug', PARAM_TEXT);

            // Delete the specific wizard report from backend
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/wizard-reports/' . urlencode($slug) . '?user_id=' . $userid);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $api_key
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                echo json_encode(['success' => true, 'message' => $data['message'] ?? 'Report removed successfully']);
            } else {
                $data = json_decode($response, true);
                echo json_encode(['success' => false, 'message' => $data['message'] ?? 'Failed to remove report']);
            }
            break;

        case 'clear_all':
            // Delete all wizard reports for the user from backend
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/wizard-reports');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['user_id' => $userid]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $api_key
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $count = $data['deleted_count'] ?? 0;
                echo json_encode(['success' => true, 'message' => "Deleted {$count} wizard reports"]);
            } else {
                $data = json_decode($response, true);
                echo json_encode(['success' => false, 'message' => $data['message'] ?? 'Failed to clear reports']);
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

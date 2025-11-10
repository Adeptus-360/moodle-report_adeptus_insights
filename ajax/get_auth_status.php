<?php
/**
 * Get Authentication Status AJAX Endpoint
 * Simple endpoint to get authentication status without external functions
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check if user is logged in to Moodle (but don't require plugin authentication)
require_login();

// Check basic Moodle capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Set content type
header('Content-Type: application/json');

try {
    // Include the auth manager
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/token_auth_manager.php');
    
    // Get authentication status
    $auth_manager = new \report_adeptus_insights\token_auth_manager();
    $auth_status = $auth_manager->get_auth_status();
    
    // Get installation data for the login form
    global $DB;
    $install_settings = $DB->get_record('adeptus_install_settings', ['id' => 1]);
    
    if ($install_settings) {
        // Extract site URL from Moodle configuration
        $site_url = $CFG->wwwroot;
        
        // Get admin email from Moodle configuration
        $admin_email = $CFG->supportemail ?? $CFG->admin ?? 'admin@' . parse_url($site_url, PHP_URL_HOST);
        
        // Add installation data to auth status
        $auth_status['installation_info'] = [
            'site_url' => $site_url,
            'admin_email' => $admin_email,
            'api_url' => $install_settings->api_url,
            'installation_id' => $install_settings->installation_id
        ];
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $auth_status
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get authentication status: ' . $e->getMessage(),
        'data' => null
    ]);
}

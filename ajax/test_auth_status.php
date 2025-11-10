<?php
/**
 * Test Authentication Status AJAX Endpoint
 * Simple endpoint to test without authentication requirements
 */

require_once('../../../config.php');

// Set content type
header('Content-Type: application/json');

try {
    // Get basic Moodle configuration
    $site_url = $CFG->wwwroot;
    $admin_email = $CFG->supportemail ?? $CFG->admin ?? 'admin@' . parse_url($site_url, PHP_URL_HOST);
    
    // Get installation data from database
    global $DB;
    $install_settings = $DB->get_record('adeptus_install_settings', ['id' => 1]);
    
    if ($install_settings) {
        $auth_status = [
            'is_registered' => 1,
            'has_api_key' => 1,
            'api_key' => $install_settings->api_key,
            'installation_id' => $install_settings->installation_id,
            'subscription' => [
                'plan_name' => 'Premium',
                'status' => 'active',
                'ai_credits_remaining' => 1000,
                'exports_remaining' => 50
            ],
            'usage' => [
                'reports_generated_this_month' => 12,
                'ai_credits_used_this_month' => 0,
                'current_period_start' => time(),
                'current_period_end' => time() + (30 * 24 * 60 * 60)
            ],
            'installation_info' => [
                'site_url' => $site_url,
                'admin_email' => $admin_email,
                'api_url' => $install_settings->api_url,
                'installation_id' => $install_settings->installation_id
            ]
        ];
    } else {
        $auth_status = [
            'is_registered' => 0,
            'has_api_key' => 0,
            'api_key' => null,
            'installation_id' => null,
            'subscription' => null,
            'usage' => null,
            'installation_info' => [
                'site_url' => $site_url,
                'admin_email' => $admin_email,
                'api_url' => null,
                'installation_id' => null
            ]
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



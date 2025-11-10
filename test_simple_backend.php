<?php
/**
 * Simple Backend Test for Adeptus Insights
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');

// Check for valid login
require_login();

// Set up page
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/test_simple_backend.php'));
$PAGE->set_title('Simple Backend Test');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo '<h1>Simple Backend Test</h1>';

$installation_manager = new \report_adeptus_insights\installation_manager();

echo '<h2>Step 1: Check Installation Status</h2>';
echo '<p>Is Registered: ' . ($installation_manager->is_registered() ? 'Yes' : 'No') . '</p>';
echo '<p>API Key: ' . ($installation_manager->get_api_key() ? 'Set' : 'Not Set') . '</p>';
echo '<p>Backend URL: ' . $installation_manager->get_backend_url() . '</p>';

if ($installation_manager->is_registered()) {
    echo '<h2>Step 2: Test API Request</h2>';
    
    try {
        echo '<p>Testing subscription/config endpoint...</p>';
        $response = $installation_manager->make_api_request('subscription/config', [], 'GET');
        echo '<p>Response: <pre>' . print_r($response, true) . '</pre></p>';
        
        echo '<p>Testing subscription/plans endpoint...</p>';
        $plans_response = $installation_manager->make_api_request('subscription/plans', [], 'POST');
        echo '<p>Plans Response: <pre>' . print_r($plans_response, true) . '</pre></p>';
        
    } catch (Exception $e) {
        echo '<p><strong>Error:</strong> ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p>Plugin not registered. Cannot test backend.</p>';
}

echo $OUTPUT->footer();

<?php
/**
 * Debug Installation Manager
 * Check what methods are actually available
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check if user is logged in
require_login();

// Check capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

echo $OUTPUT->header();
echo '<h1>Debug Installation Manager</h1>';

try {
    echo '<h2>Step 1: Include installation_manager.php</h2>';
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    echo '<p style="color: green;">✓ SUCCESS: installation_manager.php included</p>';
    
    echo '<h2>Step 2: Create installation_manager instance</h2>';
    $installation_manager = new \report_adeptus_insights\installation_manager();
    echo '<p style="color: green;">✓ SUCCESS: installation_manager instance created</p>';
    
    echo '<h2>Step 3: Check available methods</h2>';
    $methods = get_class_methods($installation_manager);
    echo '<h3>Available Methods:</h3>';
    echo '<ul>';
    foreach ($methods as $method) {
        if (strpos($method, 'get_') === 0) {
            echo '<li><strong>' . $method . '</strong></li>';
        }
    }
    echo '</ul>';
    
    echo '<h2>Step 4: Test is_registered()</h2>';
    $is_registered = $installation_manager->is_registered();
    echo '<p>is_registered(): ' . ($is_registered ? 'true' : 'false') . '</p>';
    
    echo '<h2>Step 5: Test get_api_key()</h2>';
    $api_key = $installation_manager->get_api_key();
    echo '<p>get_api_key(): ' . ($api_key ? 'Present (' . substr($api_key, 0, 10) . '...)' : 'Not Available') . '</p>';
    
    echo '<h2>Step 6: Test get_installation_id()</h2>';
    $installation_id = $installation_manager->get_installation_id();
    echo '<p>get_installation_id(): ' . ($installation_id ?: 'Not Available') . '</p>';
    
    echo '<h2>Step 7: Test get_subscription_details()</h2>';
    try {
        $subscription = $installation_manager->get_subscription_details();
        if ($subscription) {
            echo '<p style="color: green;">✓ SUCCESS: get_subscription_details() returned data</p>';
            echo '<pre>' . print_r($subscription, true) . '</pre>';
        } else {
            echo '<p style="color: orange;">⚠ get_subscription_details() returned null</p>';
        }
    } catch (Exception $e) {
        echo '<p style="color: red;">✗ ERROR: ' . $e->getMessage() . '</p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;"><strong>ERROR:</strong> ' . $e->getMessage() . '</p>';
    echo '<p><strong>File:</strong> ' . $e->getFile() . '</p>';
    echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
}

echo '<hr>';
echo '<p><a href="simple_auth_test.php" class="btn btn-primary">Test Authentication</a></p>';
echo '<p><a href="test_auth_system.php" class="btn btn-secondary">Full Test</a></p>';

echo $OUTPUT->footer();

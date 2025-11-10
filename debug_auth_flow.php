<?php
/**
 * Debug Authentication Flow
 * Test the authentication system step by step
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Set page context
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/debug_auth_flow.php'));
$PAGE->set_title('Debug Auth Flow');

echo $OUTPUT->header();

echo '<h1>Debug Authentication Flow</h1>';
echo '<div class="alert alert-info">This page tests the authentication system step by step.</div>';

try {
    echo '<h2>Step 1: Check if user is logged in</h2>';
    if (isloggedin()) {
        echo '<div class="alert alert-success">✓ User is logged in</div>';
        echo '<p>User ID: ' . $USER->id . '</p>';
        echo '<p>User Email: ' . $USER->email . '</p>';
    } else {
        echo '<div class="alert alert-danger">✗ User is not logged in</div>';
        echo $OUTPUT->footer();
        exit;
    }

    echo '<h2>Step 2: Check user capabilities</h2>';
    $context = context_system::instance();
    if (has_capability('report/adeptus_insights:view', $context)) {
        echo '<div class="alert alert-success">✓ User has required capability</div>';
    } else {
        echo '<div class="alert alert-danger">✗ User lacks required capability</div>';
        echo $OUTPUT->footer();
        exit;
    }

    echo '<h2>Step 3: Load installation manager</h2>';
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installation_manager = new \report_adeptus_insights\installation_manager();
    echo '<div class="alert alert-success">✓ Installation manager loaded</div>';

    echo '<h2>Step 4: Check if plugin is registered</h2>';
    if ($installation_manager->is_registered()) {
        echo '<div class="alert alert-success">✓ Plugin is registered</div>';
    } else {
        echo '<div class="alert alert-danger">✗ Plugin is not registered</div>';
        echo $OUTPUT->footer();
        exit;
    }

    echo '<h2>Step 5: Get API key</h2>';
    $api_key = $installation_manager->get_api_key();
    if (!empty($api_key)) {
        echo '<div class="alert alert-success">✓ API key found</div>';
        echo '<p>API Key: ' . substr($api_key, 0, 10) . '...</p>';
    } else {
        echo '<div class="alert alert-danger">✗ No API key found</div>';
        echo $OUTPUT->footer();
        exit;
    }

    echo '<h2>Step 6: Load token auth manager</h2>';
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/token_auth_manager.php');
    $auth_manager = new \report_adeptus_insights\token_auth_manager();
    echo '<div class="alert alert-success">✓ Token auth manager loaded</div>';

    echo '<h2>Step 7: Test check_auth method</h2>';
    $auth_result = $auth_manager->check_auth(false); // Don't redirect
    if ($auth_result) {
        echo '<div class="alert alert-success">✓ Authentication successful!</div>';
    } else {
        echo '<div class="alert alert-danger">✗ Authentication failed</div>';
    }

    echo '<h2>Step 8: Test get_auth_status method</h2>';
    $auth_status = $auth_manager->get_auth_status();
    echo '<pre>' . print_r($auth_status, true) . '</pre>';

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Exception: ' . $e->getMessage() . '</div>';
    echo '<pre>Stack trace: ' . $e->getTraceAsString() . '</pre>';
}

echo '<hr>';
echo '<p><a href="index.php" class="btn btn-primary">Go to Index Page</a></p>';

echo $OUTPUT->footer();

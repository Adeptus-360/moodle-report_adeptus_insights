<?php
/**
 * Adeptus Insights - Subscription Test Script
 * Tests the subscription creation flow
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');

// Check for valid login
require_login();

// Check capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Set up page
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/test_subscription.php'));
$PAGE->set_title('Adeptus Insights - Subscription Test');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

echo '<h1>Subscription Test</h1>';

// Get installation manager
$installation_manager = new \report_adeptus_insights\installation_manager();

echo '<h2>Test Results</h2>';

// Test 1: Check registration
echo '<h3>1. Registration Status</h3>';
if ($installation_manager->is_registered()) {
    echo '<p style="color: green;">✓ Installation is registered</p>';
} else {
    echo '<p style="color: red;">✗ Installation is not registered</p>';
    echo '<p><a href="subscription.php" class="btn btn-primary">Register Installation</a></p>';
    echo $OUTPUT->footer();
    exit;
}

// Test 2: Check API connectivity
echo '<h3>2. API Connectivity</h3>';
try {
    $payment_config = $installation_manager->get_payment_config();
    if ($payment_config && isset($payment_config['success']) && $payment_config['success']) {
        echo '<p style="color: green;">✓ API connectivity successful</p>';
    } else {
        echo '<p style="color: red;">✗ API connectivity failed</p>';
        if (isset($payment_config['message'])) {
            echo '<p>Error: ' . $payment_config['message'] . '</p>';
        }
    }
} catch (Exception $e) {
    echo '<p style="color: red;">✗ API connectivity failed</p>';
    echo '<p>Exception: ' . $e->getMessage() . '</p>';
}

// Test 3: Check available plans
echo '<h3>3. Available Plans</h3>';
try {
    $plans = $installation_manager->get_available_plans();
    if ($plans && isset($plans['success']) && $plans['success']) {
        echo '<p style="color: green;">✓ Plans retrieved successfully</p>';
        echo '<p>Number of plans: ' . count($plans['plans']) . '</p>';
        
        if (!empty($plans['plans'])) {
            echo '<h4>Available Plans:</h4>';
            echo '<ul>';
            foreach ($plans['plans'] as $plan) {
                echo '<li><strong>' . $plan['name'] . '</strong> - ' . $plan['price'] . ' (' . $plan['billing_cycle'] . ')</li>';
            }
            echo '</ul>';
        }
    } else {
        echo '<p style="color: red;">✗ Failed to retrieve plans</p>';
        if (isset($plans['message'])) {
            echo '<p>Error: ' . $plans['message'] . '</p>';
        }
    }
} catch (Exception $e) {
    echo '<p style="color: red;">✗ Failed to retrieve plans</p>';
    echo '<p>Exception: ' . $e->getMessage() . '</p>';
}

// Test 4: Test free plan activation
echo '<h3>4. Free Plan Activation Test</h3>';
try {
    // Get available plans from API
    $plans = $installation_manager->get_available_plans();
    
    if ($plans && isset($plans['success']) && $plans['success'] && !empty($plans['plans'])) {
        // Find a free plan
        $free_plan = null;
        foreach ($plans['plans'] as $plan) {
            if (isset($plan['is_free']) && $plan['is_free']) {
                $free_plan = $plan;
                break;
            }
        }
        
        if ($free_plan) {
            echo '<p>Testing free plan activation for: ' . $free_plan['name'] . '</p>';
            
            $result = $installation_manager->activate_free_plan($free_plan['id']);
            
            if ($result['success']) {
                echo '<p style="color: green;">✓ Free plan activation successful</p>';
                echo '<p>Message: ' . $result['message'] . '</p>';
            } else {
                echo '<p style="color: red;">✗ Free plan activation failed</p>';
                echo '<p>Error: ' . $result['message'] . '</p>';
            }
        } else {
            echo '<p style="color: orange;">⚠ No free plan found in available plans</p>';
        }
    } else {
        echo '<p style="color: red;">✗ Failed to get available plans for testing</p>';
        if (isset($plans['message'])) {
            echo '<p>Error: ' . $plans['message'] . '</p>';
        }
    }
} catch (Exception $e) {
    echo '<p style="color: red;">✗ Free plan activation test failed</p>';
    echo '<p>Exception: ' . $e->getMessage() . '</p>';
}

// Test 5: Check current subscription
echo '<h3>5. Current Subscription</h3>';
$subscription = $installation_manager->get_subscription_details();
if ($subscription) {
    echo '<p style="color: green;">✓ Subscription found</p>';
    echo '<ul>';
    echo '<li><strong>Plan:</strong> ' . ($subscription->plan_name ?? 'Unknown') . '</li>';
    echo '<li><strong>Status:</strong> ' . ($subscription->status ?? 'Unknown') . '</li>';
    echo '<li><strong>AI Credits:</strong> ' . ($subscription->ai_credits_remaining ?? 0) . '</li>';
    echo '<li><strong>Exports:</strong> ' . ($subscription->exports_remaining ?? 0) . '</li>';
    echo '</ul>';
} else {
    echo '<p style="color: orange;">⚠ No subscription found</p>';
}

echo '<h2>Next Steps</h2>';
echo '<ul>';
echo '<li><a href="subscription.php" class="btn btn-primary">Go to Subscription Management</a></li>';
echo '<li><a href="index.php" class="btn btn-success">Go to Dashboard</a></li>';
echo '</ul>';

echo $OUTPUT->footer(); 
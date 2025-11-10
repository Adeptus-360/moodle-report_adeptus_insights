<?php
/**
 * Adeptus Insights - Subscription Installation Step
 * 
 * This page handles the subscription setup during plugin installation
 * It automatically creates a free subscription and shows upgrade options
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/subscription_installation_step.php'));
$PAGE->set_title(get_string('pluginname', 'report_adeptus_insights') . ' - Subscription Setup');
$PAGE->set_heading(get_string('pluginname', 'report_adeptus_insights') . ' - Subscription Setup');

// Load installation manager
$installation_manager = new \report_adeptus_insights\installation_manager();

// Check if plugin is registered, if not redirect to registration
if (!$installation_manager->is_registered()) {
    redirect(new moodle_url('/report/adeptus_insights/register_plugin.php'));
}

// Check if installation is already completed
$installation_completed = get_config('report_adeptus_insights', 'installation_completed');
if ($installation_completed) {
    redirect(new moodle_url('/report/adeptus_insights/index.php'));
}

// Set current installation step
set_config('installation_step', '2', 'report_adeptus_insights');

// Handle form submissions
$action = optional_param('action', '', PARAM_ALPHA);
$plan_id = optional_param('plan_id', 0, PARAM_INT);

if ($action === 'completeinstallation' && confirm_sesskey()) {
    // Mark installation as completed
    set_config('installation_completed', '1', 'report_adeptus_insights');
    set_config('installation_step', '3', 'report_adeptus_insights');
    
    redirect(new moodle_url('/report/adeptus_insights/index.php'), 
            get_string('installation_complete', 'report_adeptus_insights'), 
            null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'upgrade_plan' && confirm_sesskey() && $plan_id) {
    // Redirect to Stripe billing portal for upgrade
    $result = $installation_manager->create_billing_portal_session();
    
    if ($result['success']) {
        redirect($result['data']['url']);
    } else {
        redirect(new moodle_url('/report/adeptus_insights/subscription_installation_step.php'), 
                $result['message'], null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Ensure free subscription exists before showing the page
$current_subscription = $installation_manager->get_subscription_details();

// If no local subscription record, try to get it from backend first
if (!$current_subscription) {
    debugging('No local subscription found, checking backend...');
    
    // Use the existing check_subscription_status method to sync from backend
    $backend_sync_result = $installation_manager->check_subscription_status();
    
    if ($backend_sync_result) {
        debugging('Successfully synced subscription from backend');
        // Refresh subscription data
        $current_subscription = $installation_manager->get_subscription_details();
    } else {
        debugging('Backend sync failed, creating new subscription...');
        
        // Only create if backend sync failed
        try {
            $user = $USER;
            $result = $installation_manager->setup_starter_subscription($user->email, fullname($user));
            
            if (!$result) {
                debugging('Automatic subscription creation failed, trying manual...');
                $result = $installation_manager->activate_free_plan_manually();
            }
            
            if ($result) {
                debugging('Subscription created successfully');
                // Refresh subscription data
                $current_subscription = $installation_manager->get_subscription_details();
            } else {
                debugging('Failed to create subscription');
            }
        } catch (\Exception $e) {
            debugging('Exception during subscription creation: ' . $e->getMessage());
        }
    }
}

// Get available plans for upgrades
$available_plans = $installation_manager->get_available_plans();

// Prepare template context
$templatecontext = [
    'user_fullname' => fullname($USER),
    'user_email' => $USER->email,
    'is_registered' => $installation_manager->is_registered(),
    'sesskey' => sesskey(),
    'current_subscription' => $current_subscription,
    'available_plans' => $available_plans['plans'] ?? [],
    'installation_step' => get_config('report_adeptus_insights', 'installation_step', '2')
];

// Output the page
echo $OUTPUT->header();

// Render the subscription installation template
echo $OUTPUT->render_from_template('report_adeptus_insights/subscription_installation_step', $templatecontext);

echo $OUTPUT->footer();

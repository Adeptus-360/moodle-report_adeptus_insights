<?php
/**
 * Adeptus Insights - Diagnostic Script
 * Beautiful, responsive diagnostic interface
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/token_auth_manager.php');

// Use the new authentication manager
$auth_manager = new \report_adeptus_insights\token_auth_manager();
$auth_status = $auth_manager->get_auth_status();
if (!$auth_status['user_authorized'] || !$auth_status['has_api_key']) {
    // Redirect to main page if not authenticated
    redirect(new moodle_url('/report/adeptus_insights/index.php'));
}

// Set up page
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/diagnostic.php'));
$PAGE->set_title('Adeptus Insights - Diagnostic');
$PAGE->set_pagelayout('standard');

// Get installation manager
$installation_manager = new \report_adeptus_insights\installation_manager();

// Get authentication status
$auth_status = $auth_manager->get_auth_status();

// Prepare template context
global $USER;
$templatecontext = [
    'user_fullname' => $USER->firstname . ' ' . $USER->lastname,
    'is_registered' => $auth_status['is_registered'],
    'api_key' => $auth_status['has_api_key'] ? 'Present' : 'Not Available',
    'installation_id' => $auth_status['installation_id'],
    'auth_status' => $auth_status, // Pass full auth status to template
];

// Database tables information
global $DB;
require_once($CFG->libdir . '/ddllib.php');
$dbman = $DB->get_manager();

$tables = [
    'adeptus_install_settings',
    'adeptus_subscription_status',
    'adeptus_reports'
];

$database_tables = [];
foreach ($tables as $table) {
    $table_obj = new xmldb_table($table);
    $exists = $dbman->table_exists($table_obj);
    $records = $exists ? $DB->count_records($table) : 0;
    
    $database_tables[] = [
        'name' => $table,
        'exists' => $exists,
        'records' => $records
    ];
}
$templatecontext['database_tables'] = $database_tables;

// API connectivity test
try {
    $payment_config = $installation_manager->get_payment_config();
    if ($payment_config && isset($payment_config['success']) && $payment_config['success']) {
        $templatecontext['api_connected'] = true;
        $templatecontext['api_error'] = null;
    } else {
        $templatecontext['api_connected'] = false;
        $templatecontext['api_error'] = $payment_config['message'] ?? 'Unknown error';
    }
} catch (Exception $e) {
    $templatecontext['api_connected'] = false;
    $templatecontext['api_error'] = $e->getMessage();
}

// Available plans test
try {
    $plans = $installation_manager->get_available_plans();
    if ($plans && isset($plans['success']) && $plans['success']) {
        $templatecontext['plans_available'] = true;
        $templatecontext['plans_count'] = count($plans['plans']);
        $templatecontext['plans_error'] = null;
        
        // Add plans list for display
        if (!empty($plans['plans'])) {
            $templatecontext['plans_list'] = true;
            $templatecontext['plans'] = $plans['plans'];
        }
    } else {
        $templatecontext['plans_available'] = false;
        $templatecontext['plans_count'] = 0;
        $templatecontext['plans_error'] = $plans['message'] ?? 'Unknown error';
        $templatecontext['user_friendly_message'] = $plans['user_friendly_message'] ?? null;
    }
} catch (Exception $e) {
    $templatecontext['plans_available'] = false;
    $templatecontext['plans_count'] = 0;
    $templatecontext['plans_error'] = $e->getMessage();
    $templatecontext['user_friendly_message'] = 'Failed to get available plans: ' . $e->getMessage();
}

// Current subscription details
$subscription = $installation_manager->get_subscription_details();
if ($subscription) {
    $templatecontext['current_subscription'] = [
        'plan_name' => $subscription->plan_name ?? 'Unknown Plan',
        'price' => $subscription->price ?? '£0.00',
        'billing_cycle' => $subscription->billing_cycle ?? 'monthly',
        'status' => $subscription->status ?? 'active',
        'ai_credits_remaining' => $subscription->ai_credits_remaining ?? 0,
        'exports_remaining' => $subscription->exports_remaining ?? 0,
        'next_billing' => isset($subscription->current_period_end) ? 
            date('F j, Y', $subscription->current_period_end) : null,
    ];
} else {
    $templatecontext['current_subscription'] = null;
}

// System information
$templatecontext['moodle_version'] = $CFG->version;
$templatecontext['php_version'] = PHP_VERSION;
$templatecontext['database_type'] = $CFG->dbtype;
$templatecontext['last_updated'] = date('F j, Y g:i A');

// Start output
echo $OUTPUT->header();

// Render the template
echo $OUTPUT->render_from_template('report_adeptus_insights/diagnostic', $templatecontext);

echo $OUTPUT->footer(); 
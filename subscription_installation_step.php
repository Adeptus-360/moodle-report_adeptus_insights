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

// Transform and organize plans by tier and billing interval
// Only include plans for Adeptus Insights (product_key = 'insights')
$monthly_plans = [];
$yearly_plans = [];
$has_yearly_plans = false;

if (!empty($available_plans['plans'])) {
    foreach ($available_plans['plans'] as $plan) {
        // Filter to only show Insights plans
        $product_key = $plan['product_key'] ?? '';
        if ($product_key !== 'insights') {
            continue;
        }

        $billing_interval = $plan['billing_interval'] ?? 'monthly';
        $tier = $plan['tier'] ?? 'free';

        // Handle price - can be object or string
        $price = $plan['price'] ?? ['cents' => 0, 'formatted' => 'Free'];
        $price_formatted = 'Free';
        $price_cents = 0;
        if (is_array($price)) {
            $price_formatted = $price['formatted'] ?? 'Free';
            $price_cents = $price['cents'] ?? 0;
        } else {
            $price_formatted = $price;
        }

        // Handle limits
        $limits = $plan['limits'] ?? [];
        $limit_features = $limits['features'] ?? [];

        // Format limit values (handle -1 as unlimited)
        $format_limit = function($value, $suffix = '') {
            if ($value === -1 || $value === null) {
                return 'Unlimited';
            }
            return number_format($value) . $suffix;
        };

        // Determine current plan
        $is_current = false;
        if ($current_subscription && isset($current_subscription['plan_name'])) {
            $is_current = (strtolower($plan['name']) === strtolower($current_subscription['plan_name']));
        }

        // Build transformed plan
        $transformed_plan = [
            'id' => $plan['id'] ?? 0,
            'tier' => $tier,
            'name' => $plan['name'] ?? 'Unknown',
            'short_name' => ucfirst($tier), // Free, Pro, Enterprise
            'description' => $plan['description'] ?? '',
            'price_formatted' => $price_formatted,
            'price_cents' => $price_cents,
            'billing_interval' => $billing_interval,
            'is_free' => $tier === 'free',
            'is_pro' => $tier === 'pro',
            'is_enterprise' => $tier === 'enterprise',
            'is_current' => $is_current,
            'is_popular' => $plan['is_popular'] ?? ($tier === 'pro'),

            // Formatted limits for display
            'tokens_limit' => $format_limit($limits['tokens_per_month'] ?? 50000),
            'tokens_raw' => $limits['tokens_per_month'] ?? 50000,
            'exports_limit' => $format_limit($limits['exports_per_month'] ?? $limits['exports'] ?? 3),
            'exports_raw' => $limits['exports_per_month'] ?? $limits['exports'] ?? 3,
            'reports_limit' => $format_limit($limits['reports_total_limit'] ?? 10),
            'reports_raw' => $limits['reports_total_limit'] ?? 10,
            'export_formats' => implode(', ', array_map('strtoupper', $limits['export_formats'] ?? ['pdf'])),

            // Feature flags for conditional display
            'has_ai_assistant' => $limit_features['ai_assistant'] ?? true,
            'has_scheduled_reports' => $limit_features['scheduled_reports'] ?? false,
            'has_bulk_export' => $limit_features['bulk_export'] ?? false,
            'has_api_access' => $limit_features['api_access'] ?? false,
            'has_custom_reports' => $limit_features['custom_reports'] ?? false,

            // Human-readable features list from API
            'features' => $plan['features'] ?? [],

            // Stripe integration
            'stripe_product_id' => $plan['stripe_product_id'] ?? null,
            'stripe_configured' => $plan['stripe_configured'] ?? false,
        ];

        // Organize by billing interval
        if ($billing_interval === 'yearly' || $billing_interval === 'annual') {
            $yearly_plans[$tier] = $transformed_plan;
            $has_yearly_plans = true;
        } else {
            $monthly_plans[$tier] = $transformed_plan;
        }
    }
}

// Sort plans by tier order: free, pro, enterprise
$tier_order = ['free' => 0, 'pro' => 1, 'enterprise' => 2];
$sort_by_tier = function($a, $b) use ($tier_order) {
    return ($tier_order[$a['tier']] ?? 99) - ($tier_order[$b['tier']] ?? 99);
};

usort($monthly_plans, $sort_by_tier);
usort($yearly_plans, $sort_by_tier);

// Calculate annual savings if yearly plans exist
if ($has_yearly_plans) {
    foreach ($yearly_plans as &$yearly_plan) {
        $tier = $yearly_plan['tier'];
        if (isset($monthly_plans[$tier])) {
            $monthly_annual_cost = $monthly_plans[$tier]['price_cents'] * 12;
            $yearly_cost = $yearly_plan['price_cents'];
            if ($monthly_annual_cost > 0 && $yearly_cost < $monthly_annual_cost) {
                $savings_percent = round((($monthly_annual_cost - $yearly_cost) / $monthly_annual_cost) * 100);
                $yearly_plan['savings_percent'] = $savings_percent;
                $yearly_plan['has_savings'] = $savings_percent > 0;
            }
        }
    }
    unset($yearly_plan);
}

// Prepare template context
$templatecontext = [
    'user_fullname' => fullname($USER),
    'user_email' => $USER->email,
    'is_registered' => $installation_manager->is_registered(),
    'sesskey' => sesskey(),
    'current_subscription' => $current_subscription,
    'monthly_plans' => array_values($monthly_plans),
    'yearly_plans' => array_values($yearly_plans),
    'has_yearly_plans' => $has_yearly_plans,
    'plans_json' => json_encode([
        'monthly' => array_values($monthly_plans),
        'yearly' => array_values($yearly_plans),
    ]),
    'installation_step' => get_config('report_adeptus_insights', 'installation_step', '2')
];

// Output the page
echo $OUTPUT->header();

// Render the subscription installation template
echo $OUTPUT->render_from_template('report_adeptus_insights/subscription_installation_step', $templatecontext);

echo $OUTPUT->footer();

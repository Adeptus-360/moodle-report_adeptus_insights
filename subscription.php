<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Subscription management page for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
$PAGE->set_url(new moodle_url('/report/adeptus_insights/subscription.php'));
$PAGE->set_title(get_string('subscription_management', 'report_adeptus_insights'));
// $PAGE->set_heading(get_string('subscription_management', 'report_adeptus_insights'));
$PAGE->set_pagelayout('standard');

// Get installation manager
$installation_manager = new \report_adeptus_insights\installation_manager();

// Check if plugin is registered, if not redirect to registration
if (!$installation_manager->is_registered()) {
    redirect(new moodle_url('/report/adeptus_insights/register_plugin.php'));
}

// Check if installation is completed - if not, redirect to installation step
$installation_completed = get_config('report_adeptus_insights', 'installation_completed');
if (!$installation_completed) {
    redirect(new moodle_url('/report/adeptus_insights/subscription_installation_step.php'));
}

// Handle form submissions
$action = optional_param('action', '', PARAM_ALPHA);
$plan_id = optional_param('plan_id', 0, PARAM_INT);

if ($action === 'cancel_subscription' && confirm_sesskey()) {
    $result = $installation_manager->cancel_subscription();

    if ($result['success']) {
        redirect(
            new moodle_url('/report/adeptus_insights/subscription.php'),
            $result['message'],
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            new moodle_url('/report/adeptus_insights/subscription.php'),
            $result['message'],
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

if ($action === 'update_plan' && confirm_sesskey() && $plan_id) {
    $result = $installation_manager->update_subscription_plan($plan_id);

    if ($result['success']) {
        redirect(
            new moodle_url('/report/adeptus_insights/subscription.php'),
            $result['message'],
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            new moodle_url('/report/adeptus_insights/subscription.php'),
            $result['message'],
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Get current subscription details and available plans
$subscription = $installation_manager->get_subscription_details();

// If no subscription found, try to sync from backend or create one
if (!$subscription) {
    // Try to sync subscription from backend
    $backend_sync_result = $installation_manager->check_subscription_status();

    if ($backend_sync_result) {
        // Refresh subscription data
        $subscription = $installation_manager->get_subscription_details();
    } else {
        // Create a free subscription if none exists
        try {
            $result = $installation_manager->setup_starter_subscription($USER->email, fullname($USER));

            if (!$result) {
                $result = $installation_manager->activate_free_plan_manually();
            }

            if ($result) {
                // Refresh subscription data
                $subscription = $installation_manager->get_subscription_details();
            }
        } catch (\Exception $e) {
            // Silently ignore validation errors - subscription refresh is optional.
        }
    }
}

$available_plans = $installation_manager->get_available_plans();
$payment_config = $installation_manager->get_payment_config();

// Check for any errors from installation manager
$last_error = $installation_manager->get_last_error();
if ($last_error) {
    \core\notification::error($last_error['message']);
    $installation_manager->clear_last_error();
}

// Start output
echo $OUTPUT->header();

// Get current plan price for comparison
$current_plan_price = 0;
if ($subscription && isset($subscription['price'])) {
    $current_plan_price = floatval(str_replace(['£', ','], '', $subscription['price']));
}

// Prepare template context
$templatecontext = [
    'user_fullname' => $USER->firstname . ' ' . $USER->lastname,
    'user_email' => $USER->email,
    'is_registered' => $installation_manager->is_registered(),
    'sesskey' => sesskey(),
    'current_plan_price' => $current_plan_price,
];

// Add payment config safely
if ($payment_config && isset($payment_config['success']) && $payment_config['success']) {
    $templatecontext['payment_config'] = json_encode($payment_config['data'], JSON_HEX_APOS | JSON_HEX_QUOT);
} else {
    $templatecontext['payment_config'] = 'null';
}

// Add current subscription if exists
if ($subscription) {
    // Helper function to convert date strings to formatted dates
    $formatDate = function ($dateValue) {
        if (empty($dateValue)) {
            return 'N/A';
        }

        // If it's already a timestamp (integer)
        if (is_numeric($dateValue)) {
            return date('F j, Y', $dateValue);
        }

        // If it's a date string, try to parse it
        if (is_string($dateValue)) {
            $timestamp = strtotime($dateValue);
            if ($timestamp !== false) {
                return date('F j, Y', $timestamp);
            }
        }

        return 'N/A';
    };

    // Check if current plan is free
    $isFreePlan = false;
    if (isset($subscription['price'])) {
        $price = floatval(str_replace(['£', ','], '', $subscription['price']));
        $isFreePlan = ($price == 0);
    }

    $templatecontext['current_subscription'] = [
        'plan_name' => $subscription['plan_name'] ?? 'Unknown Plan',
        'price' => $subscription['price'] ?? '£0.00',
        'billing_cycle' => $subscription['billing_cycle'] ?? 'monthly',
        'status' => $subscription['status'] ?? 'active',
        'ai_credits_remaining' => $subscription['ai_credits_remaining'] ?? 0,
        'exports_remaining' => $subscription['exports_remaining'] ?? 0,
        'next_billing' => $formatDate($subscription['current_period_end'] ?? null),
        'is_trial' => $subscription['is_trial'] ?? false,
        'trial_ends_at' => $formatDate($subscription['trial_ends_at'] ?? null),
        'is_cancelled' => $subscription['is_cancelled'] ?? false,
        'is_active' => $subscription['is_active'] ?? true,
        'has_payment_issues' => $subscription['has_payment_issues'] ?? false,
        'should_disable_api_access' => $subscription['should_disable_api_access'] ?? false,
        'status_message' => $subscription['status_message'] ?? 'Active subscription',
        'is_free_plan' => $isFreePlan,
        // Add period dates for billing period card
        'current_period_start' => $formatDate($subscription['current_period_start'] ?? null),
        'current_period_end' => $formatDate($subscription['current_period_end'] ?? null),
        // Enhanced status information
        'status_details' => $subscription['status_details'] ?? [],
        'cancellation_info' => $subscription['cancellation_info'] ?? [],
        'payment_info' => $subscription['payment_info'] ?? [],
        // Legacy fields for backward compatibility
        'cancel_at_period_end' => $subscription['cancel_at_period_end'] ?? false,
        'cancelled_at' => $subscription['cancelled_at'] ?? null,
        'failed_payment_attempts' => $subscription['failed_payment_attempts'] ?? 0,
        'last_payment_failed_at' => $subscription['last_payment_failed_at'] ?? null,
        'last_payment_succeeded_at' => $subscription['last_payment_succeeded_at'] ?? null,
        // Token-based usage metrics
        'tokens_used' => $subscription['tokens_used'] ?? 0,
        'tokens_remaining' => $subscription['tokens_remaining'] ?? -1,
        'tokens_limit' => $subscription['tokens_limit'] ?? 50000,
        'tokens_used_formatted' => $subscription['tokens_used_formatted'] ?? '0',
        'tokens_remaining_formatted' => $subscription['tokens_remaining_formatted'] ?? '50K',
        'tokens_limit_formatted' => $subscription['tokens_limit_formatted'] ?? '50K',
        'tokens_usage_percent' => $subscription['tokens_usage_percent'] ?? 0,
    ];
}

// Add available plans with upgrade/downgrade logic
// Only include plans for Adeptus Insights (product_key = 'insights')
if (!empty($available_plans['plans'])) {
    $plans = [];
    foreach ($available_plans['plans'] as $plan) {
        // Filter to only show Insights plans
        $product_key = $plan['product_key'] ?? '';
        if ($product_key !== 'insights') {
            continue;
        }

        // Handle price - can be object or string
        $price = $plan['price'] ?? 'Free';
        if (is_array($price)) {
            $price = $price['formatted'] ?? 'Free';
        }

        // Handle limits
        $limits = $plan['limits'] ?? [];

        $is_current = false;
        if ($subscription && isset($subscription['plan_name'])) {
            $is_current = (strtolower($plan['name']) === strtolower($subscription['plan_name']));
        }

        // Determine if this is an upgrade or downgrade
        $plan_price = 0;
        if (is_array($plan['price'])) {
            $plan_price = ($plan['price']['cents'] ?? 0) / 100;
        } else {
            $plan_price = floatval(str_replace(['$', '£', ',', '/mo'], '', $plan['price']));
        }
        $is_upgrade = $plan_price > $current_plan_price;
        $is_downgrade = $plan_price < $current_plan_price;

        $plans[] = [
            'id' => $plan['id'],
            'name' => $plan['name'],
            'price' => $price,
            'billing_cycle' => $plan['billing_interval'] ?? $plan['billing_cycle'] ?? 'monthly',
            'description' => $plan['description'] ?? '',
            'ai_credits' => $limits['ai_credits_basic'] ?? $plan['ai_credits'] ?? 0,
            'ai_credits_pro' => $limits['ai_credits_premium'] ?? $plan['ai_credits_pro'] ?? 0,
            'ai_credits_basic' => $limits['ai_credits_basic'] ?? $plan['ai_credits_basic'] ?? 0,
            'exports' => $limits['exports'] ?? $limits['exports_per_month'] ?? $plan['exports'] ?? 0,
            'is_free' => ($plan['tier'] ?? '') === 'free',
            'is_current' => $is_current,
            'is_upgrade' => $is_upgrade,
            'is_downgrade' => $is_downgrade,
            'features' => $plan['features'] ?? [],
            'stripe_product_id' => $plan['stripe_product_id'] ?? null,
        ];
    }
    $templatecontext['plans'] = $plans;
} else {
    $templatecontext['plans'] = [];
}

// Add usage statistics - this will be used by the analytics cards
$usage_stats = $installation_manager->get_usage_stats();
if ($usage_stats) {
    $templatecontext['usage'] = $usage_stats;
} else {
    $templatecontext['usage'] = [
        'ai_credits_used_this_month' => 0,
        'reports_generated_this_month' => 0,
        'current_period_start' => null,
        'current_period_end' => null,
    ];
}

// Debug: Show the data being passed to template
// echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
// echo '<h3>Debug: Template Context Data</h3>';
// echo '<h4>Subscription Data:</h4>';
// echo '<pre>' . print_r($subscription, true) . '</pre>';
// echo '<h4>Available Plans:</h4>';
// echo '<pre>' . print_r($available_plans, true) . '</pre>';
// echo '<h4>Usage Stats:</h4>';
// echo '<pre>' . print_r($usage_stats, true) . '</pre>';
// echo '<h4>Template Context:</h4>';
// echo '<pre>' . print_r($templatecontext, true) . '</pre>';
// echo '</div>';

// Render the template
echo $OUTPUT->render_from_template('report_adeptus_insights/subscription', $templatecontext);

echo $OUTPUT->footer();

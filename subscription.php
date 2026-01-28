<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by.
// the Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Subscription management page for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Force Boost theme for consistent plugin UI.
$CFG->theme = 'boost';

require_once($CFG->libdir . '/adminlib.php');

// Check for valid login.
require_login();

// Check capabilities.
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Set up page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/adeptus_insights/subscription.php'));
$PAGE->set_title(get_string('subscription_management', 'report_adeptus_insights'));
$PAGE->set_pagelayout('standard');

// Get installation manager.
$installationmanager = new \report_adeptus_insights\installation_manager();

// Check if plugin is registered, if not redirect to registration.
if (!$installationmanager->is_registered()) {
    redirect(new moodle_url('/report/adeptus_insights/register_plugin.php'));
}

// Check if installation is completed - if not, redirect to installation step.
$installationcompleted = get_config('report_adeptus_insights', 'installation_completed');
if (!$installationcompleted) {
    redirect(new moodle_url('/report/adeptus_insights/subscription_installation_step.php'));
}

// Handle form submissions.
$action = optional_param('action', '', PARAM_ALPHA);
$planid = optional_param('plan_id', 0, PARAM_INT);

if ($action === 'cancel_subscription' && confirm_sesskey()) {
    $result = $installationmanager->cancel_subscription();

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

if ($action === 'update_plan' && confirm_sesskey() && $planid) {
    $result = $installationmanager->update_subscription_plan($planid);

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

// Get current subscription details and available plans.
$subscription = $installationmanager->get_subscription_details();

// If no subscription found, try to sync from backend or create one.
if (!$subscription) {
    // Try to sync subscription from backend.
    $backendsyncresult = $installationmanager->check_subscription_status();

    if ($backendsyncresult) {
        // Refresh subscription data.
        $subscription = $installationmanager->get_subscription_details();
    } else {
        // Create a free subscription if none exists.
        try {
            $result = $installationmanager->setup_starter_subscription($USER->email, fullname($USER));

            if (!$result) {
                $result = $installationmanager->activate_free_plan_manually();
            }

            if ($result) {
                // Refresh subscription data.
                $subscription = $installationmanager->get_subscription_details();
            }
        } catch (\Exception $e) {
            // Silently ignore validation errors - subscription refresh is optional.
            debugging('Subscription refresh failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

$availableplans = $installationmanager->get_available_plans();
$paymentconfig = $installationmanager->get_payment_config();

// Get report usage data (cumulative counts) from subscriptions/status endpoint.
$usagewithreports = $installationmanager->get_subscription_with_usage();
if ($subscription && $usagewithreports) {
    // Merge report tracking data into subscription array.
    $subscription['reports_total'] = $usagewithreports['reports_total'] ?? 0;
    $subscription['reports_remaining'] = $usagewithreports['reports_remaining'] ?? 0;
    $subscription['reports_limit'] = $usagewithreports['reports_limit'] ?? 10;
}

// Check for any errors from installation manager.
$lasterror = $installationmanager->get_last_error();
if ($lasterror) {
    \core\notification::error($lasterror['message']);
    $installationmanager->clear_last_error();
}

// Load required CSS.
$PAGE->requires->css('/report/adeptus_insights/styles/subscription.css');

// Start output.
echo $OUTPUT->header();

// Get current plan price for comparison.
$currentplanprice = 0;
if ($subscription && isset($subscription['price'])) {
    $currentplanprice = floatval(str_replace(['£', ','], '', $subscription['price']));
}

// Prepare template context.
$templatecontext = [
    'user_fullname' => $USER->firstname . ' ' . $USER->lastname,
    'user_email' => $USER->email,
    'is_registered' => $installationmanager->is_registered(),
    'sesskey' => sesskey(),
    'current_plan_price' => $currentplanprice,
];

// Add payment config safely.
if ($paymentconfig && isset($paymentconfig['success']) && $paymentconfig['success']) {
    $templatecontext['payment_config'] = json_encode($paymentconfig['data'], JSON_HEX_APOS | JSON_HEX_QUOT);
} else {
    $templatecontext['payment_config'] = 'null';
}

// Add current subscription if exists.
if ($subscription) {
    // Helper function to convert date strings to formatted dates.
    $formatdate = function ($datevalue) {
        if (empty($datevalue)) {
            return 'N/A';
        }

        // If it's already a timestamp (integer).
        if (is_numeric($datevalue)) {
            return date('F j, Y', $datevalue);
        }

        // If it's a date string, try to parse it.
        if (is_string($datevalue)) {
            $timestamp = strtotime($datevalue);
            if ($timestamp !== false) {
                return date('F j, Y', $timestamp);
            }
        }

        return 'N/A';
    };

    // Check if current plan is free.
    $isfreeplan = false;
    if (isset($subscription['price'])) {
        $price = floatval(str_replace(['£', ','], '', $subscription['price']));
        $isfreeplan = ($price == 0);
    }

    $templatecontext['current_subscription'] = [
        'plan_name' => $subscription['plan_name'] ?? 'Unknown Plan',
        'price' => $subscription['price'] ?? '£0.00',
        'billing_cycle' => $subscription['billing_cycle'] ?? 'monthly',
        'status' => $subscription['status'] ?? 'active',
        'ai_credits_remaining' => $subscription['ai_credits_remaining'] ?? 0,
        'exports_remaining' => $subscription['exports_remaining'] ?? 0,
        'next_billing' => $formatdate($subscription['current_period_end'] ?? null),
        'is_trial' => $subscription['is_trial'] ?? false,
        'trial_ends_at' => $formatdate($subscription['trial_ends_at'] ?? null),
        'is_cancelled' => $subscription['is_cancelled'] ?? false,
        'is_active' => $subscription['is_active'] ?? true,
        'has_payment_issues' => $subscription['has_payment_issues'] ?? false,
        'should_disable_api_access' => $subscription['should_disable_api_access'] ?? false,
        'status_message' => $subscription['status_message'] ?? 'Active subscription',
        'is_free_plan' => $isfreeplan,
        // Add period dates for billing period card.
        'current_period_start' => $formatdate($subscription['current_period_start'] ?? null),
        'current_period_end' => $formatdate($subscription['current_period_end'] ?? null),
        // Enhanced status information.
        'status_details' => $subscription['status_details'] ?? [],
        'cancellation_info' => $subscription['cancellation_info'] ?? [],
        'payment_info' => $subscription['payment_info'] ?? [],
        // Legacy fields for backward compatibility.
        'cancel_at_period_end' => $subscription['cancel_at_period_end'] ?? false,
        'cancelled_at' => $subscription['cancelled_at'] ?? null,
        'failed_payment_attempts' => $subscription['failed_payment_attempts'] ?? 0,
        'last_payment_failed_at' => $subscription['last_payment_failed_at'] ?? null,
        'last_payment_succeeded_at' => $subscription['last_payment_succeeded_at'] ?? null,
        // Token-based usage metrics.
        'tokens_used' => $subscription['tokens_used'] ?? 0,
        'tokens_remaining' => $subscription['tokens_remaining'] ?? -1,
        'tokens_limit' => $subscription['tokens_limit'] ?? 50000,
        'tokens_used_formatted' => $subscription['tokens_used_formatted'] ?? '0',
        'tokens_remaining_formatted' => $subscription['tokens_remaining_formatted'] ?? '50K',
        'tokens_limit_formatted' => $subscription['tokens_limit_formatted'] ?? '50K',
        'tokens_usage_percent' => $subscription['tokens_usage_percent'] ?? 0,
        // Report usage metrics.
        'reports_total' => $subscription['reports_total'] ?? 0,
        'reports_remaining' => $subscription['reports_remaining'] ?? 0,
        'reports_limit' => $subscription['reports_limit'] ?? 10,
    ];
}

// Add available plans with upgrade/downgrade logic.
// Only include plans for Adeptus Insights (product_key = 'insights').
if (!empty($availableplans['plans'])) {
    $plans = [];
    foreach ($availableplans['plans'] as $plan) {
        // Filter to only show Insights plans.
        $productkey = $plan['product_key'] ?? '';
        if ($productkey !== 'insights') {
            continue;
        }

        // Handle price - can be object or string.
        $price = $plan['price'] ?? 'Free';
        if (is_array($price)) {
            $price = $price['formatted'] ?? 'Free';
        }

        // Handle limits.
        $limits = $plan['limits'] ?? [];

        $iscurrent = false;
        if ($subscription && isset($subscription['plan_name'])) {
            $iscurrent = (strtolower($plan['name']) === strtolower($subscription['plan_name']));
        }

        // Determine if this is an upgrade or downgrade.
        $planprice = 0;
        if (is_array($plan['price'])) {
            $planprice = ($plan['price']['cents'] ?? 0) / 100;
        } else {
            $planprice = floatval(str_replace(['$', '£', ',', '/mo'], '', $plan['price']));
        }
        $isupgrade = $planprice > $currentplanprice;
        $isdowngrade = $planprice < $currentplanprice;

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
            'is_current' => $iscurrent,
            'is_upgrade' => $isupgrade,
            'is_downgrade' => $isdowngrade,
            'features' => $plan['features'] ?? [],
            'stripe_product_id' => $plan['stripe_product_id'] ?? null,
        ];
    }
    $templatecontext['plans'] = $plans;
} else {
    $templatecontext['plans'] = [];
}

// Add usage statistics - this will be used by the analytics cards.
$usagestats = $installationmanager->get_usage_stats();
if ($usagestats) {
    $templatecontext['usage'] = $usagestats;
} else {
    $templatecontext['usage'] = [
        'ai_credits_used_this_month' => 0,
        'reports_generated_this_month' => 0,
        'reports_generated_total' => 0,
        'current_period_start' => null,
        'current_period_end' => null,
    ];
}

// Render the template.
echo $OUTPUT->render_from_template('report_adeptus_insights/subscription', $templatecontext);

echo $OUTPUT->footer();

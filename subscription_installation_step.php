<?php
// This file is part of Moodle - https://moodle.org/.
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Adeptus Insights - Subscription Installation Step.
 *
 * This page handles the subscription setup during plugin installation.
 * It automatically creates a free subscription and shows upgrade options.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');


require_once($CFG->libdir . '/adminlib.php');

// Require login and capability.
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Early redirects BEFORE page setup to avoid session mutation issues.
$installationmanager = new \report_adeptus_insights\installation_manager();

if (!$installationmanager->is_registered()) {
    redirect(new moodle_url('/report/adeptus_insights/register_plugin.php'));
}

$installationcompleted = get_config('report_adeptus_insights', 'installation_completed');
if ($installationcompleted) {
    redirect(new moodle_url('/report/adeptus_insights/index.php'));
}

// Set up page (only reached if no redirects needed).
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/subscription_installation_step.php'));
$PAGE->set_title(get_string('pluginname', 'report_adeptus_insights') . ' - Subscription Setup');
$PAGE->set_heading(get_string('pluginname', 'report_adeptus_insights') . ' - Subscription Setup');

// Set current installation step.
set_config('installation_step', '2', 'report_adeptus_insights');

// Handle form submissions.
$action = optional_param('action', '', PARAM_ALPHA);
$planid = optional_param('plan_id', 0, PARAM_INT);

if ($action === 'completeinstallation' && confirm_sesskey()) {
    global $USER;
    $activationresult = $installationmanager->setup_starter_subscription($USER->email, fullname($USER));

    if (!$activationresult) {
        $activationresult = $installationmanager->activate_free_plan_manually();
    }

    set_config('installation_completed', '1', 'report_adeptus_insights');
    set_config('installation_step', '3', 'report_adeptus_insights');

    redirect(
        new moodle_url('/report/adeptus_insights/index.php'),
        get_string('installation_complete', 'report_adeptus_insights'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'upgrade_plan' && confirm_sesskey() && $planid) {
    // Redirect to Stripe billing portal for upgrade.
    $result = $installationmanager->create_billing_portal_session();

    if ($result['success']) {
        redirect($result['data']['url']);
    } else {
        redirect(
            new moodle_url('/report/adeptus_insights/subscription_installation_step.php'),
            $result['message'],
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

$currentsubscription = $installationmanager->get_subscription_details();

if (!$currentsubscription) {
    $backendsyncresult = $installationmanager->check_subscription_status();
    if ($backendsyncresult) {
        $currentsubscription = $installationmanager->get_subscription_details();
    }
}

if ($currentsubscription && !empty($currentsubscription['plan_name'])) {
    set_config('installation_completed', '1', 'report_adeptus_insights');
    set_config('installation_step', '3', 'report_adeptus_insights');

    redirect(
        new moodle_url('/report/adeptus_insights/index.php'),
        get_string('installation_complete', 'report_adeptus_insights'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$availableplans = $installationmanager->get_available_plans();

$monthlyplans = [];
$yearlyplans = [];
$lifetimeplans = [];
$hasyearlyplans = false;
$haslifetimeplans = false;

if (!empty($availableplans['plans'])) {
    foreach ($availableplans['plans'] as $plan) {
        // Filter to only show Insights plans.
        $productkey = $plan['product_key'] ?? '';
        if ($productkey !== 'insights') {
            continue;
        }

        $billinginterval = $plan['billing_interval'] ?? 'monthly';
        $tier = $plan['tier'] ?? 'free';

        // Handle price - can be object or string.
        $price = $plan['price'] ?? ['cents' => 0, 'formatted' => 'Free'];
        $priceformatted = 'Free';
        $pricecents = 0;
        if (is_array($price)) {
            $priceformatted = $price['formatted'] ?? 'Free';
            $pricecents = $price['cents'] ?? 0;
        } else {
            $priceformatted = $price;
        }

        // Handle limits.
        $limits = $plan['limits'] ?? [];
        $limitfeatures = $limits['features'] ?? [];

        // Format limit values (handle -1 as unlimited).
        $formatlimit = function ($value, $suffix = '') {
            if ($value === -1 || $value === null) {
                return 'Unlimited';
            }
            return number_format($value) . $suffix;
        };

        // Determine current plan.
        $iscurrent = false;
        if ($currentsubscription && isset($currentsubscription['plan_name'])) {
            $iscurrent = (strtolower($plan['name']) === strtolower($currentsubscription['plan_name']));
        }

        // Build transformed plan.
        $transformedplan = [
            'id' => $plan['id'] ?? 0,
            'tier' => $tier,
            'name' => $plan['name'] ?? 'Unknown',
            'short_name' => ucfirst($tier), // Free, Pro, Enterprise.
            'description' => $plan['description'] ?? '',
            'price_formatted' => $priceformatted,
            // Clean price: just the number without currency symbol or interval suffix.
            // Used by lifetime cards so we don't show "$499.00 (lifetime)" or double the $.
            'price_clean' => $pricecents > 0 ? number_format($pricecents / 100, 0) : '0',
            'price_cents' => $pricecents,
            'billing_interval' => $billinginterval,
            'is_free' => $tier === 'free',
            'is_pro' => $tier === 'pro',
            'is_enterprise' => $tier === 'enterprise',
            'is_current' => $iscurrent,
            'is_popular' => $plan['is_popular'] ?? ($tier === 'pro'),

            // Formatted limits for display.
            'tokens_limit' => $formatlimit($limits['tokens_per_month'] ?? 50000),
            'tokens_raw' => $limits['tokens_per_month'] ?? 50000,
            'exports_limit' => $formatlimit($limits['exports_per_month'] ?? $limits['exports'] ?? 3),
            'exports_raw' => $limits['exports_per_month'] ?? $limits['exports'] ?? 3,
            'reports_limit' => $formatlimit($limits['reports_total_limit'] ?? 10),
            'reports_raw' => $limits['reports_total_limit'] ?? 10,
            'export_formats' => implode(', ', array_map('strtoupper', $limits['export_formats'] ?? ['pdf'])),

            // Feature flags for conditional display.
            'has_ai_assistant' => $limitfeatures['ai_assistant'] ?? true,
            'has_scheduled_reports' => $limitfeatures['scheduled_reports'] ?? false,
            'has_bulk_export' => $limitfeatures['bulk_export'] ?? false,
            'has_api_access' => $limitfeatures['api_access'] ?? false,
            'has_custom_reports' => $limitfeatures['custom_reports'] ?? false,

            // Human-readable features list from API.
            'features' => $plan['features'] ?? [],

            // Stripe integration.
            'stripe_product_id' => $plan['stripe_product_id'] ?? null,
            'stripe_configured' => $plan['stripe_configured'] ?? false,
        ];

        // Organize by billing interval.
        $islifetime = ($billinginterval === 'lifetime' || $billinginterval === 'one_time');
        // Also catch lifetime plans that fell through with wrong interval.
        if (!$islifetime && stripos($priceformatted, 'lifetime') !== false) {
            $islifetime = true;
        }

        if ($islifetime) {
            // For lifetime plans, only Pro should be marked as popular (not Enterprise).
            if ($tier !== 'pro') {
                $transformedplan['is_popular'] = false;
            }
            $lifetimeplans[$tier] = $transformedplan;
            $haslifetimeplans = true;
        } else if ($billinginterval === 'yearly' || $billinginterval === 'annual') {
            $yearlyplans[$tier] = $transformedplan;
            $hasyearlyplans = true;
        } else {
            $monthlyplans[$tier] = $transformedplan;
        }
    }
}

// Sort plans by tier order: free, pro, enterprise.
$tierorder = ['free' => 0, 'pro' => 1, 'enterprise' => 2];
$sortbytier = function ($a, $b) use ($tierorder) {
    return ($tierorder[$a['tier']] ?? 99) - ($tierorder[$b['tier']] ?? 99);
};

usort($monthlyplans, $sortbytier);
usort($yearlyplans, $sortbytier);
usort($lifetimeplans, $sortbytier);

// Calculate annual savings if yearly plans exist.
$maxyearlysavings = 0;
if ($hasyearlyplans) {
    foreach ($yearlyplans as &$yearlyplan) {
        $tier = $yearlyplan['tier'];
        if (isset($monthlyplans[$tier])) {
            $monthlyannualcost = $monthlyplans[$tier]['price_cents'] * 12;
            $yearlycost = $yearlyplan['price_cents'];
            if ($monthlyannualcost > 0 && $yearlycost < $monthlyannualcost) {
                $savingspercent = round((($monthlyannualcost - $yearlycost) / $monthlyannualcost) * 100);
                $yearlyplan['savings_percent'] = $savingspercent;
                $yearlyplan['has_savings'] = $savingspercent > 0;
                // Track maximum savings for toggle badge.
                if ($savingspercent > $maxyearlysavings) {
                    $maxyearlysavings = $savingspercent;
                }
            }
        }
    }
    unset($yearlyplan);
}

// Prepare template context.
$templatecontext = [
    'user_fullname' => fullname($USER),
    'user_email' => $USER->email,
    'is_registered' => $installationmanager->is_registered(),
    'sesskey' => sesskey(),
    'current_subscription' => $currentsubscription,
    'monthly_plans' => array_values($monthlyplans),
    'yearly_plans' => array_values($yearlyplans),
    'lifetime_plans' => array_values($lifetimeplans),
    'has_yearly_plans' => $hasyearlyplans,
    'has_lifetime_plans' => $haslifetimeplans,
    'max_yearly_savings' => $maxyearlysavings,
    'installation_step' => get_config('report_adeptus_insights', 'installation_step', '2'),
];

// Prepare plans data for JavaScript module.
$plansdata = [
    'monthly' => array_values($monthlyplans),
    'yearly' => array_values($yearlyplans),
    'lifetime' => array_values($lifetimeplans),
];

// Load required CSS and AMD module.
$PAGE->requires->css('/report/adeptus_insights/styles/installation.css');
$PAGE->requires->js_call_amd('report_adeptus_insights/installation_step', 'init', [[
    'plansData' => $plansdata,
]]);

// Output the page.
echo $OUTPUT->header();

// Render the subscription installation template.
echo $OUTPUT->render_from_template('report_adeptus_insights/subscription_installation_step', $templatecontext);

echo $OUTPUT->footer();

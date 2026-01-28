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

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use context_system;

/**
 * External service to get available subscription plans.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_available_plans extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get available subscription plans.
     *
     * @return array Available plans
     */
    public static function execute(): array {
        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            $installationmanager = new \report_adeptus_insights\installation_manager();

            // Get available plans from backend.
            $availableplans = $installationmanager->get_available_plans();

            if (!$availableplans || !isset($availableplans['success']) || !$availableplans['success']) {
                return [
                    'success' => false,
                    'error' => true,
                    'message' => $availableplans['message'] ?? get_string('error_fetch_plans_failed', 'report_adeptus_insights'),
                    'monthly_plans' => [],
                    'yearly_plans' => [],
                    'has_yearly_plans' => false,
                    'max_yearly_savings' => 0,
                    'current_plan' => '',
                ];
            }

            // Get current subscription to mark current plan.
            $currentsubscription = $installationmanager->get_subscription_details();
            $currentplanname = $currentsubscription['plan_name'] ?? '';

            // Transform and organize plans by tier and billing interval.
            // Only include plans for Adeptus Insights (product_key = 'insights').
            $monthlyplans = [];
            $yearlyplans = [];
            $hasyearlyplans = false;

            if (!empty($availableplans['plans'])) {
                foreach ($availableplans['plans'] as $plan) {
                    // Filter to ONLY show Insights plans (strict match).
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
                            return get_string('unlimited', 'report_adeptus_insights');
                        }
                        return number_format($value) . $suffix;
                    };

                    // Determine current plan.
                    $iscurrent = false;
                    if ($currentsubscription && isset($currentsubscription['plan_name'])) {
                        $iscurrent = (strtolower($plan['name'] ?? '') === strtolower($currentsubscription['plan_name']));
                    }

                    // Build transformed plan.
                    $transformedplan = [
                        'id' => (int) ($plan['id'] ?? 0),
                        'tier' => $tier,
                        'name' => $plan['name'] ?? 'Unknown',
                        'short_name' => ucfirst($tier),
                        'description' => $plan['description'] ?? '',
                        'price_formatted' => $priceformatted,
                        'price_cents' => (int) $pricecents,
                        'billing_interval' => $billinginterval,
                        'is_free' => $tier === 'free',
                        'is_pro' => $tier === 'pro',
                        'is_enterprise' => $tier === 'enterprise',
                        'is_current' => $iscurrent,
                        'is_popular' => $plan['is_popular'] ?? ($tier === 'pro'),
                        'tokens_limit' => $formatlimit($limits['tokens_per_month'] ?? 50000),
                        'tokens_raw' => (int) ($limits['tokens_per_month'] ?? 50000),
                        'exports_limit' => $formatlimit($limits['exports_per_month'] ?? $limits['exports'] ?? 3),
                        'exports_raw' => (int) ($limits['exports_per_month'] ?? $limits['exports'] ?? 3),
                        'reports_limit' => $formatlimit($limits['reports_total_limit'] ?? 10),
                        'reports_raw' => (int) ($limits['reports_total_limit'] ?? 10),
                        'export_formats' => implode(', ', array_map('strtoupper', $limits['export_formats'] ?? ['pdf'])),
                        'has_ai_assistant' => $limitfeatures['ai_assistant'] ?? true,
                        'has_scheduled_reports' => $limitfeatures['scheduled_reports'] ?? false,
                        'has_bulk_export' => $limitfeatures['bulk_export'] ?? false,
                        'has_api_access' => $limitfeatures['api_access'] ?? false,
                        'has_custom_reports' => $limitfeatures['custom_reports'] ?? false,
                        'features' => $plan['features'] ?? [],
                        'stripe_product_id' => $plan['stripe_product_id'] ?? '',
                        'stripe_price_id' => $plan['stripe_price_id'] ?? '',
                        'stripe_configured' => $plan['stripe_configured'] ?? false,
                        'savings_percent' => 0,
                        'has_savings' => false,
                    ];

                    // Organize by billing interval.
                    if ($billinginterval === 'yearly' || $billinginterval === 'annual') {
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

            // Calculate annual savings if yearly plans exist.
            $maxyearlysavings = 0;
            if ($hasyearlyplans) {
                // Rebuild monthly_plans as associative for lookup.
                $monthlybytier = [];
                foreach ($monthlyplans as $plan) {
                    $monthlybytier[$plan['tier']] = $plan;
                }

                foreach ($yearlyplans as &$yearlyplan) {
                    $tier = $yearlyplan['tier'];
                    if (isset($monthlybytier[$tier])) {
                        $monthlyannualcost = $monthlybytier[$tier]['price_cents'] * 12;
                        $yearlycost = $yearlyplan['price_cents'];
                        if ($monthlyannualcost > 0 && $yearlycost < $monthlyannualcost) {
                            $savingspercent = round((($monthlyannualcost - $yearlycost) / $monthlyannualcost) * 100);
                            $yearlyplan['savings_percent'] = (int) $savingspercent;
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

            return [
                'success' => true,
                'error' => false,
                'message' => '',
                'monthly_plans' => array_values($monthlyplans),
                'yearly_plans' => array_values($yearlyplans),
                'has_yearly_plans' => $hasyearlyplans,
                'max_yearly_savings' => (int) $maxyearlysavings,
                'current_plan' => $currentplanname,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => true,
                'message' => get_string('error_occurred', 'report_adeptus_insights', $e->getMessage()),
                'monthly_plans' => [],
                'yearly_plans' => [],
                'has_yearly_plans' => false,
                'max_yearly_savings' => 0,
                'current_plan' => '',
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $planstructure = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Plan ID'),
            'tier' => new external_value(PARAM_TEXT, 'Plan tier'),
            'name' => new external_value(PARAM_TEXT, 'Plan name'),
            'short_name' => new external_value(PARAM_TEXT, 'Short name'),
            'description' => new external_value(PARAM_TEXT, 'Plan description'),
            'price_formatted' => new external_value(PARAM_TEXT, 'Formatted price'),
            'price_cents' => new external_value(PARAM_INT, 'Price in cents'),
            'billing_interval' => new external_value(PARAM_TEXT, 'Billing interval'),
            'is_free' => new external_value(PARAM_BOOL, 'Is free plan'),
            'is_pro' => new external_value(PARAM_BOOL, 'Is pro plan'),
            'is_enterprise' => new external_value(PARAM_BOOL, 'Is enterprise plan'),
            'is_current' => new external_value(PARAM_BOOL, 'Is current plan'),
            'is_popular' => new external_value(PARAM_BOOL, 'Is popular plan'),
            'tokens_limit' => new external_value(PARAM_TEXT, 'Tokens limit formatted'),
            'tokens_raw' => new external_value(PARAM_INT, 'Tokens limit raw'),
            'exports_limit' => new external_value(PARAM_TEXT, 'Exports limit formatted'),
            'exports_raw' => new external_value(PARAM_INT, 'Exports limit raw'),
            'reports_limit' => new external_value(PARAM_TEXT, 'Reports limit formatted'),
            'reports_raw' => new external_value(PARAM_INT, 'Reports limit raw'),
            'export_formats' => new external_value(PARAM_TEXT, 'Export formats'),
            'has_ai_assistant' => new external_value(PARAM_BOOL, 'Has AI assistant'),
            'has_scheduled_reports' => new external_value(PARAM_BOOL, 'Has scheduled reports'),
            'has_bulk_export' => new external_value(PARAM_BOOL, 'Has bulk export'),
            'has_api_access' => new external_value(PARAM_BOOL, 'Has API access'),
            'has_custom_reports' => new external_value(PARAM_BOOL, 'Has custom reports'),
            'features' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Feature'),
                'List of features'
            ),
            'stripe_product_id' => new external_value(PARAM_TEXT, 'Stripe product ID'),
            'stripe_price_id' => new external_value(PARAM_TEXT, 'Stripe price ID'),
            'stripe_configured' => new external_value(PARAM_BOOL, 'Stripe configured'),
            'savings_percent' => new external_value(PARAM_INT, 'Savings percentage'),
            'has_savings' => new external_value(PARAM_BOOL, 'Has savings'),
        ]);

        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'error' => new external_value(PARAM_BOOL, 'Whether an error occurred'),
            'message' => new external_value(PARAM_TEXT, 'Error message if any'),
            'monthly_plans' => new external_multiple_structure($planstructure, 'Monthly plans'),
            'yearly_plans' => new external_multiple_structure($planstructure, 'Yearly plans'),
            'has_yearly_plans' => new external_value(PARAM_BOOL, 'Whether yearly plans exist'),
            'max_yearly_savings' => new external_value(PARAM_INT, 'Maximum yearly savings percentage'),
            'current_plan' => new external_value(PARAM_TEXT, 'Current plan name'),
        ]);
    }
}

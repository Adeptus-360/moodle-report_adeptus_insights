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
 * Get available subscription plans AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/installation_manager.php');

// Set headers early
@header('Content-Type: application/json; charset=utf-8');

// Disable debugging output that could break JSON
$CFG->debug = 0;
$CFG->debugdisplay = 0;
error_reporting(0);

try {
    require_login();
    require_capability('report/adeptus_insights:view', context_system::instance());

    // Validate sesskey
    if (!confirm_sesskey(optional_param('sesskey', '', PARAM_ALPHANUM))) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid session key',
        ]);
        exit;
    }

    $installationmanager = new \report_adeptus_insights\installation_manager();

    // Get available plans from backend
    $availableplans = $installationmanager->get_available_plans();

    // Debug: Log raw API response

    if (!$availableplans || !isset($availableplans['success']) || !$availableplans['success']) {
        echo json_encode([
            'success' => false,
            'message' => $availableplans['message'] ?? 'Failed to fetch plans',
        ]);
        exit;
    }

    // Get current subscription to mark current plan
    $currentsubscription = $installationmanager->get_subscription_details();
    $currentplanname = $currentsubscription['plan_name'] ?? '';

    // Transform and organize plans by tier and billing interval
    // Only include plans for Adeptus Insights (product_key = 'insights')
    $monthlyplans = [];
    $yearlyplans = [];
    $hasyearlyplans = false;

    if (!empty($availableplans['plans'])) {
        foreach ($availableplans['plans'] as $plan) {
            // Filter to ONLY show Insights plans (strict match)
            $productkey = $plan['product_key'] ?? '';
            if ($productkey !== 'insights') {
                continue;
            }

            $billinginterval = $plan['billing_interval'] ?? 'monthly';
            $tier = $plan['tier'] ?? 'free';

            // Handle price - can be object or string
            $price = $plan['price'] ?? ['cents' => 0, 'formatted' => 'Free'];
            $priceformatted = 'Free';
            $pricecents = 0;
            if (is_array($price)) {
                $priceformatted = $price['formatted'] ?? 'Free';
                $pricecents = $price['cents'] ?? 0;
            } else {
                $priceformatted = $price;
            }

            // Handle limits
            $limits = $plan['limits'] ?? [];
            $limitfeatures = $limits['features'] ?? [];

            // Format limit values (handle -1 as unlimited)
            $formatlimit = function ($value, $suffix = '') {
                if ($value === -1 || $value === null) {
                    return 'Unlimited';
                }
                return number_format($value) . $suffix;
            };

            // Determine current plan
            $iscurrent = false;
            if ($currentsubscription && isset($currentsubscription['plan_name'])) {
                $iscurrent = (strtolower($plan['name'] ?? '') === strtolower($currentsubscription['plan_name']));
            }

            // Build transformed plan
            $transformedplan = [
                'id' => $plan['id'] ?? 0,
                'tier' => $tier,
                'name' => $plan['name'] ?? 'Unknown',
                'short_name' => ucfirst($tier), // Free, Pro, Enterprise
                'description' => $plan['description'] ?? '',
                'price_formatted' => $priceformatted,
                'price_cents' => $pricecents,
                'billing_interval' => $billinginterval,
                'is_free' => $tier === 'free',
                'is_pro' => $tier === 'pro',
                'is_enterprise' => $tier === 'enterprise',
                'is_current' => $iscurrent,
                'is_popular' => $plan['is_popular'] ?? ($tier === 'pro'),

                // Formatted limits for display
                'tokens_limit' => $formatlimit($limits['tokens_per_month'] ?? 50000),
                'tokens_raw' => $limits['tokens_per_month'] ?? 50000,
                'exports_limit' => $formatlimit($limits['exports_per_month'] ?? $limits['exports'] ?? 3),
                'exports_raw' => $limits['exports_per_month'] ?? $limits['exports'] ?? 3,
                'reports_limit' => $formatlimit($limits['reports_total_limit'] ?? 10),
                'reports_raw' => $limits['reports_total_limit'] ?? 10,
                'export_formats' => implode(', ', array_map('strtoupper', $limits['export_formats'] ?? ['pdf'])),

                // Feature flags for conditional display
                'has_ai_assistant' => $limitfeatures['ai_assistant'] ?? true,
                'has_scheduled_reports' => $limitfeatures['scheduled_reports'] ?? false,
                'has_bulk_export' => $limitfeatures['bulk_export'] ?? false,
                'has_api_access' => $limitfeatures['api_access'] ?? false,
                'has_custom_reports' => $limitfeatures['custom_reports'] ?? false,

                // Human-readable features list from API
                'features' => $plan['features'] ?? [],

                // Stripe integration
                'stripe_product_id' => $plan['stripe_product_id'] ?? null,
                'stripe_price_id' => $plan['stripe_price_id'] ?? null,
                'stripe_configured' => $plan['stripe_configured'] ?? false,
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

    // Sort plans by tier order: free, pro, enterprise
    $tierorder = ['free' => 0, 'pro' => 1, 'enterprise' => 2];
    $sortbytier = function ($a, $b) use ($tierorder) {
        return ($tierorder[$a['tier']] ?? 99) - ($tierorder[$b['tier']] ?? 99);
    };

    usort($monthlyplans, $sortbytier);
    usort($yearlyplans, $sortbytier);

    // Calculate annual savings if yearly plans exist
    $maxyearlysavings = 0;
    if ($hasyearlyplans) {
        // Rebuild monthly_plans as associative for lookup
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
                    $yearlyplan['savings_percent'] = $savingspercent;
                    $yearlyplan['has_savings'] = $savingspercent > 0;
                    // Track maximum savings for toggle badge
                    if ($savingspercent > $maxyearlysavings) {
                        $maxyearlysavings = $savingspercent;
                    }
                }
            }
        }
        unset($yearlyplan);
    }

    echo json_encode([
        'success' => true,
        'monthly_plans' => array_values($monthlyplans),
        'yearly_plans' => array_values($yearlyplans),
        'has_yearly_plans' => $hasyearlyplans,
        'max_yearly_savings' => $maxyearlysavings,
        'current_plan' => $currentplanname,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// AJAX endpoint to get available subscription plans
// Returns plans in same format as subscription_installation_step.php for modal display

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

    // Validate sesskey
    if (!confirm_sesskey(optional_param('sesskey', '', PARAM_ALPHANUM))) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid session key'
        ]);
        exit;
    }

    $installation_manager = new \report_adeptus_insights\installation_manager();

    // Get available plans from backend
    $available_plans = $installation_manager->get_available_plans();

    // Debug: Log raw API response
    error_log('[get_available_plans] Raw API response: ' . json_encode($available_plans));

    if (!$available_plans || !isset($available_plans['success']) || !$available_plans['success']) {
        echo json_encode([
            'success' => false,
            'message' => $available_plans['message'] ?? 'Failed to fetch plans'
        ]);
        exit;
    }

    // Get current subscription to mark current plan
    $current_subscription = $installation_manager->get_subscription_details();
    $current_plan_name = $current_subscription['plan_name'] ?? '';

    // Transform and organize plans by tier and billing interval
    // Only include plans for Adeptus Insights (product_key = 'insights')
    $monthly_plans = [];
    $yearly_plans = [];
    $has_yearly_plans = false;

    if (!empty($available_plans['plans'])) {
        foreach ($available_plans['plans'] as $plan) {
            // Filter to ONLY show Insights plans (strict match)
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
                $is_current = (strtolower($plan['name'] ?? '') === strtolower($current_subscription['plan_name']));
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

            // Debug: Log stripe_product_id for each plan
            error_log('[get_available_plans] Plan: ' . ($plan['name'] ?? 'unknown') .
                      ', tier: ' . $tier .
                      ', stripe_product_id: ' . ($plan['stripe_product_id'] ?? 'NULL'));

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
    $max_yearly_savings = 0;
    if ($has_yearly_plans) {
        // Rebuild monthly_plans as associative for lookup
        $monthly_by_tier = [];
        foreach ($monthly_plans as $plan) {
            $monthly_by_tier[$plan['tier']] = $plan;
        }

        foreach ($yearly_plans as &$yearly_plan) {
            $tier = $yearly_plan['tier'];
            if (isset($monthly_by_tier[$tier])) {
                $monthly_annual_cost = $monthly_by_tier[$tier]['price_cents'] * 12;
                $yearly_cost = $yearly_plan['price_cents'];
                if ($monthly_annual_cost > 0 && $yearly_cost < $monthly_annual_cost) {
                    $savings_percent = round((($monthly_annual_cost - $yearly_cost) / $monthly_annual_cost) * 100);
                    $yearly_plan['savings_percent'] = $savings_percent;
                    $yearly_plan['has_savings'] = $savings_percent > 0;
                    // Track maximum savings for toggle badge
                    if ($savings_percent > $max_yearly_savings) {
                        $max_yearly_savings = $savings_percent;
                    }
                }
            }
        }
        unset($yearly_plan);
    }

    echo json_encode([
        'success' => true,
        'monthly_plans' => array_values($monthly_plans),
        'yearly_plans' => array_values($yearly_plans),
        'has_yearly_plans' => $has_yearly_plans,
        'max_yearly_savings' => $max_yearly_savings,
        'current_plan' => $current_plan_name
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

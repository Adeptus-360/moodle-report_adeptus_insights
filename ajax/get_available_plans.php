<?php
// This file is part of Moodle - http://moodle.org/
//
// AJAX endpoint to get available subscription plans

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');

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
    $plans_response = $installation_manager->get_available_plans();

    if (!$plans_response || !isset($plans_response['success']) || !$plans_response['success']) {
        echo json_encode([
            'success' => false,
            'message' => $plans_response['message'] ?? 'Failed to fetch plans'
        ]);
        exit;
    }

    // Get current subscription to mark current plan
    $subscription = $installation_manager->get_subscription_details();
    $current_plan_name = $subscription['plan_name'] ?? '';

    // Process plans for display
    $processed_plans = [];
    $plans = $plans_response['plans'] ?? [];

    foreach ($plans as $plan) {
        // Only include Insights plans
        $product_key = $plan['product_key'] ?? '';
        if ($product_key !== 'insights' && $product_key !== '') {
            continue;
        }

        $is_free = (isset($plan['price']) && $plan['price'] === 'Free') ||
                   (isset($plan['price']['cents']) && $plan['price']['cents'] == 0);

        $is_current = strtolower($plan['name'] ?? '') === strtolower($current_plan_name);

        // Format price
        $price_formatted = 'Free';
        if (!$is_free && isset($plan['price'])) {
            if (is_array($plan['price'])) {
                $cents = $plan['price']['cents'] ?? 0;
                $currency = $plan['price']['currency'] ?? 'USD';
                $price_formatted = '$' . number_format($cents / 100, 2);
            } else {
                $price_formatted = $plan['price'];
            }
        }

        // Get limits
        $limits = $plan['limits'] ?? [];

        $processed_plans[] = [
            'id' => $plan['id'] ?? 0,
            'name' => $plan['name'] ?? 'Unknown',
            'short_name' => $plan['short_name'] ?? $plan['name'] ?? 'Unknown',
            'description' => $plan['description'] ?? '',
            'price_formatted' => $price_formatted,
            'is_free' => $is_free,
            'is_current' => $is_current,
            'is_popular' => ($plan['tier'] ?? 0) == 2, // Professional tier
            'stripe_product_id' => $plan['stripe_product_id'] ?? '',
            'tokens_limit' => $limits['ai_tokens'] ?? $limits['tokens'] ?? 'N/A',
            'exports_limit' => $limits['exports'] ?? 'N/A',
            'reports_limit' => $limits['saved_reports'] ?? 'N/A',
            'features' => $plan['features'] ?? []
        ];
    }

    echo json_encode([
        'success' => true,
        'plans' => $processed_plans,
        'current_plan' => $current_plan_name
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

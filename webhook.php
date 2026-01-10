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
 * Stripe Webhook Handler for Adeptus Insights.
 *
 * Processes Stripe webhook events for subscription management.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Verify this is a webhook request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Get the webhook payload
$payload = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($sig_header)) {
    http_response_code(400);
    exit('No signature header');
}

try {
    // Load the Stripe service
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/stripe_service.php');
    $stripe_service = new \report_adeptus_insights\stripe_service();

    // Verify the webhook signature
    $event = $stripe_service->verify_webhook($payload, $sig_header);

    // Process the event
    $result = process_webhook_event($event);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
} catch (\Exception $e) {
    debugging('Webhook error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Process Stripe webhook events
 */
function process_webhook_event($event) {
    global $DB;

    try {
        // Log the event
        $event_record = [
            'stripe_event_id' => $event->id,
            'event_type' => $event->type,
            'stripe_customer_id' => $event->data->object->customer ?? null,
            'stripe_subscription_id' => $event->data->object->id ?? null,
            'event_data' => json_encode($event->data->object),
            'processed' => 0,
            'timecreated' => time(),
        ];

        $DB->insert_record('adeptus_stripe_webhooks', $event_record);

        // Process based on event type
        switch ($event->type) {
            case 'customer.subscription.created':
                return handle_subscription_created($event);

            case 'customer.subscription.updated':
                return handle_subscription_updated($event);

            case 'customer.subscription.deleted':
                return handle_subscription_deleted($event);

            case 'invoice.payment_succeeded':
                return handle_payment_succeeded($event);

            case 'invoice.payment_failed':
                return handle_payment_failed($event);

            default:
                // Mark as processed for unhandled events
                $DB->set_field('adeptus_stripe_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);
                return ['success' => true];
        }
    } catch (\Exception $e) {
        debugging('Failed to process webhook event: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle subscription created event
 */
function handle_subscription_created($event) {
    global $DB;

    $subscription = $event->data->object;
    $customer_id = $subscription->customer;

    // Get plan details from Stripe
    $price_id = $subscription->items->data[0]->price->id;
    $plan = $DB->get_record('adeptus_stripe_plans', ['stripe_price_id' => $price_id]);

    if (!$plan) {
        debugging('Plan not found for price ID: ' . $price_id);
        return ['success' => false, 'error' => 'Plan not found'];
    }

    // Update subscription status
    $subscription_data = [
        'stripe_customer_id' => $customer_id,
        'stripe_subscription_id' => $subscription->id,
        'plan_name' => $plan->name,
        'plan_id' => $plan->stripe_product_id,
        'status' => $subscription->status,
        'current_period_start' => $subscription->current_period_start,
        'current_period_end' => $subscription->current_period_end,
        'ai_credits_remaining' => $plan->ai_credits,
        'ai_credits_pro_remaining' => $plan->ai_credits_pro,
        'ai_credits_basic_remaining' => $plan->ai_credits_basic,
        'exports_remaining' => $plan->exports,
        'billing_email' => null, // Will be updated from customer data
    ];

    // Get customer email
    try {
        $stripe_service = new \report_adeptus_insights\stripe_service();
        $customer = $stripe_service->stripe->customers->retrieve($customer_id);
        $subscription_data['billing_email'] = $customer->email;
    } catch (\Exception $e) {
        debugging('Failed to get customer email: ' . $e->getMessage());
    }

    // Update local subscription status
    $existing = $DB->get_record('adeptus_subscription_status', ['id' => 1]);
    if ($existing) {
        $subscription_data['id'] = 1;
        $DB->update_record('adeptus_subscription_status', $subscription_data);
    } else {
        $subscription_data['id'] = 1;
        $DB->insert_record('adeptus_subscription_status', $subscription_data);
    }

    // Mark event as processed
    $DB->set_field('adeptus_stripe_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);

    return ['success' => true];
}

/**
 * Handle subscription updated event
 */
function handle_subscription_updated($event) {
    global $DB;

    $subscription = $event->data->object;
    $customer_id = $subscription->customer;

    // Get plan details from Stripe
    $price_id = $subscription->items->data[0]->price->id;
    $plan = $DB->get_record('adeptus_stripe_plans', ['stripe_price_id' => $price_id]);

    if (!$plan) {
        debugging('Plan not found for price ID: ' . $price_id);
        return ['success' => false, 'error' => 'Plan not found'];
    }

    // Update subscription status
    $subscription_data = [
        'stripe_customer_id' => $customer_id,
        'stripe_subscription_id' => $subscription->id,
        'plan_name' => $plan->name,
        'plan_id' => $plan->stripe_product_id,
        'status' => $subscription->status,
        'current_period_start' => $subscription->current_period_start,
        'current_period_end' => $subscription->current_period_end,
        'ai_credits_remaining' => $plan->ai_credits,
        'ai_credits_pro_remaining' => $plan->ai_credits_pro,
        'ai_credits_basic_remaining' => $plan->ai_credits_basic,
        'exports_remaining' => $plan->exports,
    ];

    // Preserve existing credits if subscription is active
    if ($subscription->status === 'active') {
        $existing = $DB->get_record('adeptus_subscription_status', ['id' => 1]);
        if ($existing) {
            $subscription_data['ai_credits_remaining'] = $existing->ai_credits_remaining;
            $subscription_data['ai_credits_pro_remaining'] = $existing->ai_credits_pro_remaining;
            $subscription_data['ai_credits_basic_remaining'] = $existing->ai_credits_basic_remaining;
            $subscription_data['exports_remaining'] = $existing->exports_remaining;
        }
    }

    // Update local subscription status
    $existing = $DB->get_record('adeptus_subscription_status', ['id' => 1]);
    if ($existing) {
        $subscription_data['id'] = 1;
        $DB->update_record('adeptus_subscription_status', $subscription_data);
    } else {
        $subscription_data['id'] = 1;
        $DB->insert_record('adeptus_subscription_status', $subscription_data);
    }

    // Mark event as processed
    $DB->set_field('adeptus_stripe_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);

    return ['success' => true];
}

/**
 * Handle subscription deleted event
 */
function handle_subscription_deleted($event) {
    global $DB;

    $subscription = $event->data->object;

    // Update subscription status to cancelled
    $subscription_data = [
        'status' => 'cancelled',
        'current_period_end' => $subscription->current_period_end,
    ];

    $existing = $DB->get_record('adeptus_subscription_status', ['id' => 1]);
    if ($existing) {
        $subscription_data['id'] = 1;
        $DB->update_record('adeptus_subscription_status', $subscription_data);
    }

    // Mark event as processed
    $DB->set_field('adeptus_stripe_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);

    return ['success' => true];
}

/**
 * Handle payment succeeded event
 */
function handle_payment_succeeded($event) {
    global $DB;

    $invoice = $event->data->object;
    $subscription_id = $invoice->subscription;

    if (!$subscription_id) {
        // One-time payment, not subscription
        return ['success' => true];
    }

    // Get subscription details
    $subscription = $DB->get_record('adeptus_subscription_status', ['stripe_subscription_id' => $subscription_id]);
    if (!$subscription) {
        debugging('Subscription not found for ID: ' . $subscription_id);
        return ['success' => false, 'error' => 'Subscription not found'];
    }

    // Get plan details
    $plan = $DB->get_record('adeptus_stripe_plans', ['stripe_product_id' => $subscription->plan_id]);
    if (!$plan) {
        debugging('Plan not found for subscription: ' . $subscription_id);
        return ['success' => false, 'error' => 'Plan not found'];
    }

    // Reset credits for new billing period
    $subscription_data = [
        'status' => 'active',
        'ai_credits_remaining' => $plan->ai_credits,
        'ai_credits_pro_remaining' => $plan->ai_credits_pro,
        'ai_credits_basic_remaining' => $plan->ai_credits_basic,
        'exports_remaining' => $plan->exports,
    ];

    $subscription_data['id'] = $subscription->id;
    $DB->update_record('adeptus_subscription_status', $subscription_data);

    // Mark event as processed
    $DB->set_field('adeptus_stripe_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);

    return ['success' => true];
}

/**
 * Handle payment failed event
 */
function handle_payment_failed($event) {
    global $DB;

    $invoice = $event->data->object;
    $subscription_id = $invoice->subscription;

    if (!$subscription_id) {
        // One-time payment, not subscription
        return ['success' => true];
    }

    // Update subscription status to past_due
    $subscription_data = [
        'status' => 'past_due',
    ];

    $existing = $DB->get_record('adeptus_subscription_status', ['stripe_subscription_id' => $subscription_id]);
    if ($existing) {
        $subscription_data['id'] = $existing->id;
        $DB->update_record('adeptus_subscription_status', $subscription_data);
    }

    // Mark event as processed
    $DB->set_field('adeptus_stripe_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);

    return ['success' => true];
}

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
 * This endpoint receives external webhooks from Stripe and uses
 * signature verification instead of Moodle session authentication.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Verify this is a webhook request.
$requestmethod = isset($_SERVER['REQUEST_METHOD']) ? clean_param($_SERVER['REQUEST_METHOD'], PARAM_ALPHA) : '';
if ($requestmethod !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Get the webhook payload.
$payload = file_get_contents('php://input');
// Get Stripe signature header and clean it (base64 chars, timestamps, commas, equals).
$sigheader = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
// Stripe signatures contain special characters (t=,v1=) so we validate format rather than cleaning.
if (!empty($sigheader) && !preg_match('/^t=\d+,v\d+=[a-f0-9]+/', $sigheader)) {
    http_response_code(400);
    exit('Invalid signature format');
}

if (empty($sigheader)) {
    http_response_code(400);
    exit('No signature header');
}

try {
    // Load the Stripe service (autoloaded).
    $stripeservice = new \report_adeptus_insights\stripe_service();

    // Verify the webhook signature
    $event = $stripeservice->verify_webhook($payload, $sigheader);

    // Process the event
    $result = report_adeptus_insights_process_webhook_event($event);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Process Stripe webhook events.
 *
 * @param object $event The Stripe event object.
 * @return array Result with success status.
 */
function report_adeptus_insights_process_webhook_event($event) {
    global $DB;

    try {
        // Log the event
        $eventrecord = [
            'stripe_event_id' => $event->id,
            'event_type' => $event->type,
            'stripe_customer_id' => $event->data->object->customer ?? null,
            'stripe_subscription_id' => $event->data->object->id ?? null,
            'event_data' => json_encode($event->data->object),
            'processed' => 0,
            'timecreated' => time(),
        ];

        $DB->insert_record('report_adeptus_insights_webhooks', $eventrecord);

        // Process based on event type
        switch ($event->type) {
            case 'customer.subscription.created':
                return report_adeptus_insights_handle_subscription_created($event);

            case 'customer.subscription.updated':
                return report_adeptus_insights_handle_subscription_updated($event);

            case 'customer.subscription.deleted':
                return report_adeptus_insights_handle_subscription_deleted($event);

            case 'invoice.payment_succeeded':
                return report_adeptus_insights_handle_payment_succeeded($event);

            case 'invoice.payment_failed':
                return report_adeptus_insights_handle_payment_failed($event);

            default:
                // Mark as processed for unhandled events
                $DB->set_field('report_adeptus_insights_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);
                return ['success' => true];
        }
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle subscription created event.
 *
 * @param object $event The Stripe event object.
 * @return array Result with success status.
 */
function report_adeptus_insights_handle_subscription_created($event) {
    global $DB;

    $subscription = $event->data->object;
    $customerid = $subscription->customer;

    // Get plan details from Stripe
    $priceid = $subscription->items->data[0]->price->id;
    $plan = $DB->get_record('report_adeptus_insights_plans', ['stripe_price_id' => $priceid]);

    if (!$plan) {
        return ['success' => false, 'error' => 'Plan not found'];
    }

    // Update subscription status
    $subscriptiondata = [
        'stripe_customer_id' => $customerid,
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
        $stripeservice = new \report_adeptus_insights\stripe_service();
        $customer = $stripeservice->stripe->customers->retrieve($customerid);
        $subscriptiondata['billing_email'] = $customer->email;
    } catch (\Exception $e) {
        // Ignore Stripe API errors - billing email is optional.
        debugging('Stripe customer retrieval failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    // Update local subscription status
    $existing = $DB->get_record('report_adeptus_insights_subscription', ['id' => 1]);
    if ($existing) {
        $subscriptiondata['id'] = 1;
        $DB->update_record('report_adeptus_insights_subscription', $subscriptiondata);
    } else {
        $subscriptiondata['id'] = 1;
        $DB->insert_record('report_adeptus_insights_subscription', $subscriptiondata);
    }

    // Mark event as processed
    $DB->set_field('report_adeptus_insights_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);

    return ['success' => true];
}

/**
 * Handle subscription updated event.
 *
 * @param object $event The Stripe event object.
 * @return array Result with success status.
 */
function report_adeptus_insights_handle_subscription_updated($event) {
    global $DB;

    $subscription = $event->data->object;
    $customerid = $subscription->customer;

    // Get plan details from Stripe
    $priceid = $subscription->items->data[0]->price->id;
    $plan = $DB->get_record('report_adeptus_insights_plans', ['stripe_price_id' => $priceid]);

    if (!$plan) {
        return ['success' => false, 'error' => 'Plan not found'];
    }

    // Update subscription status
    $subscriptiondata = [
        'stripe_customer_id' => $customerid,
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
        $existing = $DB->get_record('report_adeptus_insights_subscription', ['id' => 1]);
        if ($existing) {
            $subscriptiondata['ai_credits_remaining'] = $existing->ai_credits_remaining;
            $subscriptiondata['ai_credits_pro_remaining'] = $existing->ai_credits_pro_remaining;
            $subscriptiondata['ai_credits_basic_remaining'] = $existing->ai_credits_basic_remaining;
            $subscriptiondata['exports_remaining'] = $existing->exports_remaining;
        }
    }

    // Update local subscription status
    $existing = $DB->get_record('report_adeptus_insights_subscription', ['id' => 1]);
    if ($existing) {
        $subscriptiondata['id'] = 1;
        $DB->update_record('report_adeptus_insights_subscription', $subscriptiondata);
    } else {
        $subscriptiondata['id'] = 1;
        $DB->insert_record('report_adeptus_insights_subscription', $subscriptiondata);
    }

    // Mark event as processed
    $DB->set_field('report_adeptus_insights_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);

    return ['success' => true];
}

/**
 * Handle subscription deleted event.
 *
 * @param object $event The Stripe event object.
 * @return array Result with success status.
 */
function report_adeptus_insights_handle_subscription_deleted($event) {
    global $DB;

    $subscription = $event->data->object;

    // Update subscription status to cancelled
    $subscriptiondata = [
        'status' => 'cancelled',
        'current_period_end' => $subscription->current_period_end,
    ];

    $existing = $DB->get_record('report_adeptus_insights_subscription', ['id' => 1]);
    if ($existing) {
        $subscriptiondata['id'] = 1;
        $DB->update_record('report_adeptus_insights_subscription', $subscriptiondata);
    }

    // Mark event as processed
    $DB->set_field('report_adeptus_insights_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);

    return ['success' => true];
}

/**
 * Handle payment succeeded event.
 *
 * @param object $event The Stripe event object.
 * @return array Result with success status.
 */
function report_adeptus_insights_handle_payment_succeeded($event) {
    global $DB;

    $invoice = $event->data->object;
    $subscriptionid = $invoice->subscription;

    if (!$subscriptionid) {
        // One-time payment, not subscription
        return ['success' => true];
    }

    // Get subscription details
    $subscription = $DB->get_record('report_adeptus_insights_subscription', ['stripe_subscription_id' => $subscriptionid]);
    if (!$subscription) {
        return ['success' => false, 'error' => 'Subscription not found'];
    }

    // Get plan details
    $plan = $DB->get_record('report_adeptus_insights_plans', ['stripe_product_id' => $subscription->plan_id]);
    if (!$plan) {
        return ['success' => false, 'error' => 'Plan not found'];
    }

    // Reset credits for new billing period
    $subscriptiondata = [
        'status' => 'active',
        'ai_credits_remaining' => $plan->ai_credits,
        'ai_credits_pro_remaining' => $plan->ai_credits_pro,
        'ai_credits_basic_remaining' => $plan->ai_credits_basic,
        'exports_remaining' => $plan->exports,
    ];

    $subscriptiondata['id'] = $subscription->id;
    $DB->update_record('report_adeptus_insights_subscription', $subscriptiondata);

    // Mark event as processed
    $DB->set_field('report_adeptus_insights_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);

    return ['success' => true];
}

/**
 * Handle payment failed event.
 *
 * @param object $event The Stripe event object.
 * @return array Result with success status.
 */
function report_adeptus_insights_handle_payment_failed($event) {
    global $DB;

    $invoice = $event->data->object;
    $subscriptionid = $invoice->subscription;

    if (!$subscriptionid) {
        // One-time payment, not subscription
        return ['success' => true];
    }

    // Update subscription status to past_due
    $subscriptiondata = [
        'status' => 'past_due',
    ];

    $existing = $DB->get_record('report_adeptus_insights_subscription', ['stripe_subscription_id' => $subscriptionid]);
    if ($existing) {
        $subscriptiondata['id'] = $existing->id;
        $DB->update_record('report_adeptus_insights_subscription', $subscriptiondata);
    }

    // Mark event as processed
    $DB->set_field('report_adeptus_insights_webhooks', 'processed', 1, ['stripe_event_id' => $event->id]);

    return ['success' => true];
}

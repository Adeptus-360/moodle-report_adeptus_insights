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
 * Stripe Service Class for Adeptus Insights.
 *
 * Handles all Stripe API interactions and subscription management.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

class stripe_service {
    private $stripe;
    private $config;
    private $is_test_mode;

    public function __construct() {
        global $DB;

        // Load Stripe configuration
        $this->load_config();

        // Initialize Stripe SDK
        if (!class_exists('\Stripe\Stripe')) {
            // Try to autoload Stripe
            $stripe_path = $CFG->dirroot . '/report/adeptus_insights/vendor/stripe/stripe-php/init.php';
            if (file_exists($stripe_path)) {
                require_once($stripe_path);
            } else {
                // Fallback to Composer autoload
                $composer_autoload = $CFG->dirroot . '/report/adeptus_insights/vendor/autoload.php';
                if (file_exists($composer_autoload)) {
                    require_once($composer_autoload);
                }
            }
        }

        if (class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($this->config->secret_key);
            $this->stripe = new \Stripe\StripeClient($this->config->secret_key);
        } else {
            throw new \Exception('Stripe SDK not found. Please install via Composer.');
        }
    }

    /**
     * Load Stripe configuration from database
     */
    private function load_config() {
        global $DB;

        try {
            $this->config = $DB->get_record('adeptus_stripe_config', ['id' => 1]);
            if (!$this->config) {
                // Create default config
                $this->create_default_config();
            }
            $this->is_test_mode = (bool)$this->config->is_test_mode;
        } catch (\Exception $e) {
            $this->create_default_config();
        }
    }

    /**
     * Create default Stripe configuration
     */
    private function create_default_config() {
        global $DB;

        $record = [
            'publishable_key' => 'pk_test_...', // Placeholder
            'secret_key' => 'sk_test_...', // Placeholder
            'webhook_secret' => '',
            'is_test_mode' => 1,
            'currency' => 'GBP',
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        try {
            if (!$DB->get_manager()->table_exists('adeptus_stripe_config')) {
                $sql = "CREATE TABLE IF NOT EXISTS {adeptus_stripe_config} (
                    id INT(10) NOT NULL AUTO_INCREMENT,
                    publishable_key CHAR(255) NOT NULL,
                    secret_key CHAR(255) NOT NULL,
                    webhook_secret CHAR(255) NULL,
                    is_test_mode INT(1) NOT NULL DEFAULT 1,
                    currency CHAR(3) NOT NULL DEFAULT 'GBP',
                    timecreated INT(10) NOT NULL,
                    timemodified INT(10) NOT NULL,
                    PRIMARY KEY (id)
                )";
                $DB->execute($sql);
            }

            $DB->insert_record('adeptus_stripe_config', $record);
            $this->config = (object)$record;
        } catch (\Exception $e) {
        }
    }

    /**
     * Get Stripe configuration
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Update Stripe configuration
     */
    public function update_config($data) {
        global $DB;

        $record = [
            'id' => 1,
            'publishable_key' => $data['publishable_key'],
            'secret_key' => $data['secret_key'],
            'webhook_secret' => $data['webhook_secret'] ?? '',
            'is_test_mode' => $data['is_test_mode'] ?? 1,
            'currency' => $data['currency'] ?? 'GBP',
            'timemodified' => time(),
        ];

        $DB->update_record('adeptus_stripe_config', $record);
        $this->config = (object)$record;
        $this->is_test_mode = (bool)$record['is_test_mode'];

        // Reinitialize Stripe with new key
        \Stripe\Stripe::setApiKey($this->config->secret_key);
        $this->stripe = new \Stripe\StripeClient($this->config->secret_key);
    }

    /**
     * Create or get Stripe customer
     */
    public function create_customer($email, $name = null, $metadata = []) {
        try {
            // Check if customer already exists
            $customers = $this->stripe->customers->all([
                'email' => $email,
                'limit' => 1,
            ]);

            if (!empty($customers->data)) {
                return $customers->data[0];
            }

            // Create new customer
            $customer_data = [
                'email' => $email,
                'metadata' => $metadata,
            ];

            if ($name) {
                $customer_data['name'] = $name;
            }

            return $this->stripe->customers->create($customer_data);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Create subscription
     */
    public function create_subscription($customer_id, $price_id, $metadata = []) {
        try {
            $subscription_data = [
                'customer' => $customer_id,
                'items' => [
                    ['price' => $price_id],
                ],
                'metadata' => $metadata,
                'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent'],
            ];

            return $this->stripe->subscriptions->create($subscription_data);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Get subscription details
     */
    public function get_subscription($subscription_id) {
        try {
            return $this->stripe->subscriptions->retrieve($subscription_id);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel_subscription($subscription_id, $at_period_end = true) {
        try {
            $params = [];
            if ($at_period_end) {
                $params['cancel_at_period_end'] = true;
            } else {
                $params['cancel_at_period_end'] = false;
            }

            return $this->stripe->subscriptions->update($subscription_id, $params);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Update subscription
     */
    public function update_subscription($subscription_id, $price_id) {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscription_id);

            return $this->stripe->subscriptions->update($subscription_id, [
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'price' => $price_id,
                    ],
                ],
                'proration_behavior' => 'create_prorations',
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Create payment intent for one-time payments
     */
    public function create_payment_intent($amount, $currency, $customer_id, $metadata = []) {
        try {
            return $this->stripe->paymentIntents->create([
                'amount' => $amount,
                'currency' => $currency,
                'customer' => $customer_id,
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Get all products/prices
     */
    public function get_products() {
        try {
            return $this->stripe->products->all([
                'active' => true,
                'expand' => ['data.default_price'],
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Create product and price
     */
    public function create_product($name, $description, $price, $currency = 'GBP', $interval = 'month') {
        try {
            // Create product
            $product = $this->stripe->products->create([
                'name' => $name,
                'description' => $description,
            ]);

            // Create price
            $price_data = [
                'product' => $product->id,
                'unit_amount' => $price * 100, // Convert to cents
                'currency' => $currency,
            ];

            if ($price > 0) {
                $price_data['recurring'] = [
                    'interval' => $interval,
                ];
            }

            $price_obj = $this->stripe->prices->create($price_data);

            return [
                'product' => $product,
                'price' => $price_obj,
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Verify webhook signature
     */
    public function verify_webhook($payload, $signature) {
        try {
            return \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $this->config->webhook_secret
            );
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Get customer portal URL
     */
    public function create_portal_session($customer_id, $return_url) {
        try {
            return $this->stripe->billingPortal->sessions->create([
                'customer' => $customer_id,
                'return_url' => $return_url,
            ]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Check if in test mode
     */
    public function is_test_mode() {
        return $this->is_test_mode;
    }

    /**
     * Get publishable key
     */
    public function get_publishable_key() {
        return $this->config->publishable_key;
    }

    /**
     * Format amount for display
     */
    public function format_amount($amount, $currency = 'GBP') {
        $symbols = [
            'GBP' => '£',
            'USD' => '$',
            'EUR' => '€',
        ];

        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . number_format($amount / 100, 2);
    }
}

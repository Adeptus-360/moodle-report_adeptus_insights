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

/**
 * Stripe payment service for subscription management.
 *
 * Handles Stripe payment processing, subscription creation, and billing operations.
 */
class stripe_service {
    /** @var object Stripe client instance. */
    private $stripe;

    /** @var object Stripe configuration object. */
    private $config;

    /** @var bool Whether test mode is enabled. */
    private $istestmode;

    /**
     * Constructor.
     */
    public function __construct() {
        global $CFG, $DB;

        // Load Stripe configuration.
        $this->load_config();

        // Initialize Stripe SDK.
        if (!class_exists('\Stripe\Stripe')) {
            // Load Stripe from bundled library.
            $stripepath = $CFG->dirroot . '/report/adeptus_insights/lib/stripe-php/init.php';
            if (file_exists($stripepath)) {
                require_once($stripepath);
            }
        }

        if (class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($this->config->secret_key);
            $this->stripe = new \Stripe\StripeClient($this->config->secret_key);
        } else {
            throw new \moodle_exception('stripesdknotfound', 'report_adeptus_insights');
        }
    }

    /**
     * Load Stripe configuration from database
     */
    private function load_config() {
        global $DB;

        try {
            $this->config = $DB->get_record('report_adeptus_insights_stripe', ['id' => 1]);
            if (!$this->config) {
                // Create default config.
                $this->create_default_config();
            }
            $this->is_test_mode = (bool)$this->config->is_test_mode;
        } catch (\Exception $e) {
            $this->create_default_config();
        }
    }

    /**
     * Create default Stripe configuration.
     *
     * Note: The report_adeptus_insights_stripe table is created by install.xml for new installations
     * and by upgrade.php for existing installations.
     */
    private function create_default_config() {
        global $DB;

        $record = [
            'publishable_key' => 'pk_test_placeholder',
            'secret_key' => 'sk_test_placeholder',
            'webhook_secret' => '',
            'is_test_mode' => 1,
            'currency' => 'GBP',
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        try {
            $dbman = $DB->get_manager();
            if (!$dbman->table_exists('report_adeptus_insights_stripe')) {
                // Table should exist from install.xml or upgrade.php.
                // If not, Stripe features are unavailable until upgrade runs.
                debugging('Stripe config table not found. Please run Moodle upgrade.', DEBUG_DEVELOPER);
                $this->config = (object) $record;
                return;
            }

            $DB->insert_record('report_adeptus_insights_stripe', $record);
            $this->config = (object) $record;
        } catch (\Exception $e) {
            // Ignore config creation errors - Stripe may not be configured yet.
            debugging('Stripe config creation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $this->config = (object) $record;
        }
    }

    /**
     * Get Stripe configuration.
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

        $DB->update_record('report_adeptus_insights_stripe', $record);
        $this->config = (object)$record;
        $this->is_test_mode = (bool)$record['is_test_mode'];

        // Reinitialize Stripe with new key.
        \Stripe\Stripe::setApiKey($this->config->secret_key);
        $this->stripe = new \Stripe\StripeClient($this->config->secret_key);
    }

    /**
     * Create or get Stripe customer
     */
    public function create_customer($email, $name = null, $metadata = []) {
        try {
            // Check if customer already exists.
            $customers = $this->stripe->customers->all([
                'email' => $email,
                'limit' => 1,
            ]);

            if (!empty($customers->data)) {
                return $customers->data[0];
            }

            // Create new customer.
            $customerdata = [
                'email' => $email,
                'metadata' => $metadata,
            ];

            if ($name) {
                $customerdata['name'] = $name;
            }

            return $this->stripe->customers->create($customerdata);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Create subscription
     */
    public function create_subscription($customerid, $priceid, $metadata = []) {
        try {
            $subscriptiondata = [
                'customer' => $customerid,
                'items' => [
                    ['price' => $priceid],
                ],
                'metadata' => $metadata,
                'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent'],
            ];

            return $this->stripe->subscriptions->create($subscriptiondata);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Get subscription details
     */
    public function get_subscription($subscriptionid) {
        try {
            return $this->stripe->subscriptions->retrieve($subscriptionid);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel_subscription($subscriptionid, $atperiodend = true) {
        try {
            $params = [];
            if ($atperiodend) {
                $params['cancel_at_period_end'] = true;
            } else {
                $params['cancel_at_period_end'] = false;
            }

            return $this->stripe->subscriptions->update($subscriptionid, $params);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Update subscription
     */
    public function update_subscription($subscriptionid, $priceid) {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionid);

            return $this->stripe->subscriptions->update($subscriptionid, [
                'items' => [
                    [
                        'id' => $subscription->items->data[0]->id,
                        'price' => $priceid,
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
    public function create_payment_intent($amount, $currency, $customerid, $metadata = []) {
        try {
            return $this->stripe->paymentIntents->create([
                'amount' => $amount,
                'currency' => $currency,
                'customer' => $customerid,
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
            // Create product.
            $product = $this->stripe->products->create([
                'name' => $name,
                'description' => $description,
            ]);

            // Create price.
            $pricedata = [
                'product' => $product->id,
                'unit_amount' => $price * 100, // Convert to cents.
                'currency' => $currency,
            ];

            if ($price > 0) {
                $pricedata['recurring'] = [
                    'interval' => $interval,
                ];
            }

            $priceobj = $this->stripe->prices->create($pricedata);

            return [
                'product' => $product,
                'price' => $priceobj,
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
    public function create_portal_session($customerid, $returnurl) {
        try {
            return $this->stripe->billingPortal->sessions->create([
                'customer' => $customerid,
                'return_url' => $returnurl,
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

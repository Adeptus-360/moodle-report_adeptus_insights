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
 * Stripe Configuration Page
 *
 * Backend configuration for Stripe settings stored in database.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require admin access.
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/admin/stripe_config.php'));
$PAGE->set_title(get_string('stripe_configuration', 'report_adeptus_insights'));
$PAGE->set_heading(get_string('stripe_configuration', 'report_adeptus_insights'));

// Load the Stripe service.
require_once($CFG->dirroot . '/report/adeptus_insights/classes/stripe_service.php');

$message = '';
$message_type = 'info';

// Get form action parameters.
$saveconfig = optional_param('save_config', 0, PARAM_INT);
$syncstripe = optional_param('sync_stripe', 0, PARAM_INT);

// Handle form submission.
if ($saveconfig && confirm_sesskey()) {
    try {
        // Validate and sanitize input using Moodle param functions.
        $test_mode = optional_param('test_mode', 0, PARAM_INT);
        $publishable_key = trim(required_param('publishable_key', PARAM_TEXT));
        $secret_key = trim(required_param('secret_key', PARAM_TEXT));
        $webhook_secret = trim(optional_param('webhook_secret', '', PARAM_TEXT));
        $currency = trim(optional_param('currency', 'USD', PARAM_ALPHA));

        // Validate required fields
        if (empty($publishable_key)) {
            throw new Exception('Publishable key is required');
        }

        if (empty($secret_key)) {
            throw new Exception('Secret key is required');
        }

        // Validate key formats
        if ($test_mode) {
            if (!str_starts_with($publishable_key, 'pk_test_')) {
                throw new Exception('Invalid test publishable key format');
            }
            if (!str_starts_with($secret_key, 'sk_test_')) {
                throw new Exception('Invalid test secret key format');
            }
        } else {
            if (!str_starts_with($publishable_key, 'pk_live_')) {
                throw new Exception('Invalid live publishable key format');
            }
            if (!str_starts_with($secret_key, 'sk_live_')) {
                throw new Exception('Invalid live secret key format');
            }
        }

        // Create Stripe service instance
        $stripe_service = new \report_adeptus_insights\stripe_service();

        // Update configuration
        $config_data = [
            'publishable_key' => $publishable_key,
            'secret_key' => $secret_key,
            'webhook_secret' => $webhook_secret,
            'is_test_mode' => $test_mode,
            'currency' => $currency,
        ];

        $stripe_service->update_config($config_data);

        // Test the configuration
        try {
            $test_result = $stripe_service->get_products();
            $message = 'Stripe configuration saved and tested successfully!';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Configuration saved but test failed: ' . $e->getMessage();
            $message_type = 'warning';
        }
    } catch (Exception $e) {
        $message = 'Error saving configuration: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Stripe sync.
if ($syncstripe && confirm_sesskey()) {
    try {
        $stripe_service = new \report_adeptus_insights\stripe_service();
        $sync_result = sync_stripe_products($stripe_service);
        $message = 'Stripe products synchronized successfully! ' . $sync_result['message'];
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error syncing Stripe products: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get current configuration
try {
    $stripe_service = new \report_adeptus_insights\stripe_service();
    $config = $stripe_service->get_config();
} catch (Exception $e) {
    $config = null;
    if (empty($message)) {
        $message = 'Failed to load current configuration: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Display the page
echo $OUTPUT->header();

// Show message if any
if (!empty($message)) {
    $alert_class = 'alert-' . ($message_type === 'error' ? 'danger' : $message_type);
    echo '<div class="alert ' . $alert_class . '">' . htmlspecialchars($message) . '</div>';
}

// Configuration Form
echo '<div class="card">';
echo '<div class="card-header">';
echo '<h3>' . get_string('stripe_configuration', 'report_adeptus_insights') . '</h3>';
echo '</div>';
echo '<div class="card-body">';

echo '<form method="post" action="">';

// Test Mode
echo '<div class="form-group row">';
echo '<label class="col-sm-3 col-form-label">' . get_string('test_mode', 'report_adeptus_insights') . '</label>';
echo '<div class="col-sm-9">';
echo '<div class="form-check">';
echo '<input type="checkbox" class="form-check-input" id="test_mode" name="test_mode" ' .
     ($config && $config->is_test_mode ? 'checked' : '') . '>';
echo '<label class="form-check-label" for="test_mode">' . get_string('test_mode_desc', 'report_adeptus_insights') . '</label>';
echo '</div>';
echo '</div>';
echo '</div>';

// Publishable Key
echo '<div class="form-group row">';
echo '<label class="col-sm-3 col-form-label">' . get_string('publishable_key', 'report_adeptus_insights') . '</label>';
echo '<div class="col-sm-9">';
echo '<input type="text" class="form-control" name="publishable_key" value="' .
     htmlspecialchars($config ? $config->publishable_key : '') . '" placeholder="pk_test_... or pk_live_...">';
echo '<small class="form-text text-muted">' . get_string('publishable_key_desc', 'report_adeptus_insights') . '</small>';
echo '</div>';
echo '</div>';

// Secret Key
echo '<div class="form-group row">';
echo '<label class="col-sm-3 col-form-label">' . get_string('secret_key', 'report_adeptus_insights') . '</label>';
echo '<div class="col-sm-9">';
echo '<input type="password" class="form-control" name="secret_key" value="' .
     htmlspecialchars($config ? $config->secret_key : '') . '" placeholder="sk_test_... or sk_live_...">';
echo '<small class="form-text text-muted">' . get_string('secret_key_desc', 'report_adeptus_insights') . '</small>';
echo '</div>';
echo '</div>';

// Webhook Secret
echo '<div class="form-group row">';
echo '<label class="col-sm-3 col-form-label">' . get_string('webhook_secret', 'report_adeptus_insights') . '</label>';
echo '<div class="col-sm-9">';
echo '<input type="password" class="form-control" name="webhook_secret" value="' .
     htmlspecialchars($config ? $config->webhook_secret : '') . '" placeholder="whsec_...">';
echo '<small class="form-text text-muted">' . get_string('webhook_secret_desc', 'report_adeptus_insights') . '</small>';
echo '</div>';
echo '</div>';

// Currency
echo '<div class="form-group row">';
echo '<label class="col-sm-3 col-form-label">' . get_string('currency', 'report_adeptus_insights') . '</label>';
echo '<div class="col-sm-9">';
echo '<select class="form-control" name="currency">';
$currencies = ['GBP' => 'British Pound (£)', 'USD' => 'US Dollar ($)', 'EUR' => 'Euro (€)'];
foreach ($currencies as $code => $name) {
    $selected = ($config && $config->currency === $code) ? 'selected' : '';
    echo '<option value="' . $code . '" ' . $selected . '>' . $name . '</option>';
}
echo '</select>';
echo '<small class="form-text text-muted">' . get_string('currency_desc', 'report_adeptus_insights') . '</small>';
echo '</div>';
echo '</div>';

// Buttons
echo '<div class="form-group row">';
echo '<div class="col-sm-9 offset-sm-3">';
echo '<button type="submit" name="save_config" class="btn btn-primary">' . get_string('save_configuration', 'report_adeptus_insights') . '</button>';
echo '<button type="submit" name="sync_stripe" class="btn btn-secondary ml-2">' . get_string('sync_stripe_products', 'report_adeptus_insights') . '</button>';
echo '</div>';
echo '</div>';

echo '</form>';
echo '</div>';
echo '</div>';

// Current Products Table
if ($config) {
    try {
        $products = $DB->get_records('adeptus_stripe_plans', ['is_active' => 1], 'sort_order ASC');
        if (!empty($products)) {
            echo '<div class="card mt-4">';
            echo '<div class="card-header">';
            echo '<h4>' . get_string('current_products', 'report_adeptus_insights') . '</h4>';
            echo '</div>';
            echo '<div class="card-body">';
            echo '<table class="table table-striped">';
            echo '<thead><tr>';
            echo '<th>' . get_string('name', 'report_adeptus_insights') . '</th>';
            echo '<th>' . get_string('price', 'report_adeptus_insights') . '</th>';
            echo '<th>' . get_string('ai_credits', 'report_adeptus_insights') . '</th>';
            echo '<th>' . get_string('exports', 'report_adeptus_insights') . '</th>';
            echo '<th>' . get_string('stripe_product_id', 'report_adeptus_insights') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ($products as $product) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($product->name) . '</td>';
                echo '<td>' . $stripe_service->format_amount($product->price * 100, $product->currency ?? 'GBP') . '</td>';
                echo '<td>' . $product->ai_credits . '</td>';
                echo '<td>' . $product->exports . '</td>';
                echo '<td><code>' . htmlspecialchars($product->stripe_product_id) . '</code></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
            echo '</div>';
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-warning mt-4">No products found in database.</div>';
    }
}

// Back button
echo '<div class="mt-3">';
echo '<a href="' . new moodle_url('/admin/settings.php', ['section' => 'report_adeptus_insights']) . '" class="btn btn-secondary">';
echo get_string('back_to_settings', 'report_adeptus_insights');
echo '</a>';
echo '</div>';

echo $OUTPUT->footer();

/**
 * Sync Stripe products to local database
 */
function sync_stripe_products($stripe_service) {
    global $DB;

    try {
        // Get products from Stripe
        $stripe_products = $stripe_service->get_products();

        $synced_count = 0;
        $updated_count = 0;

        foreach ($stripe_products->data as $stripe_product) {
            // Get the default price for this product
            $price = $stripe_product->default_price;
            if (!$price) {
                continue;
            }

            // Check if product exists in database
            $existing = $DB->get_record('adeptus_stripe_plans', ['stripe_product_id' => $stripe_product->id]);

            $product_data = [
                'stripe_product_id' => $stripe_product->id,
                'stripe_price_id' => $price->id,
                'name' => $stripe_product->name,
                'description' => $stripe_product->description ?? '',
                'price' => $price->unit_amount / 100, // Convert from cents
                'billing_cycle' => $price->recurring ? $price->recurring->interval : 'one-time',
                'ai_credits' => 0, // Default values, should be set manually
                'ai_credits_pro' => 0,
                'ai_credits_basic' => 0,
                'exports' => 0,
                'is_active' => 1,
                'is_free' => ($price->unit_amount == 0) ? 1 : 0,
                'sort_order' => 0,
                'timemodified' => time(),
            ];

            if ($existing) {
                // Update existing product
                $product_data['id'] = $existing->id;
                $DB->update_record('adeptus_stripe_plans', $product_data);
                $updated_count++;
            } else {
                // Create new product
                $product_data['timecreated'] = time();
                $DB->insert_record('adeptus_stripe_plans', $product_data);
                $synced_count++;
            }
        }

        return [
            'success' => true,
            'message' => "Synced {$synced_count} new products, updated {$updated_count} existing products.",
        ];
    } catch (Exception $e) {
        throw new Exception('Failed to sync Stripe products: ' . $e->getMessage());
    }
}

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
 * Save Stripe Configuration Handler.
 *
 * Transfers Moodle settings to the plugin's database configuration.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require admin access
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/admin/save_stripe_config.php'));
$PAGE->set_title(get_string('save_stripe_config', 'report_adeptus_insights'));
$PAGE->set_heading(get_string('save_stripe_config', 'report_adeptus_insights'));

// Load the Stripe service
require_once($CFG->dirroot . '/report/adeptus_insights/classes/stripe_service.php');

$message = '';
$message_type = 'info';

try {
    // Get Moodle settings
    $test_mode = get_config('report_adeptus_insights', 'test_mode');
    $publishable_key = get_config('report_adeptus_insights', 'publishable_key');
    $secret_key = get_config('report_adeptus_insights', 'secret_key');
    $webhook_secret = get_config('report_adeptus_insights', 'webhook_secret');
    $currency = get_config('report_adeptus_insights', 'currency') ?: 'GBP';

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
        'is_test_mode' => $test_mode ? 1 : 0,
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

// Display the result
echo $OUTPUT->header();

$alert_class = 'alert-' . ($message_type === 'error' ? 'danger' : $message_type);
echo '<div class="alert ' . $alert_class . '">' . htmlspecialchars($message) . '</div>';

// Add back button
echo '<div class="mt-3">';
echo '<a href="' . new moodle_url('/admin/settings.php', ['section' => 'report_adeptus_insights']) . '" class="btn btn-secondary">';
echo get_string('back_to_settings', 'report_adeptus_insights');
echo '</a>';
echo '</div>';

echo $OUTPUT->footer();

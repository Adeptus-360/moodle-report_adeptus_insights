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
 * Admin settings for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Ensure the util class is loaded
require_once(__DIR__ . '/classes/util.php');

if ($hassiteconfig) {
    $settings = new admin_settingpage('report_adeptus_insights', get_string('pluginname', 'report_adeptus_insights'));
    $ADMIN->add('reports', $settings);

    // General Configuration Section
    $settings->add(new admin_setting_heading('report_adeptus_insights/general', 
        get_string('general_configuration', 'report_adeptus_insights'), 
        get_string('general_configuration_desc', 'report_adeptus_insights')));

    // Enable Plugin
    $settings->add(new admin_setting_configcheckbox('report_adeptus_insights/enabled',
        get_string('enable_plugin', 'report_adeptus_insights'),
        get_string('enable_plugin_desc', 'report_adeptus_insights'),
        1));

    // Usage Tracking Section
    $settings->add(new admin_setting_heading('report_adeptus_insights/usage', 
        get_string('usage_tracking', 'report_adeptus_insights'), 
        get_string('usage_tracking_desc', 'report_adeptus_insights')));

    // Enable Usage Tracking
    $settings->add(new admin_setting_configcheckbox('report_adeptus_insights/enable_usage_tracking',
        get_string('enable_usage_tracking', 'report_adeptus_insights'),
        get_string('enable_usage_tracking_desc', 'report_adeptus_insights'),
        1));

    // Usage Retention Days
    $settings->add(new admin_setting_configtext('report_adeptus_insights/usage_retention_days',
        get_string('usage_retention_days', 'report_adeptus_insights'),
        get_string('usage_retention_days_desc', 'report_adeptus_insights'),
        365,
        PARAM_INT));

    // Email Notifications Section
    $settings->add(new admin_setting_heading('report_adeptus_insights/notifications', 
        get_string('email_notifications', 'report_adeptus_insights'), 
        get_string('email_notifications_desc', 'report_adeptus_insights')));

    // Enable Email Notifications
    $settings->add(new admin_setting_configcheckbox('report_adeptus_insights/enable_email_notifications',
        get_string('enable_email_notifications', 'report_adeptus_insights'),
        get_string('enable_email_notifications_desc', 'report_adeptus_insights'),
        1));

    // Notification Email
    $settings->add(new admin_setting_configtext('report_adeptus_insights/notification_email',
        get_string('notification_email', 'report_adeptus_insights'),
        get_string('notification_email_desc', 'report_adeptus_insights'),
        '',
        PARAM_EMAIL));

    // Advanced Settings Section
    $settings->add(new admin_setting_heading('report_adeptus_insights/advanced', 
        get_string('advanced_settings', 'report_adeptus_insights'), 
        get_string('advanced_settings_desc', 'report_adeptus_insights')));

    // Debug Mode
    $settings->add(new admin_setting_configcheckbox('report_adeptus_insights/debug_mode',
        get_string('debug_mode', 'report_adeptus_insights'),
        get_string('debug_mode_desc', 'report_adeptus_insights'),
        0));

    // API Timeout
    $settings->add(new admin_setting_configtext('report_adeptus_insights/api_timeout',
        get_string('api_timeout', 'report_adeptus_insights'),
        get_string('api_timeout_desc', 'report_adeptus_insights'),
        30,
        PARAM_INT));

    // Stripe Configuration Link
    $settings->add(new admin_setting_heading('report_adeptus_insights/stripe_link', 
        get_string('stripe_configuration', 'report_adeptus_insights'), 
        get_string('stripe_configuration_desc', 'report_adeptus_insights') . '<br><br>' .
        '<a href="' . new moodle_url('/report/adeptus_insights/admin/stripe_config.php') . '" class="btn btn-primary">' . 
        get_string('configure_stripe', 'report_adeptus_insights') . '</a>'));

    // ---------- Simple post-install redirect to subscription ----------
    if (!empty($ADMIN->fulltree)) {
        $component = 'report_adeptus_insights';
        $stage = (int) get_config($component, 'postinstall_redirect_stage');

        // If we have stage 2, redirect directly to subscription
        if ($stage == 2) {
            // Reset the stage and redirect to subscription
            set_config('postinstall_redirect_stage', 0, 'report_adeptus_insights');
            redirect(new moodle_url('/report/adeptus_insights/subscription.php'));
        }
    }
} 
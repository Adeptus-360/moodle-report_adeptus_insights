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

if ($hassiteconfig) {
    $settings = new admin_settingpage('report_adeptus_insights', get_string('pluginname', 'report_adeptus_insights'));
    $ADMIN->add('reports', $settings);

    // Email Notifications Section
    $settings->add(new admin_setting_heading(
        'report_adeptus_insights/notifications',
        get_string('email_notifications', 'report_adeptus_insights'),
        get_string('email_notifications_desc', 'report_adeptus_insights')
    ));

    // Enable Email Notifications
    $settings->add(new admin_setting_configcheckbox(
        'report_adeptus_insights/enable_email_notifications',
        get_string('enable_email_notifications', 'report_adeptus_insights'),
        get_string('enable_email_notifications_desc', 'report_adeptus_insights'),
        1
    ));

    // Notification Email
    $settings->add(new admin_setting_configtext(
        'report_adeptus_insights/notification_email',
        get_string('notification_email', 'report_adeptus_insights'),
        get_string('notification_email_desc', 'report_adeptus_insights'),
        '',
        PARAM_EMAIL
    ));

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

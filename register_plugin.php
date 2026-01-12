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
 * Plugin Registration Page for Adeptus Insights.
 *
 * Handles the registration of the plugin with the backend API.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/register_plugin.php'));
$PAGE->set_title(get_string('pluginname', 'report_adeptus_insights') . ' - Registration');
$PAGE->set_heading(get_string('pluginname', 'report_adeptus_insights') . ' - Registration');

// Load installation manager
require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
$installation_manager = new \report_adeptus_insights\installation_manager();

// Check if already registered
$is_registered = $installation_manager->is_registered();
$installation_id = $installation_manager->get_installation_id();
$api_key = $installation_manager->get_api_key();

// Get current user info
$user = $USER;
$admin_email = $user->email ?? '';
$admin_name = fullname($user) ?? '';

// Get site information with better fallbacks
$site_url = $CFG->wwwroot ?? '';
$site_name = $CFG->fullname ?? $CFG->shortname ?? 'Moodle Site';
$moodle_version = $CFG->version ?? '';
$php_version = PHP_VERSION ?? '';
$plugin_version = $installation_manager->get_plugin_version() ?? '';

// Try to get site name from database if config is not available
if (empty($site_name) || $site_name === 'Moodle Site') {
    global $DB;
    try {
        $config_record = $DB->get_record('config', ['name' => 'fullname']);
        if ($config_record && !empty($config_record->value)) {
            $site_name = $config_record->value;
        } else {
            $config_record = $DB->get_record('config', ['name' => 'shortname']);
            if ($config_record && !empty($config_record->value)) {
                $site_name = $config_record->value;
            }
        }
    } catch (Exception $e) {
        // Ignore database errors - fall back to default site name.
    }
}

// Debug logging for troubleshooting
if (empty($site_name) || $site_name === 'Moodle Site') {
    // In debug mode, try to get the actual value from the database
    if (debugging()) {
        global $DB;
        try {
            $fullname_record = $DB->get_record('config', ['name' => 'fullname']);
            $shortname_record = $DB->get_record('config', ['name' => 'shortname']);


            // Use the database value if available
            if ($fullname_record && !empty($fullname_record->value)) {
                $site_name = $fullname_record->value;
            } else if ($shortname_record && !empty($shortname_record->value)) {
                $site_name = $shortname_record->value;
            }
        } catch (Exception $e) {
            // Ignore database errors during debug mode lookup.
        }
    }
}

// Validate required fields
$missing_fields = [];
if (empty($site_name)) {
    $missing_fields[] = 'Site Name';
}
if (empty($site_url)) {
    $missing_fields[] = 'Site URL';
}
if (empty($admin_name)) {
    $missing_fields[] = 'Administrator Name';
}
if (empty($admin_email)) {
    $missing_fields[] = 'Administrator Email';
}
if (empty($moodle_version)) {
    $missing_fields[] = 'Moodle Version';
}
if (empty($php_version)) {
    $missing_fields[] = 'PHP Version';
}
if (empty($plugin_version)) {
    $missing_fields[] = 'Plugin Version';
}

if (!empty($missing_fields)) {
    $error_message = 'Missing required Moodle configuration: ' . implode(', ', $missing_fields) . '. Please ensure these values are properly set in your Moodle configuration.';
}

// Handle form submission
if (optional_param('action', '', PARAM_ALPHA) === 'register' && confirm_sesskey()) {
    // Check if we have missing fields
    if (!empty($missing_fields)) {
        $error_message = 'Cannot register plugin due to missing required information: ' . implode(', ', $missing_fields) . '. Please ensure these values are properly set in your Moodle configuration.';
    } else {
        try {
            $result = $installation_manager->register_installation($admin_email, $admin_name, $site_url, $site_name);

            if ($result['success']) {
                redirect(
                    new moodle_url('/report/adeptus_insights/subscription_installation_step.php'),
                    $result['message'],
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                // Check if we need to redirect to index due to site already existing
                if (isset($result['code']) && $result['code'] === 'SITE_EXISTS') {
                    redirect(
                        new moodle_url('/report/adeptus_insights/index.php'),
                        'Site already exists on backend. You have been redirected to the main page.',
                        null,
                        \core\output\notification::NOTIFY_INFO
                    );
                }

                $error_message = $result['message'];
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Prepare template context
$templatecontext = [
    'is_registered' => $is_registered,
    'installation_id' => $installation_id,
    'api_key' => $api_key,
    'admin_email' => $admin_email,
    'admin_name' => $admin_name,
    'site_url' => $site_url,
    'site_name' => $site_name,
    'moodle_version' => $moodle_version,
    'php_version' => $php_version,
    'plugin_version' => $plugin_version,
    'sesskey' => sesskey(),
    'error_message' => $error_message ?? null,
    'site_already_exists' => isset($error_message) && strpos($error_message, 'Site already exists') !== false,
    'debug' => debugging(), // Show debug info if debugging is enabled
];

// Output the page
echo $OUTPUT->header();

// Render the registration template
echo $OUTPUT->render_from_template('report_adeptus_insights/register_plugin', $templatecontext);

echo $OUTPUT->footer();

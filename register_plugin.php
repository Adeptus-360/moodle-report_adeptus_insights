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
 * Plugin Registration Page for Adeptus Insights.
 *
 * Handles the registration of the plugin with the backend API.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Force Boost theme for consistent plugin UI.
$CFG->theme = 'boost';

require_once($CFG->libdir . '/adminlib.php');

// Require login and capability.
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/report/adeptus_insights/register_plugin.php'));
$PAGE->set_title(get_string('pluginname', 'report_adeptus_insights') . ' - Registration');
$PAGE->set_heading(get_string('pluginname', 'report_adeptus_insights') . ' - Registration');

// Load external CSS.
$PAGE->requires->css('/report/adeptus_insights/styles/register.css');

// Load installation manager.
$installationmanager = new \report_adeptus_insights\installation_manager();

// Check if already registered.
$isregistered = $installationmanager->is_registered();
$installationid = $installationmanager->get_installation_id();
$apikey = $installationmanager->get_api_key();

// Get current user info.
$user = $USER;
$adminemail = $user->email ?? '';
$adminname = fullname($user) ?? '';

// Get site information - use $SITE which contains the frontpage course with site name.
global $SITE;
$siteurl = $CFG->wwwroot ?? '';
$sitename = $SITE->fullname ?? $SITE->shortname ?? 'Moodle Site';
$moodleversion = $CFG->version ?? '';
$phpversion = PHP_VERSION ?? '';
$pluginversion = $installationmanager->get_plugin_version() ?? '';

// Validate required fields.
$missingfields = [];
if (empty($sitename)) {
    $missingfields[] = 'Site Name';
}
if (empty($siteurl)) {
    $missingfields[] = 'Site URL';
}
if (empty($adminname)) {
    $missingfields[] = 'Administrator Name';
}
if (empty($adminemail)) {
    $missingfields[] = 'Administrator Email';
}
if (empty($moodleversion)) {
    $missingfields[] = 'Moodle Version';
}
if (empty($phpversion)) {
    $missingfields[] = 'PHP Version';
}
if (empty($pluginversion)) {
    $missingfields[] = 'Plugin Version';
}

if (!empty($missingfields)) {
    $errormessage = 'Missing required Moodle configuration: ' . implode(', ', $missingfields) .
        '. Please ensure these values are properly set in your Moodle configuration.';
}

// Handle form submission.
if (optional_param('action', '', PARAM_ALPHA) === 'register' && confirm_sesskey()) {
    // Check if we have missing fields.
    if (!empty($missingfields)) {
        $errormessage = 'Cannot register plugin due to missing required information: ' .
            implode(', ', $missingfields) .
            '. Please ensure these values are properly set in your Moodle configuration.';
    } else {
        try {
            $result = $installationmanager->register_installation($adminemail, $adminname, $siteurl, $sitename);

            if ($result['success']) {
                redirect(
                    new moodle_url('/report/adeptus_insights/subscription_installation_step.php'),
                    $result['message'],
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                // Check if we need to redirect to index due to site already existing.
                if (isset($result['code']) && $result['code'] === 'SITE_EXISTS') {
                    redirect(
                        new moodle_url('/report/adeptus_insights/index.php'),
                        'Site already exists on backend. You have been redirected to the main page.',
                        null,
                        \core\output\notification::NOTIFY_INFO
                    );
                }

                $errormessage = $result['message'];
            }
        } catch (Exception $e) {
            $errormessage = $e->getMessage();
        }
    }
}

// Prepare template context.
$templatecontext = [
    'is_registered' => $isregistered,
    'installation_id' => $installationid,
    'api_key' => $apikey,
    'admin_email' => $adminemail,
    'admin_name' => $adminname,
    'site_url' => $siteurl,
    'site_name' => $sitename,
    'moodle_version' => $moodleversion,
    'php_version' => $phpversion,
    'plugin_version' => $pluginversion,
    'sesskey' => sesskey(),
    'error_message' => $errormessage ?? null,
    'site_already_exists' => isset($errormessage) && strpos($errormessage, 'Site already exists') !== false,
    'debug' => debugging(), // Show debug info if debugging is enabled.
];

// Load AMD module for form validation (only if not registered).
if (!$isregistered) {
    $PAGE->requires->js_call_amd('report_adeptus_insights/register_plugin', 'init', [[
        'fieldValues' => [
            'siteName' => $sitename,
            'siteUrl' => $siteurl,
            'adminName' => $adminname,
            'adminEmail' => $adminemail,
            'moodleVersion' => $moodleversion,
            'phpVersion' => $phpversion,
            'pluginVersion' => $pluginversion,
        ],
    ]]);
}

// Output the page.
echo $OUTPUT->header();

// Render the registration template.
echo $OUTPUT->render_from_template('report_adeptus_insights/register_plugin', $templatecontext);

echo $OUTPUT->footer();

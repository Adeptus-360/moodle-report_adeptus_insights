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
 * Get Authentication Status AJAX Endpoint.
 *
 * Simple endpoint to get authentication status without external functions.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check if user is logged in to Moodle (but don't require plugin authentication)
require_login();

// Check basic Moodle capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Set content type
header('Content-Type: application/json');

try {
    // Include the auth manager
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/token_auth_manager.php');

    // Get authentication status
    $authmanager = new \report_adeptus_insights\token_auth_manager();
    $authstatus = $authmanager->get_auth_status();

    // Get installation data for the login form
    global $DB;
    $installsettings = $DB->get_record('report_adeptus_insights_settings', ['id' => 1]);

    if ($installsettings) {
        // Extract site URL from Moodle configuration
        $siteurl = $CFG->wwwroot;

        // Get admin email from Moodle configuration
        $adminemail = $CFG->supportemail ?? $CFG->admin ?? 'admin@' . parse_url($siteurl, PHP_URL_HOST);

        // Add installation data to auth status
        $authstatus['installation_info'] = [
            'site_url' => $siteurl,
            'admin_email' => $adminemail,
            'api_url' => $installsettings->api_url,
            'installation_id' => $installsettings->installation_id,
        ];
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $authstatus,
    ]);
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => get_string('error_get_auth_status_failed', 'report_adeptus_insights', $e->getMessage()),
        'data' => null,
    ]);
}

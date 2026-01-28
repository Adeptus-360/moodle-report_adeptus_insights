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

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_system;

/**
 * External service to get authentication status.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_auth_status extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get authentication status.
     *
     * @return array Result
     */
    public static function execute(): array {
        global $CFG, $DB;

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            // Get authentication status.
            $authmanager = new \report_adeptus_insights\token_auth_manager();
            $authstatus = $authmanager->get_auth_status();

            // Get installation data for the login form.
            $installsettings = $DB->get_record('report_adeptus_insights_settings', ['id' => 1]);

            $installationinfo = [];
            if ($installsettings) {
                // Extract site URL from Moodle configuration.
                $siteurl = $CFG->wwwroot;

                // Get admin email from Moodle configuration.
                $adminemail = $CFG->supportemail ?? $CFG->admin ?? 'admin@' . parse_url($siteurl, PHP_URL_HOST);

                $installationinfo = [
                    'site_url' => $siteurl,
                    'admin_email' => $adminemail,
                    'api_url' => $installsettings->api_url ?? '',
                    'installation_id' => $installsettings->installation_id ?? '',
                ];
            }

            return [
                'success' => true,
                'message' => '',
                'is_authenticated' => $authstatus['is_authenticated'] ?? false,
                'user_email' => $authstatus['user_email'] ?? '',
                'token_expires_at' => $authstatus['token_expires_at'] ?? 0,
                'installation_info' => json_encode($installationinfo),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('error_get_auth_status_failed', 'report_adeptus_insights', $e->getMessage()),
                'is_authenticated' => false,
                'user_email' => '',
                'token_expires_at' => 0,
                'installation_info' => '{}',
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'message' => new external_value(PARAM_TEXT, 'Error message if any'),
            'is_authenticated' => new external_value(PARAM_BOOL, 'Whether user is authenticated'),
            'user_email' => new external_value(PARAM_TEXT, 'User email if authenticated'),
            'token_expires_at' => new external_value(PARAM_INT, 'Token expiration timestamp'),
            'installation_info' => new external_value(PARAM_RAW, 'JSON-encoded installation info'),
        ]);
    }
}

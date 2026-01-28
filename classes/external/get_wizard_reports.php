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

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * External API for getting user's saved wizard reports from backend.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_wizard_reports extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Get user's saved wizard reports from backend.
     *
     * @return array List of user's saved reports.
     */
    public static function execute() {
        global $USER;

        // Parameter validation (none for this endpoint).
        $params = self::validate_parameters(self::execute_parameters(), []);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability check.
        require_capability('report/adeptus_insights:view', $context);

        // Get API key and backend URL.
        $installationmanager = new \report_adeptus_insights\installation_manager();
        $apikey = $installationmanager->get_api_key();
        $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();

        if (empty($apikey)) {
            return [
                'success' => false,
                'message' => get_string('error_api_key_not_configured', 'report_adeptus_insights'),
                'reports' => [],
                'count' => 0,
            ];
        }

        // Fetch wizard reports from backend API.
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');
        $curl->setHeader('Authorization: Bearer ' . $apikey);
        $options = [
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        $userid = $USER->id;
        $response = $curl->get($backendapiurl . '/wizard-reports?user_id=' . $userid, [], $options);
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($httpcode !== 200) {
            return [
                'success' => false,
                'message' => get_string('error_fetch_wizard_reports_failed', 'report_adeptus_insights'),
                'reports' => [],
                'count' => 0,
            ];
        }

        $data = json_decode($response, true);

        if (empty($data['success'])) {
            return [
                'success' => false,
                'message' => $data['message'] ?? get_string('error_fetch_wizard_reports_generic', 'report_adeptus_insights'),
                'reports' => [],
                'count' => 0,
            ];
        }

        // Format reports for external return structure.
        $reports = $data['reports'] ?? [];
        $formattedreports = [];

        foreach ($reports as $report) {
            $formattedreports[] = [
                'id' => (int) ($report['id'] ?? 0),
                'slug' => $report['slug'] ?? '',
                'name' => $report['name'] ?? '',
                'report_template_id' => $report['report_template_id'] ?? '',
                'parameters' => json_encode($report['parameters'] ?? []),
                'created_at' => $report['created_at'] ?? '',
                'updated_at' => $report['updated_at'] ?? '',
            ];
        }

        return [
            'success' => true,
            'message' => '',
            'reports' => $formattedreports,
            'count' => count($formattedreports),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'message' => new external_value(PARAM_TEXT, 'Error or status message', VALUE_OPTIONAL),
            'reports' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Report ID'),
                    'slug' => new external_value(PARAM_TEXT, 'Report slug/identifier'),
                    'name' => new external_value(PARAM_TEXT, 'Report name'),
                    'report_template_id' => new external_value(PARAM_TEXT, 'Template ID used'),
                    'parameters' => new external_value(PARAM_RAW, 'JSON-encoded parameters'),
                    'created_at' => new external_value(PARAM_TEXT, 'Creation timestamp'),
                    'updated_at' => new external_value(PARAM_TEXT, 'Last update timestamp'),
                ]),
                'List of user saved reports'
            ),
            'count' => new external_value(PARAM_INT, 'Number of reports'),
        ]);
    }
}

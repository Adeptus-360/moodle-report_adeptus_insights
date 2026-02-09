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

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_system;

/**
 * External service to get AI-generated reports via server-side proxy.
 *
 * Proxies the request to the backend API to avoid browser CORS issues.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_ai_reports extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Fetch AI-generated reports from the backend.
     *
     * @return array JSON-encoded response.
     */
    public static function execute(): array {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apiurl = $installationmanager->get_api_url();
            $apikey = $installationmanager->get_api_key();

            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Accept: application/json');
            if ($apikey) {
                $curl->setHeader('Authorization: Bearer ' . $apikey);
            }

            $options = [
                'CURLOPT_TIMEOUT' => 15,
                'CURLOPT_RETURNTRANSFER' => true,
            ];

            $response = $curl->get($apiurl . '/ai-reports', [], $options);
            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;

            if ($httpcode === 200 && $response) {
                return [
                    'success' => true,
                    'data' => $response,
                ];
            }

            return [
                'success' => true,
                'data' => json_encode(['reports' => [], 'data' => []]),
            ];
        } catch (\Exception $e) {
            return [
                'success' => true,
                'data' => json_encode(['reports' => [], 'data' => []]),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the request succeeded'),
            'data' => new external_value(PARAM_RAW, 'JSON-encoded AI reports data'),
        ]);
    }
}

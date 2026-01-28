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
 * External service to update a report's category.
 *
 * This endpoint proxies category update requests to the backend API.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_report_category extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'slug' => new external_value(PARAM_TEXT, 'Report slug'),
            'category_id' => new external_value(PARAM_INT, 'Category ID to assign'),
            'source' => new external_value(PARAM_ALPHA, 'Report source: wizard or assistant', VALUE_DEFAULT, 'assistant'),
        ]);
    }

    /**
     * Update a report's category.
     *
     * @param string $slug Report slug
     * @param int $categoryid Category ID
     * @param string $source Report source (wizard or assistant)
     * @return array Result
     */
    public static function execute(string $slug, int $categoryid, string $source = 'assistant'): array {
        global $CFG;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'slug' => $slug,
            'category_id' => $categoryid,
            'source' => $source,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            // Get installation manager and API configuration.
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
            $backendurl = \report_adeptus_insights\api_config::get_backend_url();

            if (empty($apikey)) {
                throw new \Exception(get_string('error_installation_not_configured', 'report_adeptus_insights'));
            }

            // Determine the correct API endpoint based on report source.
            if ($params['source'] === 'wizard') {
                $endpoint = rtrim($backendurl, '/') . '/wizard-reports/' . urlencode($params['slug']) . '/category';
            } else {
                $endpoint = rtrim($backendurl, '/') . '/ai-reports/' . urlencode($params['slug']) . '/category';
            }

            $postdata = json_encode([
                'category_id' => $params['category_id'],
            ]);

            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Accept: application/json');
            $curl->setHeader('Authorization: Bearer ' . $apikey);
            $options = [
                'CURLOPT_TIMEOUT' => 15,
                'CURLOPT_CONNECTTIMEOUT' => 10,
                'CURLOPT_SSL_VERIFYPEER' => true,
            ];

            $response = $curl->put($endpoint, $postdata, $options);
            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;
            $curlerror = $curl->get_errno() ? $curl->error : '';

            // Handle connection errors.
            if ($response === false || !empty($curlerror)) {
                debugging('[Adeptus Insights] Update report category failed - curl error: ' . $curlerror, DEBUG_DEVELOPER);
                return [
                    'success' => false,
                    'message' => get_string('error_connect_backend', 'report_adeptus_insights'),
                    'data' => '',
                ];
            }

            // Handle HTTP errors.
            if ($httpcode !== 200 && $httpcode !== 204) {
                debugging('[Adeptus Insights] Update report category failed - HTTP ' . $httpcode .
                    ' Endpoint: ' . $endpoint . ' Response: ' . substr($response, 0, 500), DEBUG_DEVELOPER);
                $errordata = json_decode($response, true);
                $errormessage = $errordata['message'] ?? get_string('category_update_failed', 'report_adeptus_insights');
                return [
                    'success' => false,
                    'message' => $errormessage,
                    'data' => '',
                ];
            }

            // Parse backend response.
            $backenddata = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                debugging('[Adeptus Insights] Update report category failed - invalid JSON response', DEBUG_DEVELOPER);
                return [
                    'success' => false,
                    'message' => get_string('error_invalid_backend_json', 'report_adeptus_insights'),
                    'data' => '',
                ];
            }

            return [
                'success' => $backenddata['success'] ?? true,
                'message' => $backenddata['message'] ?? get_string('category_updated', 'report_adeptus_insights'),
                'data' => isset($backenddata['data']) ? json_encode($backenddata['data']) : '',
            ];
        } catch (\Exception $e) {
            debugging('[Adeptus Insights] Update report category exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => false,
                'message' => get_string('error_managing_category', 'report_adeptus_insights', $e->getMessage()),
                'data' => '',
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
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'data' => new external_value(PARAM_RAW, 'JSON-encoded data from backend'),
        ]);
    }
}

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
use external_multiple_structure;
use external_value;
use context_system;

/**
 * External service to manage report categories.
 *
 * This endpoint proxies category management requests to the backend API.
 * Supported actions: list, create, update, delete
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_category extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_ALPHA, 'Action: list, create, update, or delete'),
            'category_id' => new external_value(PARAM_INT, 'Category ID (for update/delete)', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Category name (for create/update)', VALUE_DEFAULT, ''),
            'color' => new external_value(PARAM_TEXT, 'Category color (for create/update)', VALUE_DEFAULT, '#6c757d'),
        ]);
    }

    /**
     * Manage report categories.
     *
     * @param string $action Action to perform
     * @param int $categoryid Category ID
     * @param string $name Category name
     * @param string $color Category color
     * @return array Result
     */
    public static function execute(string $action, int $categoryid = 0, string $name = '', string $color = '#6c757d'): array {
        global $CFG;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'action' => $action,
            'category_id' => $categoryid,
            'name' => $name,
            'color' => $color,
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

            // Build endpoint and request based on action.
            $endpoint = rtrim($backendurl, '/') . '/reports/categories';
            $method = 'GET';
            $postdata = null;

            switch ($params['action']) {
                case 'list':
                    $method = 'GET';
                    break;

                case 'create':
                    if (empty($params['name'])) {
                        return [
                            'success' => false,
                            'message' => get_string('error_name_required', 'report_adeptus_insights'),
                            'categories' => '[]',
                            'category' => '',
                        ];
                    }
                    $method = 'POST';
                    $postdata = json_encode([
                        'name' => $params['name'],
                        'color' => $params['color'],
                    ]);
                    break;

                case 'update':
                    if (empty($params['category_id'])) {
                        return [
                            'success' => false,
                            'message' => get_string('error_category_id_required', 'report_adeptus_insights'),
                            'categories' => '[]',
                            'category' => '',
                        ];
                    }
                    if (empty($params['name'])) {
                        return [
                            'success' => false,
                            'message' => get_string('error_name_required', 'report_adeptus_insights'),
                            'categories' => '[]',
                            'category' => '',
                        ];
                    }
                    $method = 'PUT';
                    $endpoint .= '/' . $params['category_id'];
                    $postdata = json_encode([
                        'name' => $params['name'],
                        'color' => $params['color'],
                    ]);
                    break;

                case 'delete':
                    if (empty($params['category_id'])) {
                        return [
                            'success' => false,
                            'message' => get_string('error_category_id_required', 'report_adeptus_insights'),
                            'categories' => '[]',
                            'category' => '',
                        ];
                    }
                    $method = 'DELETE';
                    $endpoint .= '/' . $params['category_id'];
                    break;

                default:
                    return [
                        'success' => false,
                        'message' => get_string('error_invalid_action', 'report_adeptus_insights'),
                        'categories' => '[]',
                        'category' => '',
                    ];
            }

            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Accept: application/json');
            $curl->setHeader('Authorization: Bearer ' . $apikey);
            $options = [
                'CURLOPT_TIMEOUT' => 15,
                'CURLOPT_CONNECTTIMEOUT' => 10,
                'CURLOPT_SSL_VERIFYPEER' => true,
            ];

            // Execute request based on HTTP method.
            switch ($method) {
                case 'GET':
                    $response = $curl->get($endpoint, [], $options);
                    break;
                case 'POST':
                    $response = $curl->post($endpoint, $postdata, $options);
                    break;
                case 'PUT':
                    $response = $curl->put($endpoint, $postdata, $options);
                    break;
                case 'DELETE':
                    $response = $curl->delete($endpoint, [], $options);
                    break;
                default:
                    throw new \Exception(get_string('error_invalid_action', 'report_adeptus_insights'));
            }

            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;
            $curlerror = $curl->get_errno() ? $curl->error : '';

            // Handle connection errors.
            if ($response === false || !empty($curlerror)) {
                debugging('[Adeptus Insights] Category management failed - curl error: ' . $curlerror, DEBUG_DEVELOPER);
                return [
                    'success' => false,
                    'message' => get_string('error_connect_backend', 'report_adeptus_insights'),
                    'categories' => '[]',
                    'category' => '',
                ];
            }

            // Handle HTTP errors.
            if ($httpcode !== 200 && $httpcode !== 201) {
                debugging('[Adeptus Insights] Category management failed - HTTP ' . $httpcode . ': ' . $response, DEBUG_DEVELOPER);
                $errordata = json_decode($response, true);
                return [
                    'success' => false,
                    'message' => $errordata['message'] ?? get_string('category_manage_failed', 'report_adeptus_insights'),
                    'categories' => '[]',
                    'category' => '',
                ];
            }

            // Parse backend response.
            $backenddata = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                debugging('[Adeptus Insights] Category management failed - invalid JSON response', DEBUG_DEVELOPER);
                return [
                    'success' => false,
                    'message' => get_string('error_invalid_backend_json', 'report_adeptus_insights'),
                    'categories' => '[]',
                    'category' => '',
                ];
            }

            // Return the backend response with consistent structure.
            // Backend may return categories in 'categories', 'data', or directly as array.
            $categories = '[]';
            if (isset($backenddata['categories'])) {
                $categories = json_encode($backenddata['categories']);
            } else if (isset($backenddata['data']) && is_array($backenddata['data'])) {
                $categories = json_encode($backenddata['data']);
            } else if (is_array($backenddata) && !isset($backenddata['success']) && !isset($backenddata['message'])) {
                // Backend returned raw array of categories.
                $categories = json_encode($backenddata);
            }

            return [
                'success' => $backenddata['success'] ?? true,
                'message' => $backenddata['message'] ?? '',
                'categories' => $categories,
                'category' => isset($backenddata['category']) ? json_encode($backenddata['category']) : '',
            ];
        } catch (\Exception $e) {
            debugging('[Adeptus Insights] Category management exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => false,
                'message' => get_string('error_managing_category', 'report_adeptus_insights', $e->getMessage()),
                'categories' => '[]',
                'category' => '',
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
            'categories' => new external_value(PARAM_RAW, 'JSON-encoded categories array (for list action)'),
            'category' => new external_value(PARAM_RAW, 'JSON-encoded category object (for create/update)'),
        ]);
    }
}

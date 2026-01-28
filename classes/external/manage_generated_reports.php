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
 * External service to manage generated wizard reports.
 *
 * Supported actions: remove_single, clear_all
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_generated_reports extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_TEXT, 'Action: remove_single or clear_all'),
            'slug' => new external_value(PARAM_TEXT, 'Report slug (for remove_single)', VALUE_DEFAULT, ''),
            'source' => new external_value(PARAM_TEXT, 'Report source: wizard or assistant', VALUE_DEFAULT, 'wizard'),
        ]);
    }

    /**
     * Manage generated reports.
     *
     * @param string $action Action to perform
     * @param string $slug Report slug
     * @param string $source Report source (wizard or assistant)
     * @return array Result
     */
    public static function execute(string $action, string $slug = '', string $source = 'wizard'): array {
        global $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'action' => $action,
            'slug' => $slug,
            'source' => $source,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            $userid = $USER->id;

            // Get API key and backend URL.
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
            $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();

            if (empty($apikey)) {
                return [
                    'success' => false,
                    'message' => get_string('error_api_key_not_configured', 'report_adeptus_insights'),
                    'deleted_count' => 0,
                ];
            }

            switch ($params['action']) {
                case 'remove_single':
                    if (empty($params['slug'])) {
                        return [
                            'success' => false,
                            'message' => get_string('error_slug_required', 'report_adeptus_insights'),
                            'deleted_count' => 0,
                        ];
                    }

                    if ($params['source'] === 'assistant') {
                        // Delete AI/assistant report from backend using DELETE method.
                        $curl = new \curl();
                        $curl->setHeader('Content-Type: application/json');
                        $curl->setHeader('Accept: application/json');
                        $curl->setHeader('Authorization: Bearer ' . $apikey);
                        $options = [
                            'CURLOPT_TIMEOUT' => 15,
                            'CURLOPT_SSL_VERIFYPEER' => true,
                            'CURLOPT_CUSTOMREQUEST' => 'DELETE',
                        ];
                        $response = $curl->get(
                            $backendapiurl . '/ai-reports/' . urlencode($params['slug']),
                            [],
                            $options
                        );
                        $info = $curl->get_info();
                        $httpcode = $info['http_code'] ?? 0;
                        $curlerror = $curl->get_errno() ? $curl->error : '';

                        if ($curlerror) {
                            return [
                                'success' => false,
                                'message' => get_string('error_connection', 'report_adeptus_insights', $curlerror),
                                'deleted_count' => 0,
                            ];
                        } else if ($httpcode === 200 || $httpcode === 204) {
                            $data = json_decode($response, true);
                            return [
                                'success' => true,
                                'message' => $data['message'] ?? get_string('ai_report_delete_success', 'report_adeptus_insights'),
                                'deleted_count' => 1,
                            ];
                        } else {
                            $data = json_decode($response, true);
                            return [
                                'success' => false,
                                'message' => $data['message'] ??
                                    get_string('ai_report_delete_failed', 'report_adeptus_insights', $httpcode),
                                'deleted_count' => 0,
                            ];
                        }
                    } else {
                        // Delete wizard report from backend.
                        $curl = new \curl();
                        $curl->setHeader('Content-Type: application/json');
                        $curl->setHeader('Accept: application/json');
                        $curl->setHeader('Authorization: Bearer ' . $apikey);
                        $options = [
                            'CURLOPT_TIMEOUT' => 10,
                            'CURLOPT_SSL_VERIFYPEER' => true,
                            'CURLOPT_CUSTOMREQUEST' => 'DELETE',
                        ];
                        $response = $curl->get(
                            $backendapiurl . '/wizard-reports/' . urlencode($params['slug']) . '?user_id=' . $userid,
                            [],
                            $options
                        );
                        $info = $curl->get_info();
                        $httpcode = $info['http_code'] ?? 0;

                        if ($httpcode === 200) {
                            $data = json_decode($response, true);
                            return [
                                'success' => true,
                                'message' => $data['message'] ?? get_string('report_removed', 'report_adeptus_insights'),
                                'deleted_count' => 1,
                            ];
                        } else {
                            $data = json_decode($response, true);
                            return [
                                'success' => false,
                                'message' => $data['message'] ?? get_string('report_remove_failed', 'report_adeptus_insights'),
                                'deleted_count' => 0,
                            ];
                        }
                    }

                case 'clear_all':
                    // Delete all wizard reports for the user from backend.
                    $curl = new \curl();
                    $curl->setHeader('Content-Type: application/json');
                    $curl->setHeader('Accept: application/json');
                    $curl->setHeader('Authorization: Bearer ' . $apikey);
                    $options = [
                        'CURLOPT_TIMEOUT' => 15,
                        'CURLOPT_SSL_VERIFYPEER' => true,
                        'CURLOPT_CUSTOMREQUEST' => 'DELETE',
                    ];
                    $postdata = json_encode(['user_id' => $userid]);
                    $response = $curl->post($backendapiurl . '/wizard-reports', $postdata, $options);
                    $info = $curl->get_info();
                    $httpcode = $info['http_code'] ?? 0;

                    if ($httpcode === 200) {
                        $data = json_decode($response, true);
                        $count = $data['deleted_count'] ?? 0;
                        return [
                            'success' => true,
                            'message' => get_string('reports_cleared', 'report_adeptus_insights', $count),
                            'deleted_count' => (int) $count,
                        ];
                    } else {
                        $data = json_decode($response, true);
                        return [
                            'success' => false,
                            'message' => $data['message'] ?? get_string('error_clear_failed', 'report_adeptus_insights'),
                            'deleted_count' => 0,
                        ];
                    }

                default:
                    return [
                        'success' => false,
                        'message' => get_string('error_invalid_action', 'report_adeptus_insights'),
                        'deleted_count' => 0,
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('error_managing_reports', 'report_adeptus_insights', $e->getMessage()),
                'deleted_count' => 0,
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
            'deleted_count' => new external_value(PARAM_INT, 'Number of reports deleted'),
        ]);
    }
}

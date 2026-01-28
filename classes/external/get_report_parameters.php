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
 * External API for getting report parameters with dynamic options.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_report_parameters extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'reportid' => new external_value(PARAM_TEXT, 'Report name/identifier'),
        ]);
    }

    /**
     * Get report parameters with dynamically populated options.
     *
     * @param string $reportid Report name/identifier.
     * @return array Report info and parameters with options.
     */
    public static function execute($reportid) {
        global $CFG, $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'reportid' => $reportid,
        ]);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability check.
        require_capability('report/adeptus_insights:view', $context);

        // Check if backend is enabled.
        $backendenabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
        if (!$backendenabled) {
            throw new \moodle_exception('error_backend_disabled', 'report_adeptus_insights');
        }

        // Get API configuration.
        $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();
        $apitimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;

        // Get API key for authentication.
        $installationmanager = new \report_adeptus_insights\installation_manager();
        $apikey = $installationmanager->get_api_key();

        // Fetch all reports from backend to find the requested one.
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');
        $curl->setHeader('X-API-Key: ' . $apikey);
        $options = [
            'CURLOPT_TIMEOUT' => $apitimeout,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        $response = $curl->get($backendapiurl . '/reports/definitions', [], $options);
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if (!$response || $httpcode !== 200) {
            throw new \moodle_exception('error_fetch_reports_failed', 'report_adeptus_insights');
        }

        $backenddata = json_decode($response, true);
        if (!$backenddata || !$backenddata['success']) {
            throw new \moodle_exception('error_invalid_backend_response', 'report_adeptus_insights');
        }

        // Find the report by ID (which is the report name).
        $report = null;
        foreach ($backenddata['data'] as $backendreport) {
            $backendname = trim($backendreport['name']);
            $requestedname = trim($params['reportid']);

            if ($backendname === $requestedname) {
                $report = $backendreport;
                break;
            }
        }

        if (!$report) {
            throw new \moodle_exception('error_report_not_found', 'report_adeptus_insights');
        }

        // Parse parameters from the report's parameters field.
        $parameters = [];
        if (!empty($report['parameters'])) {
            if (is_array($report['parameters'])) {
                foreach ($report['parameters'] as $key => $value) {
                    if (is_array($value) && isset($value['name'])) {
                        $parameters[] = $value;
                    }
                }
            }
        }

        // Enhance parameters with dynamic data based on type.
        foreach ($parameters as &$param) {
            self::process_parameter_locally($param, $DB);
        }

        // Format parameters for return.
        $formattedparams = [];
        foreach ($parameters as $param) {
            $formattedparam = [
                'name' => $param['name'] ?? '',
                'type' => $param['type'] ?? 'text',
                'label' => $param['label'] ?? '',
                'description' => $param['description'] ?? '',
                'required' => $param['required'] ?? true,
                'default_value' => isset($param['default']) ? (string) $param['default'] : '',
                'min' => isset($param['min']) ? (string) $param['min'] : '',
                'max' => isset($param['max']) ? (string) $param['max'] : '',
                'options' => [],
            ];

            // Format options if present.
            if (!empty($param['options']) && is_array($param['options'])) {
                foreach ($param['options'] as $opt) {
                    $formattedparam['options'][] = [
                        'value' => (string) ($opt['value'] ?? ''),
                        'label' => $opt['label'] ?? '',
                    ];
                }
            }

            $formattedparams[] = $formattedparam;
        }

        return [
            'success' => true,
            'report' => [
                'id' => $report['name'],
                'name' => $report['name'],
                'category' => $report['category'] ?? '',
                'description' => $report['description'] ?? '',
                'charttype' => $report['charttype'] ?? '',
            ],
            'parameters' => $formattedparams,
            'backend_enhanced' => false,
        ];
    }

    /**
     * Process parameter locally to populate dynamic options.
     *
     * @param array $param The parameter to process (passed by reference).
     * @param object $DB The database object.
     */
    private static function process_parameter_locally(&$param, $DB) {
        switch ($param['type']) {
            case 'course_select':
                $param['type'] = 'select';
                $courses = $DB->get_records_menu('course', ['visible' => 1], 'fullname ASC', 'id, fullname');
                $param['options'] = [];
                foreach ($courses as $id => $name) {
                    if ($id > 1) {
                        $param['options'][] = ['value' => $id, 'label' => s($name)];
                    }
                }
                break;

            case 'user_select':
                $param['type'] = 'select';
                $users = $DB->get_records_sql("
                    SELECT id, firstname, lastname, username
                    FROM {user}
                    WHERE deleted = 0 AND confirmed = 1
                    ORDER BY lastname, firstname
                    LIMIT 200
                ");
                $param['options'] = [];
                foreach ($users as $user) {
                    if ($user->id > 2) {
                        $param['options'][] = [
                            'value' => $user->id,
                            'label' => s($user->firstname . ' ' . $user->lastname) . ' (' . s($user->username) . ')',
                        ];
                    }
                }
                break;

            case 'category_select':
                $param['type'] = 'select';
                $categories = $DB->get_records('course_categories', null, 'name ASC', 'id, name, path, depth');
                $param['options'] = [];
                foreach ($categories as $category) {
                    $indent = str_repeat('— ', $category->depth);
                    $param['options'][] = [
                        'value' => $category->id,
                        'label' => $indent . s($category->name),
                    ];
                }
                break;

            case 'group_select':
                $param['type'] = 'select';
                $groups = $DB->get_records_sql("
                    SELECT g.id, g.name, c.fullname as coursename
                    FROM {groups} g
                    JOIN {course} c ON g.courseid = c.id
                    ORDER BY c.fullname, g.name
                ");
                $param['options'] = [];
                foreach ($groups as $group) {
                    $param['options'][] = [
                        'value' => $group->id,
                        'label' => s($group->coursename) . ' - ' . s($group->name),
                    ];
                }
                break;

            case 'role_select':
                $param['type'] = 'select';
                $roles = $DB->get_records('role', null, 'sortorder ASC', 'id, name, shortname');
                $param['options'] = [];
                foreach ($roles as $role) {
                    $param['options'][] = [
                        'value' => $role->id,
                        'label' => s($role->name) . ' (' . s($role->shortname) . ')',
                    ];
                }
                break;

            case 'module_select':
                $param['type'] = 'select';
                $modules = $DB->get_records('modules', ['visible' => 1], 'name ASC', 'id, name');
                $param['options'] = [];
                foreach ($modules as $module) {
                    $param['options'][] = [
                        'value' => $module->id,
                        'label' => ucfirst(s($module->name)),
                    ];
                }
                break;

            case 'quiz_select':
                $param['type'] = 'select';
                $quizzes = $DB->get_records_sql("
                    SELECT q.id, q.name, c.fullname as coursename, q.timeopen, q.timeclose
                    FROM {quiz} q
                    JOIN {course} c ON q.course = c.id
                    WHERE c.visible = 1
                    ORDER BY c.fullname, q.name
                ");
                $param['options'] = [];
                foreach ($quizzes as $quiz) {
                    $status = '';
                    if ($quiz->timeopen && $quiz->timeclose) {
                        $now = time();
                        if ($now < $quiz->timeopen) {
                            $status = ' [Not yet open]';
                        } else if ($now > $quiz->timeclose) {
                            $status = ' [Closed]';
                        } else {
                            $status = ' [Open]';
                        }
                    }
                    $param['options'][] = [
                        'value' => $quiz->id,
                        'label' => s($quiz->coursename) . ' - ' . s($quiz->name) . $status,
                    ];
                }
                break;

            case 'date':
                $param['type'] = 'date';
                if (!isset($param['default'])) {
                    $param['default'] = date('Y-m-d');
                }
                break;

            case 'number':
                $param['type'] = 'number';
                if (!isset($param['min'])) {
                    $param['min'] = 1;
                }
                if (!isset($param['default'])) {
                    $param['default'] = 10;
                }
                break;

            case 'text':
            default:
                $param['type'] = 'text';
                break;
        }

        // Ensure required fields are set.
        if (!isset($param['label'])) {
            $param['label'] = ucwords(str_replace(['_', 'id'], [' ', ' ID'], $param['name']));
        }

        if (!isset($param['description'])) {
            $param['description'] = "Enter the " . strtolower($param['label']);
        }

        if (!isset($param['required'])) {
            $param['required'] = true;
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'report' => new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Report identifier'),
                'name' => new external_value(PARAM_TEXT, 'Report name'),
                'category' => new external_value(PARAM_TEXT, 'Report category'),
                'description' => new external_value(PARAM_RAW, 'Report description'),
                'charttype' => new external_value(PARAM_ALPHANUMEXT, 'Default chart type'),
            ]),
            'parameters' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'Parameter name'),
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Parameter type'),
                    'label' => new external_value(PARAM_TEXT, 'Display label'),
                    'description' => new external_value(PARAM_TEXT, 'Parameter description'),
                    'required' => new external_value(PARAM_BOOL, 'Whether parameter is required'),
                    'default_value' => new external_value(PARAM_RAW, 'Default value'),
                    'min' => new external_value(PARAM_RAW, 'Minimum value for number type', VALUE_OPTIONAL),
                    'max' => new external_value(PARAM_RAW, 'Maximum value for number type', VALUE_OPTIONAL),
                    'options' => new external_multiple_structure(
                        new external_single_structure([
                            'value' => new external_value(PARAM_RAW, 'Option value'),
                            'label' => new external_value(PARAM_TEXT, 'Option label'),
                        ]),
                        'Options for select type',
                        VALUE_OPTIONAL
                    ),
                ]),
                'List of report parameters'
            ),
            'backend_enhanced' => new external_value(PARAM_BOOL, 'Whether backend enhancement was used'),
        ]);
    }
}

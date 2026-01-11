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
 * Get report parameters AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/api_config.php'); // Load API config

// Require login and capability
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/adeptus_insights:view', $context);

// Set content type
header('Content-Type: application/json');

// Get parameters
$reportid = required_param('reportid', PARAM_TEXT);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session key']);
    exit;
}

try {
    // Fetch the report from backend API
    $backendEnabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
    $backendApiUrl = \report_adeptus_insights\api_config::get_backend_url();
    $apiTimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;
    $debugMode = isset($CFG->adeptus_debug_mode) ? $CFG->adeptus_debug_mode : false;

    if (!$backendEnabled) {
        echo json_encode(['success' => false, 'message' => 'Backend API is disabled']);
        exit;
    }

    // Get API key for authentication
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installation_manager = new \report_adeptus_insights\installation_manager();
    $api_key = $installation_manager->get_api_key();

    // Fetch all reports from backend to find the requested one
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/reports/definitions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $apiTimeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . $api_key,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!$response || $httpCode !== 200 || !empty($curlError)) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch reports from backend']);
        exit;
    }

    $backendData = json_decode($response, true);
    if (!$backendData || !$backendData['success']) {
        echo json_encode(['success' => false, 'message' => 'Invalid response from backend']);
        exit;
    }

    // Find the report by ID (which is now the report name)
    $report = null;
    foreach ($backendData['data'] as $backendReport) {
        // Trim whitespace and normalize for comparison
        $backendName = trim($backendReport['name']);
        $requestedName = trim($reportid);

        if ($backendName === $requestedName) {
            $report = $backendReport;
            break;
        }
    }

    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit;
    }

    // Parse parameters from the report's parameters field
    $parameters = [];
    if (!empty($report['parameters'])) {
        if (is_array($report['parameters'])) {
            // Extract actual parameter definitions (arrays with 'name' key)
            // Skip metadata entries like 'charttype' which are scalar values
            foreach ($report['parameters'] as $key => $value) {
                // Check if this is a parameter definition (array with 'name' key)
                if (is_array($value) && isset($value['name'])) {
                    $parameters[] = $value;
                }
            }
        }
    }

    $fallbackEnabled = isset($CFG->adeptus_wizard_fallback_to_local) ? $CFG->adeptus_wizard_fallback_to_local : true;

    // Enhance parameters with dynamic data based on type
    // Only process if we have actual parameters (not just metadata)
    foreach ($parameters as &$param) {
        // Try to get parameter type mapping from backend first if enabled
        $enhancedParam = null;

        // Backend parameter enhancement disabled - endpoint not available in new backend
        if (false) {
            try {
                // Call backend API to get enhanced parameter data
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/adeptus-reports/process-parameter');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'paramName' => $param['name'],
                    'paramConfig' => [
                        'type' => $param['type'] ?? 'text',
                        'label' => $param['label'] ?? null,
                        'description' => $param['description'] ?? null,
                        'required' => $param['required'] ?? true,
                    ],
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, $apiTimeout);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($debugMode) {
                }

                if ($response && $httpCode === 200 && empty($curlError)) {
                    $backendData = json_decode($response, true);
                    if ($backendData && $backendData['success']) {
                        $enhancedParam = $backendData['data'];
                        if ($debugMode) {
                        }
                    }
                } else {
                    if ($debugMode) {
                    }
                }
            } catch (Exception $e) {
                // Silently continue - backend enhancement is optional.
            }
        }

        // If backend enhancement succeeded, use it; otherwise fall back to local processing
        if ($enhancedParam) {
            $param = array_merge($param, $enhancedParam);
        }

        // Local fallback parameter processing (always available as backup)
        processParameterLocally($param);
    }

    // Convert parameters from associative array to numeric array for JavaScript
    $param_array = array_values($parameters);

    // Return success response
    echo json_encode([
        'success' => true,
        'report' => [
            'id' => $report['name'], // Use name as ID
            'name' => $report['name'],
            'category' => $report['category'],
            'description' => $report['description'],
            'charttype' => $report['charttype'],
        ],
        'parameters' => $param_array,
        'backend_enhanced' => $backendEnabled && !empty($enhancedParam),
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}

/**
 * Process parameter locally as fallback
 */
function processParameterLocally(&$param) {
    global $DB;

    switch ($param['type']) {
        case 'course_select':
            $param['type'] = 'select';
            $courses = $DB->get_records_menu('course', ['visible' => 1], 'fullname ASC', 'id, fullname');
            $param['options'] = [];
            foreach ($courses as $id => $name) {
                if ($id > 1) { // Skip site course
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
                if ($user->id > 2) { // Skip guest and admin
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

        case 'activity_select':
            $param['type'] = 'select';
            // Get recent activities across all courses
            $activities = $DB->get_records_sql("
                SELECT cm.id, cm.course, m.name as modulename, 
                       COALESCE(a.name, r.name, f.name, q.name, p.name) as activityname,
                       c.fullname as coursename
                FROM {course_modules} cm
                JOIN {modules} m ON cm.module = m.id
                JOIN {course} c ON cm.course = c.id
                LEFT JOIN {assign} a ON cm.instance = a.id AND m.name = 'assign'
                LEFT JOIN {resource} r ON cm.instance = r.id AND m.name = 'resource'
                LEFT JOIN {forum} f ON cm.instance = f.id AND m.name = 'forum'
                LEFT JOIN {quiz} q ON cm.instance = q.id AND m.name = 'quiz'
                LEFT JOIN {page} p ON cm.instance = p.id AND m.name = 'page'
                WHERE cm.visible = 1
                ORDER BY c.fullname, m.name, activityname
                LIMIT 100
            ");
            $param['options'] = [];
            foreach ($activities as $activity) {
                $param['options'][] = [
                    'value' => $activity->id,
                    'label' => s($activity->coursename) . ' - ' .
                               ucfirst($activity->modulename) . ': ' .
                               s($activity->activityname),
                ];
            }
            break;

        case 'coursemodule_select':
            $param['type'] = 'select';
            $coursemodules = $DB->get_records_sql("
                SELECT cm.id, c.fullname as coursename, m.name as modulename
                FROM {course_modules} cm
                JOIN {course} c ON cm.course = c.id
                JOIN {modules} m ON cm.module = m.id
                WHERE cm.visible = 1
                ORDER BY c.fullname, m.name
                LIMIT 100
            ");
            $param['options'] = [];
            foreach ($coursemodules as $cm) {
                $param['options'][] = [
                    'value' => $cm->id,
                    'label' => s($cm->coursename) . ' - ' . ucfirst($cm->modulename),
                ];
            }
            break;

        case 'grade_select':
            $param['type'] = 'select';
            $gradeitems = $DB->get_records_sql("
                SELECT gi.id, gi.itemname, c.fullname as coursename
                FROM {grade_items} gi
                JOIN {course} c ON gi.courseid = c.id
                WHERE gi.itemtype IN ('manual', 'mod')
                ORDER BY c.fullname, gi.itemname
                LIMIT 100
            ");
            $param['options'] = [];
            foreach ($gradeitems as $item) {
                $param['options'][] = [
                    'value' => $item->id,
                    'label' => s($item->coursename) . ' - ' . s($item->itemname),
                ];
            }
            break;

        case 'gradeitem_select':
            $param['type'] = 'select';
            $gradeitems = $DB->get_records_sql("
                SELECT gi.id, gi.itemname, c.fullname as coursename, gi.itemtype
                FROM {grade_items} gi
                JOIN {course} c ON gi.courseid = c.id
                WHERE gi.itemtype IN ('course', 'category', 'mod', 'manual')
                ORDER BY c.fullname, gi.itemtype, gi.itemname
                LIMIT 100
            ");
            $param['options'] = [];
            foreach ($gradeitems as $item) {
                $typeLabel = ucfirst($item->itemtype);
                $param['options'][] = [
                    'value' => $item->id,
                    'label' => s($item->coursename) . ' - ' . $typeLabel . ': ' . s($item->itemname),
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
            // Set default to today if not specified
            if (!isset($param['default'])) {
                $param['default'] = date('Y-m-d');
            }
            break;

        case 'number':
            $param['type'] = 'number';
            // Set reasonable defaults if not specified
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

    // Ensure required fields are set
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

exit;

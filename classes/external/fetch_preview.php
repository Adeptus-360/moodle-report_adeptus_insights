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
 * External API for fetching analytics preview data.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetch_preview extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'question' => new external_value(PARAM_ALPHA, 'The analytics question type'),
            'fields' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Field name'),
                'List of fields to retrieve',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Fetch preview data from the analytics table.
     *
     * @param string $question The analytics question type.
     * @param array $fields List of fields to retrieve.
     * @return array Preview data results.
     */
    public static function execute($question, $fields = []) {
        global $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'question' => $question,
            'fields' => $fields,
        ]);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability check.
        require_capability('moodle/site:viewreports', $context);

        // Validate fields.
        $allowedfields = ['logins', 'assignments_submitted', 'forum_posts', 'average_grade',
            'resource_clicks', 'last_login', 'userid', 'courseid'];

        if (empty($params['fields'])) {
            throw new \moodle_exception('error_no_fields_selected', 'report_adeptus_insights');
        }

        $validfields = array_intersect($params['fields'], $allowedfields);
        if (empty($validfields)) {
            throw new \moodle_exception('error_invalid_fields', 'report_adeptus_insights');
        }

        // Build and execute query.
        $fieldlist = implode(', ', $validfields);
        $sql = "SELECT id, {$fieldlist}
                FROM {report_adeptus_insights_analytics}
                ORDER BY timemodified DESC";
        $results = $DB->get_records_sql($sql, [], 0, 20);

        // Format results for return.
        $data = [];
        foreach ($results as $row) {
            $item = ['id' => (int) $row->id];
            foreach ($validfields as $field) {
                if (isset($row->$field)) {
                    $item[$field] = $row->$field;
                }
            }
            $data[] = $item;
        }

        return [
            'success' => true,
            'data' => $data,
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
            'data' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Record ID'),
                    'userid' => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL),
                    'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_OPTIONAL),
                    'logins' => new external_value(PARAM_INT, 'Number of logins', VALUE_OPTIONAL),
                    'assignments_submitted' => new external_value(PARAM_INT, 'Assignments submitted', VALUE_OPTIONAL),
                    'forum_posts' => new external_value(PARAM_INT, 'Forum posts', VALUE_OPTIONAL),
                    'resource_clicks' => new external_value(PARAM_INT, 'Resource clicks', VALUE_OPTIONAL),
                    'last_login' => new external_value(PARAM_INT, 'Last login timestamp', VALUE_OPTIONAL),
                    'average_grade' => new external_value(PARAM_FLOAT, 'Average grade', VALUE_OPTIONAL),
                ]),
                'Preview data records'
            ),
        ]);
    }
}

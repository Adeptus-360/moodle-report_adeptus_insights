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

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * External API for fetching students by course IDs.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetch_students extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course ID'),
                'List of course IDs to fetch students from'
            ),
        ]);
    }

    /**
     * Fetch students enrolled in the specified courses.
     *
     * @param array $courseids List of course IDs.
     * @return array Students data.
     */
    public static function execute($courseids) {
        global $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseids' => $courseids,
        ]);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability check - must be able to view reports.
        require_capability('report/adeptus_insights:view', $context);

        if (empty($params['courseids'])) {
            return [
                'success' => true,
                'students' => [],
            ];
        }

        // Build query to get students enrolled in the specified courses.
        // Students are users with the student role (roleid = 5 by default).
        list($insql, $inparams) = $DB->get_in_or_equal($params['courseids'], SQL_PARAMS_NAMED);

        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextlevel
                JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id
                JOIN {role} r ON r.id = ra.roleid
                WHERE e.courseid {$insql}
                AND u.deleted = 0
                AND u.suspended = 0
                AND ue.status = 0
                AND r.shortname = 'student'
                ORDER BY u.lastname, u.firstname";

        $inparams['contextlevel'] = CONTEXT_COURSE;
        $students = $DB->get_records_sql($sql, $inparams);

        // Format results.
        $data = [];
        foreach ($students as $student) {
            $data[] = [
                'id' => (int) $student->id,
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'fullname' => fullname($student),
                'email' => $student->email,
            ];
        }

        return [
            'success' => true,
            'students' => $data,
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
            'students' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'User ID'),
                    'firstname' => new external_value(PARAM_TEXT, 'First name'),
                    'lastname' => new external_value(PARAM_TEXT, 'Last name'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                    'email' => new external_value(PARAM_EMAIL, 'Email address'),
                ]),
                'List of students'
            ),
        ]);
    }
}

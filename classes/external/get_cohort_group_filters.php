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
 * External API for fetching cohort and group filter options.
 *
 * Returns cohorts and groups visible to the current user, respecting
 * Moodle capabilities and context permissions.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_cohort_group_filters extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Fetch cohorts and groups the current user has access to.
     *
     * @return array Cohorts and groups data.
     */
    public static function execute() {
        global $DB, $USER;

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        $cohorts = self::get_visible_cohorts($context);
        $groups = self::get_visible_groups($context);

        return [
            'cohorts' => $cohorts,
            'groups' => $groups,
        ];
    }

    /**
     * Get cohorts visible to the current user.
     *
     * @param \context $systemcontext The system context.
     * @return array Array of cohort data.
     */
    private static function get_visible_cohorts(\context $systemcontext) {
        global $DB, $USER;

        $cohorts = [];

        // Site admins and users with moodle/cohort:view at system level see all cohorts.
        if (has_capability('moodle/cohort:view', $systemcontext)) {
            $records = $DB->get_records('cohort', null, 'name ASC', 'id, name, idnumber, description, contextid');
            foreach ($records as $record) {
                $membercount = $DB->count_records('cohort_members', ['cohortid' => $record->id]);
                $cohorts[] = [
                    'id' => (int) $record->id,
                    'name' => $record->name,
                    'idnumber' => $record->idnumber ?? '',
                    'membercount' => $membercount,
                ];
            }
            return $cohorts;
        }

        // Otherwise, check category contexts the user can access.
        $categories = $DB->get_records('course_categories', null, 'name ASC', 'id, name');
        foreach ($categories as $category) {
            $catcontext = \context_coursecat::instance($category->id, IGNORE_MISSING);
            if ($catcontext && has_capability('moodle/cohort:view', $catcontext)) {
                $records = $DB->get_records('cohort', ['contextid' => $catcontext->id], 'name ASC',
                    'id, name, idnumber, description, contextid');
                foreach ($records as $record) {
                    $membercount = $DB->count_records('cohort_members', ['cohortid' => $record->id]);
                    $cohorts[] = [
                        'id' => (int) $record->id,
                        'name' => $record->name,
                        'idnumber' => $record->idnumber ?? '',
                        'membercount' => $membercount,
                    ];
                }
            }
        }

        return $cohorts;
    }

    /**
     * Get groups visible to the current user.
     *
     * @param \context $systemcontext The system context.
     * @return array Array of group data with course context.
     */
    private static function get_visible_groups(\context $systemcontext) {
        global $DB, $USER;

        $groups = [];

        // Site admins see all groups.
        if (has_capability('moodle/site:accessallgroups', $systemcontext)) {
            $sql = "SELECT g.id, g.name, g.courseid, g.idnumber, c.fullname AS coursename
                      FROM {groups} g
                      JOIN {course} c ON c.id = g.courseid
                     ORDER BY c.fullname ASC, g.name ASC";
            $records = $DB->get_records_sql($sql);
            foreach ($records as $record) {
                $membercount = $DB->count_records('groups_members', ['groupid' => $record->id]);
                $groups[] = [
                    'id' => (int) $record->id,
                    'name' => $record->name,
                    'courseid' => (int) $record->courseid,
                    'coursename' => $record->coursename,
                    'idnumber' => $record->idnumber ?? '',
                    'membercount' => $membercount,
                ];
            }
            return $groups;
        }

        // Otherwise, show groups from courses the user is enrolled in.
        $courses = enrol_get_all_users_courses($USER->id, true, 'id, fullname');
        foreach ($courses as $course) {
            $coursecontext = \context_course::instance($course->id, IGNORE_MISSING);
            if (!$coursecontext) {
                continue;
            }

            $canviewall = has_capability('moodle/site:accessallgroups', $coursecontext);
            if ($canviewall) {
                $coursegroups = $DB->get_records('groups', ['courseid' => $course->id], 'name ASC',
                    'id, name, courseid, idnumber');
            } else {
                // Only groups the user belongs to.
                $sql = "SELECT g.id, g.name, g.courseid, g.idnumber
                          FROM {groups} g
                          JOIN {groups_members} gm ON gm.groupid = g.id
                         WHERE g.courseid = ? AND gm.userid = ?
                         ORDER BY g.name ASC";
                $coursegroups = $DB->get_records_sql($sql, [$course->id, $USER->id]);
            }

            foreach ($coursegroups as $g) {
                $membercount = $DB->count_records('groups_members', ['groupid' => $g->id]);
                $groups[] = [
                    'id' => (int) $g->id,
                    'name' => $g->name,
                    'courseid' => (int) $g->courseid,
                    'coursename' => $course->fullname,
                    'idnumber' => $g->idnumber ?? '',
                    'membercount' => $membercount,
                ];
            }
        }

        return $groups;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'cohorts' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Cohort ID'),
                    'name' => new external_value(PARAM_TEXT, 'Cohort name'),
                    'idnumber' => new external_value(PARAM_TEXT, 'Cohort ID number'),
                    'membercount' => new external_value(PARAM_INT, 'Number of members'),
                ]),
                'Available cohorts'
            ),
            'groups' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Group ID'),
                    'name' => new external_value(PARAM_TEXT, 'Group name'),
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'coursename' => new external_value(PARAM_TEXT, 'Course name'),
                    'idnumber' => new external_value(PARAM_TEXT, 'Group ID number'),
                    'membercount' => new external_value(PARAM_INT, 'Number of members'),
                ]),
                'Available groups'
            ),
        ]);
    }
}

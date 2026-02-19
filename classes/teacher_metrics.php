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

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Teacher metrics helper for generating teacher-specific SQL queries.
 *
 * Provides reusable SQL generation methods for teacher performance reports
 * including completion rates, engagement, grading timeliness, and course load.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_metrics {

    /** @var int Role ID for editing teacher. */
    const ROLE_EDITING_TEACHER = 3;

    /** @var int Role ID for non-editing teacher. */
    const ROLE_TEACHER = 4;

    /** @var int Role ID for student. */
    const ROLE_STUDENT = 5;

    /** @var int Context level for courses. */
    const CONTEXT_COURSE = 50;

    /**
     * Get a SQL subquery that identifies teachers and their courses.
     *
     * Returns (userid, courseid, firstname, lastname, email) for all users
     * with editing teacher or non-editing teacher roles.
     *
     * @param string $prefix Table prefix placeholder.
     * @return string SQL subquery.
     */
    public static function get_teacher_courses_sql(string $prefix = 'prefix_'): string {
        $editingteacher = self::ROLE_EDITING_TEACHER;
        $teacher = self::ROLE_TEACHER;
        $ctxcourse = self::CONTEXT_COURSE;
        return "
            SELECT DISTINCT
                u.id AS teacherid,
                u.firstname,
                u.lastname,
                u.email,
                ctx.instanceid AS courseid
            FROM {$prefix}user u
            INNER JOIN {$prefix}role_assignments ra ON ra.userid = u.id
            INNER JOIN {$prefix}context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = {$ctxcourse}
            WHERE ra.roleid IN ({$editingteacher}, {$teacher})
              AND u.deleted = 0
              AND u.suspended = 0
        ";
    }

    /**
     * Get SQL for course completion rates by teacher.
     *
     * @param string $prefix Table prefix placeholder.
     * @return string Complete SQL query.
     */
    public static function get_completion_rates_sql(string $prefix = 'prefix_'): string {
        $student = self::ROLE_STUDENT;
        $ctxcourse = self::CONTEXT_COURSE;
        return "
            SELECT
                tc.teacherid,
                tc.firstname AS \"Teacher Firstname\",
                tc.lastname AS \"Teacher Lastname\",
                tc.email AS \"Teacher Email\",
                c.fullname AS \"Course\",
                COUNT(DISTINCT sra.userid) AS \"Enrolled Students\",
                COUNT(DISTINCT cc.userid) AS \"Completed Students\",
                CASE
                    WHEN COUNT(DISTINCT sra.userid) > 0
                    THEN ROUND(COUNT(DISTINCT cc.userid) * 100.0 / COUNT(DISTINCT sra.userid), 1)
                    ELSE 0
                END AS \"Completion Rate (%)\"
            FROM (
                " . self::get_teacher_courses_sql($prefix) . "
            ) tc
            INNER JOIN {$prefix}course c ON c.id = tc.courseid
            LEFT JOIN {$prefix}role_assignments sra
                ON sra.contextid = (
                    SELECT id FROM {$prefix}context
                    WHERE instanceid = tc.courseid AND contextlevel = {$ctxcourse}
                )
                AND sra.roleid = {$student}
            LEFT JOIN {$prefix}user su ON su.id = sra.userid AND su.deleted = 0
            LEFT JOIN {$prefix}course_completions cc
                ON cc.course = tc.courseid
                AND cc.userid = sra.userid
                AND cc.timecompleted IS NOT NULL
            GROUP BY tc.teacherid, tc.firstname, tc.lastname, tc.email, c.id, c.fullname
            ORDER BY \"Completion Rate (%)\" DESC
        ";
    }

    /**
     * Get SQL for student engagement metrics by teacher.
     *
     * Measures average log entries and activity completions per student
     * in each teacher's courses over the last 30 days.
     *
     * @param string $prefix Table prefix placeholder.
     * @return string Complete SQL query.
     */
    public static function get_engagement_sql(string $prefix = 'prefix_'): string {
        $student = self::ROLE_STUDENT;
        $ctxcourse = self::CONTEXT_COURSE;
        return "
            SELECT
                tc.firstname AS \"Teacher Firstname\",
                tc.lastname AS \"Teacher Lastname\",
                tc.email AS \"Teacher Email\",
                c.fullname AS \"Course\",
                COUNT(DISTINCT sra.userid) AS \"Enrolled Students\",
                COALESCE(logs.total_actions, 0) AS \"Total Student Actions (30d)\",
                CASE
                    WHEN COUNT(DISTINCT sra.userid) > 0
                    THEN ROUND(COALESCE(logs.total_actions, 0) / COUNT(DISTINCT sra.userid), 1)
                    ELSE 0
                END AS \"Avg Actions per Student\",
                COALESCE(cmc.completions, 0) AS \"Activity Completions (30d)\",
                CASE
                    WHEN COUNT(DISTINCT sra.userid) > 0
                    THEN ROUND(COALESCE(cmc.completions, 0) / COUNT(DISTINCT sra.userid), 1)
                    ELSE 0
                END AS \"Avg Completions per Student\"
            FROM (
                " . self::get_teacher_courses_sql($prefix) . "
            ) tc
            INNER JOIN {$prefix}course c ON c.id = tc.courseid
            LEFT JOIN {$prefix}role_assignments sra
                ON sra.contextid = (
                    SELECT id FROM {$prefix}context
                    WHERE instanceid = tc.courseid AND contextlevel = {$ctxcourse}
                )
                AND sra.roleid = {$student}
            LEFT JOIN (
                SELECT courseid, COUNT(*) AS total_actions
                FROM {$prefix}logstore_standard_log
                WHERE timecreated >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
                  AND userid > 0 AND anonymous = 0
                GROUP BY courseid
            ) logs ON logs.courseid = tc.courseid
            LEFT JOIN (
                SELECT cm.course AS courseid, COUNT(*) AS completions
                FROM {$prefix}course_modules_completion cmc2
                INNER JOIN {$prefix}course_modules cm ON cm.id = cmc2.coursemoduleid
                WHERE cmc2.completionstate > 0
                  AND cmc2.timemodified >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
                GROUP BY cm.course
            ) cmc ON cmc.courseid = tc.courseid
            GROUP BY tc.teacherid, tc.firstname, tc.lastname, tc.email,
                     c.id, c.fullname, logs.total_actions, cmc.completions
            ORDER BY \"Avg Actions per Student\" DESC
        ";
    }

    /**
     * Get SQL for grading timeliness by teacher.
     *
     * Calculates average time from assignment submission to grade, per teacher.
     *
     * @param string $prefix Table prefix placeholder.
     * @return string Complete SQL query.
     */
    public static function get_grading_timeliness_sql(string $prefix = 'prefix_'): string {
        return "
            SELECT
                tc.firstname AS \"Teacher Firstname\",
                tc.lastname AS \"Teacher Lastname\",
                tc.email AS \"Teacher Email\",
                c.fullname AS \"Course\",
                a.name AS \"Assignment\",
                COUNT(DISTINCT ag.id) AS \"Graded Submissions\",
                ROUND(AVG(
                    CASE
                        WHEN ag.timemodified > 0 AND asub.timemodified > 0
                            AND ag.timemodified >= asub.timemodified
                        THEN (ag.timemodified - asub.timemodified) / 3600.0
                        ELSE NULL
                    END
                ), 1) AS \"Avg Hours to Grade\",
                ROUND(MIN(
                    CASE
                        WHEN ag.timemodified > 0 AND asub.timemodified > 0
                            AND ag.timemodified >= asub.timemodified
                        THEN (ag.timemodified - asub.timemodified) / 3600.0
                        ELSE NULL
                    END
                ), 1) AS \"Min Hours to Grade\",
                ROUND(MAX(
                    CASE
                        WHEN ag.timemodified > 0 AND asub.timemodified > 0
                            AND ag.timemodified >= asub.timemodified
                        THEN (ag.timemodified - asub.timemodified) / 3600.0
                        ELSE NULL
                    END
                ), 1) AS \"Max Hours to Grade\"
            FROM (
                " . self::get_teacher_courses_sql('prefix_') . "
            ) tc
            INNER JOIN {$prefix}course c ON c.id = tc.courseid
            INNER JOIN {$prefix}assign a ON a.course = tc.courseid
            INNER JOIN {$prefix}assign_submission asub ON asub.assignment = a.id
                AND asub.status = 'submitted'
            INNER JOIN {$prefix}assign_grades ag ON ag.assignment = a.id
                AND ag.userid = asub.userid
                AND ag.grade >= 0
            GROUP BY tc.teacherid, tc.firstname, tc.lastname, tc.email,
                     c.id, c.fullname, a.id, a.name
            ORDER BY \"Avg Hours to Grade\" ASC
        ";
    }

    /**
     * Get SQL for teacher course load summary.
     *
     * Shows number of courses, total enrolled students per teacher.
     *
     * @param string $prefix Table prefix placeholder.
     * @return string Complete SQL query.
     */
    public static function get_course_load_sql(string $prefix = 'prefix_'): string {
        $student = self::ROLE_STUDENT;
        $ctxcourse = self::CONTEXT_COURSE;
        return "
            SELECT
                tc.firstname AS \"Teacher Firstname\",
                tc.lastname AS \"Teacher Lastname\",
                tc.email AS \"Teacher Email\",
                COUNT(DISTINCT tc.courseid) AS \"Number of Courses\",
                COUNT(DISTINCT sra.userid) AS \"Total Enrolled Students\",
                CASE
                    WHEN COUNT(DISTINCT tc.courseid) > 0
                    THEN ROUND(COUNT(DISTINCT sra.userid) / COUNT(DISTINCT tc.courseid), 1)
                    ELSE 0
                END AS \"Avg Students per Course\"
            FROM (
                " . self::get_teacher_courses_sql($prefix) . "
            ) tc
            LEFT JOIN {$prefix}role_assignments sra
                ON sra.contextid = (
                    SELECT id FROM {$prefix}context
                    WHERE instanceid = tc.courseid AND contextlevel = {$ctxcourse}
                )
                AND sra.roleid = {$student}
            LEFT JOIN {$prefix}user su ON su.id = sra.userid AND su.deleted = 0
            GROUP BY tc.teacherid, tc.firstname, tc.lastname, tc.email
            ORDER BY \"Number of Courses\" DESC, \"Total Enrolled Students\" DESC
        ";
    }
}

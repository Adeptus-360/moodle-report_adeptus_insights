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

namespace report_adeptus_insights\task;
/**
 * Scheduled task to build the analytics base table.
 *
 * This task aggregates user-course interaction data from Moodle core tables
 * into the report_adeptus_insights_analytics materialized table for faster
 * reporting and analytics queries.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class build_analytics_base extends \core\task\scheduled_task {
    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_build_analytics_base', 'report_adeptus_insights');
    }

    /**
     * Execute the task.
     *
     * Aggregates data from Moodle core tables into the analytics base table:
     * - Logins from logstore
     * - Assignment submissions
     * - Forum posts
     * - Resource/activity views
     * - Average grades
     */
    public function execute() {
        global $DB;

        mtrace('Starting analytics base table build...');

        $starttime = time();

        // Get all active user-course combinations from enrollments.
        $sql = "SELECT DISTINCT ue.userid, e.courseid
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {user} u ON u.id = ue.userid
                JOIN {course} c ON c.id = e.courseid
                WHERE ue.status = 0
                AND u.deleted = 0
                AND u.suspended = 0
                AND c.visible = 1";

        $enrollments = $DB->get_records_sql($sql);
        $totalenrollments = count($enrollments);

        mtrace("Found {$totalenrollments} active user-course enrollments to process.");

        $processed = 0;
        $errors = 0;

        foreach ($enrollments as $enrollment) {
            try {
                $this->process_user_course($enrollment->userid, $enrollment->courseid);
                $processed++;

                // Progress output every 1000 records.
                if ($processed % 1000 === 0) {
                    mtrace("Processed {$processed} of {$totalenrollments} enrollments...");
                }
            } catch (\Exception $e) {
                $errors++;
                mtrace("Error processing user {$enrollment->userid} in course {$enrollment->courseid}: " .
                    $e->getMessage());
            }
        }

        $duration = time() - $starttime;
        mtrace("Analytics base table build completed in {$duration} seconds.");
        mtrace("Processed: {$processed}, Errors: {$errors}");
    }

    /**
     * Process analytics data for a single user-course combination.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     */
    protected function process_user_course($userid, $courseid) {
        global $DB;

        $now = time();

        // Collect all metrics for this user-course combination.
        $data = new \stdClass();
        $data->userid = $userid;
        $data->courseid = $courseid;
        $data->logins = $this->count_logins($userid, $courseid);
        $data->assignments_submitted = $this->count_assignments_submitted($userid, $courseid);
        $data->forum_posts = $this->count_forum_posts($userid, $courseid);
        $data->resource_clicks = $this->count_resource_clicks($userid, $courseid);
        $data->last_login = $this->get_last_login($userid, $courseid);
        $data->average_grade = $this->get_average_grade($userid, $courseid);
        $data->timemodified = $now;

        // Check if record exists.
        $existing = $DB->get_record('report_adeptus_insights_analytics', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if ($existing) {
            $data->id = $existing->id;
            $DB->update_record('report_adeptus_insights_analytics', $data);
        } else {
            $data->timecreated = $now;
            $DB->insert_record('report_adeptus_insights_analytics', $data);
        }
    }

    /**
     * Count login events for a user in a course context.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return int Number of logins.
     */
    protected function count_logins($userid, $courseid) {
        global $DB;

        // Check if standard log store is available.
        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as cnt
                FROM {logstore_standard_log}
                WHERE userid = :userid
                AND courseid = :courseid
                AND eventname = :eventname";

        $count = $DB->get_field_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'eventname' => '\core\event\course_viewed',
        ]);

        return (int) $count;
    }

    /**
     * Count assignment submissions for a user in a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return int Number of submissions.
     */
    protected function count_assignments_submitted($userid, $courseid) {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT s.id)
                FROM {assign_submission} s
                JOIN {assign} a ON a.id = s.assignment
                WHERE s.userid = :userid
                AND a.course = :courseid
                AND s.status = 'submitted'";

        $count = $DB->get_field_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        return (int) $count;
    }

    /**
     * Count forum posts for a user in a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return int Number of forum posts.
     */
    protected function count_forum_posts($userid, $courseid) {
        global $DB;

        $sql = "SELECT COUNT(p.id)
                FROM {forum_posts} p
                JOIN {forum_discussions} d ON d.id = p.discussion
                JOIN {forum} f ON f.id = d.forum
                WHERE p.userid = :userid
                AND f.course = :courseid";

        $count = $DB->get_field_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        return (int) $count;
    }

    /**
     * Count resource/activity views for a user in a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return int Number of resource clicks.
     */
    protected function count_resource_clicks($userid, $courseid) {
        global $DB;

        // Check if standard log store is available.
        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as cnt
                FROM {logstore_standard_log}
                WHERE userid = :userid
                AND courseid = :courseid
                AND action = 'viewed'
                AND target IN ('course_module', 'resource', 'page', 'url', 'book', 'folder')";

        $count = $DB->get_field_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        return (int) $count;
    }

    /**
     * Get the last login timestamp for a user in a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return int Unix timestamp of last login, or 0 if never.
     */
    protected function get_last_login($userid, $courseid) {
        global $DB;

        // Use user_lastaccess table for course-specific last access.
        $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        return (int) $lastaccess;
    }

    /**
     * Get the average grade for a user in a course.
     *
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @return float|null Average grade percentage, or null if no grades.
     */
    protected function get_average_grade($userid, $courseid) {
        global $DB;

        $sql = "SELECT AVG(
                    CASE
                        WHEN gi.grademax > gi.grademin
                        THEN ((gg.finalgrade - gi.grademin) / (gi.grademax - gi.grademin)) * 100
                        ELSE NULL
                    END
                ) as avggrade
                FROM {grade_grades} gg
                JOIN {grade_items} gi ON gi.id = gg.itemid
                WHERE gg.userid = :userid
                AND gi.courseid = :courseid
                AND gg.finalgrade IS NOT NULL
                AND gi.itemtype != 'course'";

        $avggrade = $DB->get_field_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        return $avggrade !== false ? (float) $avggrade : null;
    }
}

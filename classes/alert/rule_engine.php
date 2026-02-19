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
 * Rule-based alert engine — evaluates admin-defined rules and logs matching users.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights\alert;

defined('MOODLE_INTERNAL') || die();

/**
 * Evaluates alert rules against Moodle data and logs triggered alerts.
 */
class rule_engine {

    /** @var int Deduplication window in seconds (7 days). */
    const DEDUP_WINDOW = 604800;

    /**
     * Evaluate all enabled rules and log alerts.
     *
     * @return int Number of new alert log entries created.
     */
    public static function evaluate_all(): int {
        global $DB;

        $rules = $DB->get_records('report_adeptus_alert_rules', ['enabled' => 1]);
        $count = 0;

        foreach ($rules as $rule) {
            $matches = self::evaluate_rule($rule);
            foreach ($matches as $match) {
                if (self::is_duplicate($rule->id, $match->userid, $match->courseid)) {
                    continue;
                }
                $log = new \stdClass();
                $log->rule_id = $rule->id;
                $log->user_id = $match->userid;
                $log->course_id = $match->courseid;
                $log->triggered_value = $match->value;
                $log->notified = 0;
                $log->timecreated = time();
                $DB->insert_record('report_adeptus_alert_logs', $log);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Evaluate a single rule and return matching user/course pairs.
     *
     * @param \stdClass $rule The rule record.
     * @return array Array of objects with userid, courseid, value properties.
     */
    protected static function evaluate_rule(\stdClass $rule): array {
        switch ($rule->rule_type) {
            case 'grade_below':
                return self::evaluate_grade_below($rule);
            case 'completion_stalled':
                return self::evaluate_completion_stalled($rule);
            case 'inactive_days':
                return self::evaluate_inactive_days($rule);
            case 'login_gap':
                return self::evaluate_login_gap($rule);
            default:
                return [];
        }
    }

    /**
     * Find users whose course grade is below the threshold.
     *
     * @param \stdClass $rule The rule record.
     * @return array Matching records.
     */
    protected static function evaluate_grade_below(\stdClass $rule): array {
        global $DB;

        $params = ['threshold' => $rule->threshold];
        $coursewhere = '';
        if (!empty($rule->course_id)) {
            $coursewhere = ' AND gg.courseid = :courseid';
            $params['courseid'] = $rule->course_id;
        }

        $sql = "SELECT gg.userid, gg.courseid, gg.finalgrade AS value
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.itemtype = 'course'
                   AND gg.finalgrade IS NOT NULL
                   AND gg.finalgrade < :threshold
                   {$coursewhere}";

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Find users with no completion progress in X days.
     *
     * @param \stdClass $rule The rule record (threshold = days).
     * @return array Matching records.
     */
    protected static function evaluate_completion_stalled(\stdClass $rule): array {
        global $DB;

        $cutoff = time() - ((int)$rule->threshold * DAYSECS);
        $params = ['cutoff' => $cutoff];
        $coursewhere = '';
        if (!empty($rule->course_id)) {
            $coursewhere = ' AND cc.course = :courseid';
            $params['courseid'] = $rule->course_id;
        }

        // Users enrolled in courses with completion tracking who haven't completed any
        // activity since the cutoff.
        $sql = "SELECT cc.userid, cc.course AS courseid,
                       COALESCE(MAX(cmc.timemodified), 0) AS value
                  FROM {course_completions} cc
             LEFT JOIN {course_modules_completion} cmc
                       ON cmc.userid = cc.userid
                       AND cmc.completionstate > 0
                 WHERE (cc.timecompleted IS NULL OR cc.timecompleted = 0)
                   {$coursewhere}
              GROUP BY cc.userid, cc.course
                HAVING COALESCE(MAX(cmc.timemodified), 0) < :cutoff";

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Find users with no activity (log actions) in X days.
     *
     * @param \stdClass $rule The rule record (threshold = days).
     * @return array Matching records.
     */
    protected static function evaluate_inactive_days(\stdClass $rule): array {
        global $DB;

        $cutoff = time() - ((int)$rule->threshold * DAYSECS);
        $params = ['cutoff' => $cutoff];
        $coursewhere = '';
        if (!empty($rule->course_id)) {
            $coursewhere = ' AND ue_course.id = :courseid';
            $params['courseid'] = $rule->course_id;
        }

        // Find enrolled users whose last log action in the course is before the cutoff.
        $sql = "SELECT ue.userid, e.courseid AS courseid,
                       COALESCE(MAX(l.timecreated), 0) AS value
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} ue_course ON ue_course.id = e.courseid
             LEFT JOIN {logstore_standard_log} l
                       ON l.userid = ue.userid AND l.courseid = e.courseid
                 WHERE ue.status = 0
                   {$coursewhere}
              GROUP BY ue.userid, e.courseid
                HAVING COALESCE(MAX(l.timecreated), 0) < :cutoff";

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Find users who haven't logged in for X days.
     *
     * @param \stdClass $rule The rule record (threshold = days).
     * @return array Matching records.
     */
    protected static function evaluate_login_gap(\stdClass $rule): array {
        global $DB;

        $cutoff = time() - ((int)$rule->threshold * DAYSECS);

        $sql = "SELECT u.id AS userid, 0 AS courseid, u.lastlogin AS value
                  FROM {user} u
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND u.lastlogin > 0
                   AND u.lastlogin < :cutoff";

        return array_values($DB->get_records_sql($sql, ['cutoff' => $cutoff]));
    }

    /**
     * Check if an alert was already triggered for this rule/user within the dedup window.
     *
     * @param int $ruleid Rule ID.
     * @param int $userid User ID.
     * @param int|null $courseid Course ID (nullable).
     * @return bool True if duplicate.
     */
    protected static function is_duplicate(int $ruleid, int $userid, ?int $courseid): bool {
        global $DB;

        $since = time() - self::DEDUP_WINDOW;
        $params = [
            'rule_id' => $ruleid,
            'user_id' => $userid,
            'since' => $since,
        ];

        $coursecond = '';
        if ($courseid) {
            $coursecond = ' AND course_id = :course_id';
            $params['course_id'] = $courseid;
        } else {
            $coursecond = ' AND course_id IS NULL';
        }

        return $DB->record_exists_select(
            'report_adeptus_alert_logs',
            "rule_id = :rule_id AND user_id = :user_id AND timecreated > :since{$coursecond}",
            $params
        );
    }

    /**
     * Get unnotified alert logs and mark them as notified.
     *
     * @return array Array of alert log records.
     */
    public static function get_and_mark_pending(): array {
        global $DB;

        $logs = $DB->get_records('report_adeptus_alert_logs', ['notified' => 0]);
        foreach ($logs as $log) {
            $DB->set_field('report_adeptus_alert_logs', 'notified', 1, ['id' => $log->id]);
        }

        return $logs;
    }
}

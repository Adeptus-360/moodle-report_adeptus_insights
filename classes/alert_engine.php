<?php
// This file is part of Moodle - http://moodle.org/.
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

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Alert conditions engine — evaluates at-risk learner conditions.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class alert_engine {

    /** @var int Inactivity threshold in days. */
    protected int $inactivitydays;

    /** @var int Completion rate threshold percentage. */
    protected int $completionthreshold;

    /**
     * Constructor — loads thresholds from plugin config.
     */
    public function __construct() {
        $this->inactivitydays = (int) get_config('report_adeptus_insights', 'alert_inactivity_days') ?: 14;
        $this->completionthreshold = (int) get_config('report_adeptus_insights', 'alert_completion_threshold') ?: 30;
    }

    /**
     * Check if the enterprise feature gate allows alerts.
     *
     * @return bool
     */
    public static function is_available(): bool {
        $tier = get_config('report_adeptus_insights', 'license_tier');
        return ($tier === 'enterprise');
    }

    /**
     * Check if alerts are enabled in settings.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('report_adeptus_insights', 'alert_enabled');
    }

    /**
     * Get all inactive learners grouped by course.
     *
     * Returns learners who haven't logged in for more than the configured threshold.
     *
     * @return array Course-grouped array: [courseid => ['course' => obj, 'learners' => [...]]]
     */
    public function get_inactive_learners(): array {
        global $DB;

        $cutoff = time() - ($this->inactivitydays * DAYSECS);

        $sql = "SELECT ue.id AS ueid, u.id AS userid, u.firstname, u.lastname, u.email,
                       c.id AS courseid, c.fullname AS coursename, u.lastaccess
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {user} u ON u.id = ue.userid
                 WHERE ue.status = 0
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND (u.lastaccess < :cutoff OR u.lastaccess = 0)
                   AND c.id != :siteid
              ORDER BY c.fullname, u.lastname, u.firstname";

        $params = ['cutoff' => $cutoff, 'siteid' => SITEID];
        $records = $DB->get_records_sql($sql, $params);

        return $this->group_by_course($records, 'inactive');
    }

    /**
     * Get learners with low course completion rates, grouped by course.
     *
     * @return array Course-grouped array.
     */
    public function get_low_completion_learners(): array {
        global $DB;

        // Only courses with completion enabled.
        $sql = "SELECT cc.id AS ccid, u.id AS userid, u.firstname, u.lastname, u.email,
                       c.id AS courseid, c.fullname AS coursename,
                       (SELECT COUNT(*) FROM {course_modules} cm
                         WHERE cm.course = c.id AND cm.completion > 0 AND cm.deletioninprogress = 0) AS total_activities,
                       (SELECT COUNT(*) FROM {course_modules_completion} cmc
                          JOIN {course_modules} cm2 ON cm2.id = cmc.coursemoduleid
                         WHERE cm2.course = c.id AND cmc.userid = u.id
                           AND cmc.completionstate > 0) AS completed_activities
                  FROM {course_completions} cc
                  JOIN {user} u ON u.id = cc.userid
                  JOIN {course} c ON c.id = cc.course
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND cc.timecompleted IS NULL
                   AND c.enablecompletion = 1
                   AND c.id != :siteid
              ORDER BY c.fullname, u.lastname, u.firstname";

        $records = $DB->get_records_sql($sql, ['siteid' => SITEID]);

        // Filter by completion percentage.
        $threshold = $this->completionthreshold;
        $results = [];
        foreach ($records as $record) {
            if ((int) $record->total_activities === 0) {
                continue;
            }
            $pct = round(((int) $record->completed_activities / (int) $record->total_activities) * 100);
            if ($pct < $threshold) {
                $record->completion_pct = $pct;
                $results[] = $record;
            }
        }

        return $this->group_by_course($results, 'low_completion');
    }

    /**
     * Get all at-risk learners (combines both conditions).
     *
     * @return array ['inactive' => [...], 'low_completion' => [...]]
     */
    public function get_at_risk_learners(): array {
        return [
            'inactive' => $this->get_inactive_learners(),
            'low_completion' => $this->get_low_completion_learners(),
        ];
    }

    /**
     * Check if a digest was already sent for this period.
     *
     * @param string $period Period identifier (e.g. '2026-W08' or '2026-02-19').
     * @return bool
     */
    public function was_digest_sent(string $period): bool {
        global $DB;
        return $DB->record_exists('report_adeptus_alert_log', ['period_key' => $period]);
    }

    /**
     * Log that a digest was sent.
     *
     * @param string $period Period identifier.
     * @param int $recipientcount Number of recipients emailed.
     * @param int $learnercount Number of at-risk learners found.
     */
    public function log_digest(string $period, int $recipientcount, int $learnercount): void {
        global $DB;
        $DB->insert_record('report_adeptus_alert_log', (object) [
            'period_key' => $period,
            'recipients_sent' => $recipientcount,
            'learners_found' => $learnercount,
            'timecreated' => time(),
        ]);
    }

    /**
     * Get the current period key based on configured frequency.
     *
     * @return string
     */
    public function get_current_period_key(): string {
        $frequency = get_config('report_adeptus_insights', 'alert_frequency') ?: 'weekly';
        $now = time();
        switch ($frequency) {
            case 'daily':
                return date('Y-m-d', $now);
            case 'monthly':
                return date('Y-m', $now);
            case 'weekly':
            default:
                return date('o-\WW', $now);
        }
    }

    /**
     * Get users who should receive the digest based on configured recipient roles.
     *
     * @return array Array of user objects.
     */
    public function get_digest_recipients(): array {
        global $DB;

        $roleconfig = get_config('report_adeptus_insights', 'alert_recipient_roles');
        if (empty($roleconfig)) {
            $roleconfig = 'manager';
        }

        $rolenames = array_map('trim', explode(',', $roleconfig));
        $recipients = [];
        $seen = [];

        foreach ($rolenames as $rolename) {
            $role = $DB->get_record('role', ['shortname' => $rolename]);
            if (!$role) {
                continue;
            }
            $context = \context_system::instance();
            $users = get_role_users($role->id, $context, false, 'u.id, u.firstname, u.lastname, u.email, u.mailformat');
            foreach ($users as $user) {
                if (!isset($seen[$user->id])) {
                    $seen[$user->id] = true;
                    $recipients[] = $user;
                }
            }
        }

        return $recipients;
    }

    /**
     * Group records by course.
     *
     * @param array $records DB records.
     * @param string $type Alert type identifier.
     * @return array Grouped data.
     */
    protected function group_by_course(array $records, string $type): array {
        $grouped = [];
        foreach ($records as $record) {
            $cid = $record->courseid;
            if (!isset($grouped[$cid])) {
                $grouped[$cid] = [
                    'courseid' => $cid,
                    'coursename' => $record->coursename,
                    'type' => $type,
                    'learners' => [],
                ];
            }
            $grouped[$cid]['learners'][] = $record;
        }
        return array_values($grouped);
    }
}

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

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Time calculation helper for log-event delta method.
 *
 * Calculates time spent on the LMS by examining consecutive log events per user
 * per session. Gaps exceeding the session timeout (default 30 minutes) are capped.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class time_calculator {

    /** @var int Maximum gap between events in seconds (30 minutes). */
    const SESSION_TIMEOUT = 1800;

    /**
     * Get the SQL subquery fragment for calculating time deltas from log events.
     *
     * This returns a complete SQL query that calculates total time in seconds
     * per user using the log-event delta method with a 30-minute session cap.
     *
     * @param int $fromtime Unix timestamp for start of date range.
     * @param int $totime Unix timestamp for end of date range.
     * @param string $prefix Table prefix placeholder (use 'prefix_' for Moodle).
     * @return string SQL subquery.
     */
    public static function get_time_per_user_sql(int $fromtime, int $totime, string $prefix = 'prefix_'): string {
        $timeout = self::SESSION_TIMEOUT;
        return "
            SELECT
                userid,
                SUM(
                    CASE
                        WHEN time_delta > 0 AND time_delta <= {$timeout}
                        THEN time_delta
                        ELSE 0
                    END
                ) AS total_seconds
            FROM (
                SELECT
                    userid,
                    timecreated,
                    timecreated - LAG(timecreated) OVER (
                        PARTITION BY userid ORDER BY timecreated
                    ) AS time_delta
                FROM {$prefix}logstore_standard_log
                WHERE timecreated >= {$fromtime}
                  AND timecreated < {$totime}
                  AND userid > 0
                  AND anonymous = 0
            ) deltas
            GROUP BY userid
        ";
    }

    /**
     * Get SQL for time per user per course.
     *
     * @param int $fromtime Unix timestamp for start of date range.
     * @param int $totime Unix timestamp for end of date range.
     * @param string $prefix Table prefix placeholder.
     * @return string SQL subquery.
     */
    public static function get_time_per_user_course_sql(int $fromtime, int $totime, string $prefix = 'prefix_'): string {
        $timeout = self::SESSION_TIMEOUT;
        return "
            SELECT
                userid,
                courseid,
                SUM(
                    CASE
                        WHEN time_delta > 0 AND time_delta <= {$timeout}
                        THEN time_delta
                        ELSE 0
                    END
                ) AS total_seconds
            FROM (
                SELECT
                    userid,
                    courseid,
                    timecreated,
                    timecreated - LAG(timecreated) OVER (
                        PARTITION BY userid, courseid ORDER BY timecreated
                    ) AS time_delta
                FROM {$prefix}logstore_standard_log
                WHERE timecreated >= {$fromtime}
                  AND timecreated < {$totime}
                  AND userid > 0
                  AND anonymous = 0
                  AND courseid > 0
            ) deltas
            GROUP BY userid, courseid
        ";
    }

    /**
     * Get SQL for time per activity (contextid) in a course.
     *
     * @param int $fromtime Unix timestamp for start of date range.
     * @param int $totime Unix timestamp for end of date range.
     * @param string $prefix Table prefix placeholder.
     * @return string SQL subquery.
     */
    public static function get_time_per_activity_sql(int $fromtime, int $totime, string $prefix = 'prefix_'): string {
        $timeout = self::SESSION_TIMEOUT;
        return "
            SELECT
                userid,
                contextid,
                courseid,
                SUM(
                    CASE
                        WHEN time_delta > 0 AND time_delta <= {$timeout}
                        THEN time_delta
                        ELSE 0
                    END
                ) AS total_seconds
            FROM (
                SELECT
                    userid,
                    contextid,
                    courseid,
                    timecreated,
                    timecreated - LAG(timecreated) OVER (
                        PARTITION BY userid, contextid ORDER BY timecreated
                    ) AS time_delta
                FROM {$prefix}logstore_standard_log
                WHERE timecreated >= {$fromtime}
                  AND timecreated < {$totime}
                  AND userid > 0
                  AND anonymous = 0
                  AND courseid > 0
                  AND contextlevel = 70
            ) deltas
            GROUP BY userid, contextid, courseid
        ";
    }

    /**
     * Get SQL for weekly/monthly time trend.
     *
     * @param int $fromtime Unix timestamp for start of date range.
     * @param int $totime Unix timestamp for end of date range.
     * @param string $period 'weekly' or 'monthly'.
     * @param string $prefix Table prefix placeholder.
     * @return string SQL subquery.
     */
    public static function get_time_trend_sql(
        int $fromtime, int $totime, string $period = 'weekly', string $prefix = 'prefix_'
    ): string {
        $timeout = self::SESSION_TIMEOUT;
        if ($period === 'monthly') {
            $dateformat = '%Y-%m';
        } else {
            $dateformat = '%x-W%v';
        }
        return "
            SELECT
                period_label,
                SUM(
                    CASE
                        WHEN time_delta > 0 AND time_delta <= {$timeout}
                        THEN time_delta
                        ELSE 0
                    END
                ) AS total_seconds,
                COUNT(DISTINCT userid) AS active_users
            FROM (
                SELECT
                    userid,
                    timecreated,
                    DATE_FORMAT(FROM_UNIXTIME(timecreated), '{$dateformat}') AS period_label,
                    timecreated - LAG(timecreated) OVER (
                        PARTITION BY userid ORDER BY timecreated
                    ) AS time_delta
                FROM {$prefix}logstore_standard_log
                WHERE timecreated >= {$fromtime}
                  AND timecreated < {$totime}
                  AND userid > 0
                  AND anonymous = 0
            ) deltas
            GROUP BY period_label
            ORDER BY period_label
        ";
    }

    /**
     * Format seconds as hours:minutes string.
     *
     * @param int $seconds Total seconds.
     * @return string Formatted as "Xh Ym".
     */
    public static function format_duration(int $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return sprintf('%dh %02dm', $hours, $minutes);
    }
}

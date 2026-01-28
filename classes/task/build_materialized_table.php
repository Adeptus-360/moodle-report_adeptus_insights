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

namespace report_adeptus_insights\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to maintain and optimize materialized tables.
 *
 * This task performs maintenance on the analytics tables:
 * - Removes orphaned records (deleted users/courses)
 * - Cleans up old cache entries
 * - Purges old usage tracking data based on retention settings
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class build_materialized_table extends \core\task\scheduled_task {
    /**
     * Default retention period in days for usage data.
     */
    const DEFAULT_RETENTION_DAYS = 365;

    /**
     * Default retention period in days for cache data.
     */
    const CACHE_RETENTION_DAYS = 7;

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_build_materialized_table', 'report_adeptus_insights');
    }

    /**
     * Execute the task.
     *
     * Performs maintenance operations on plugin tables:
     * 1. Remove orphaned analytics records
     * 2. Clean up expired cache entries
     * 3. Purge old usage tracking data
     * 4. Clean up old webhook events
     */
    public function execute() {
        global $DB;

        mtrace('Starting materialized table maintenance...');

        $starttime = time();

        // 1. Remove orphaned analytics records.
        $orphaned = $this->cleanup_orphaned_analytics();
        mtrace("Removed {$orphaned} orphaned analytics records.");

        // 2. Clean up expired cache entries.
        $cacheremoved = $this->cleanup_cache();
        mtrace("Removed {$cacheremoved} expired cache entries.");

        // 3. Purge old usage tracking data.
        $usageremoved = $this->cleanup_usage_data();
        mtrace("Removed {$usageremoved} old usage records.");

        // 4. Clean up old webhook events.
        $webhooksremoved = $this->cleanup_webhooks();
        mtrace("Removed {$webhooksremoved} old processed webhook events.");

        // 5. Clean up old export tracking records.
        $exportsremoved = $this->cleanup_exports();
        mtrace("Removed {$exportsremoved} old export tracking records.");

        $duration = time() - $starttime;
        mtrace("Materialized table maintenance completed in {$duration} seconds.");
    }

    /**
     * Remove analytics records for deleted users or courses.
     *
     * @return int Number of records removed.
     */
    protected function cleanup_orphaned_analytics() {
        global $DB;

        $removed = 0;

        // Remove records for deleted users.
        $sql = "DELETE FROM {report_adeptus_insights_analytics}
                WHERE userid NOT IN (SELECT id FROM {user} WHERE deleted = 0)";
        $removed += $DB->execute($sql) ? $DB->count_records_sql(
            "SELECT ROW_COUNT()"
        ) : 0;

        // Alternative approach that works across all DB types.
        $sql = "SELECT a.id
                FROM {report_adeptus_insights_analytics} a
                LEFT JOIN {user} u ON u.id = a.userid AND u.deleted = 0
                WHERE u.id IS NULL";

        $orphanedusers = $DB->get_fieldset_sql($sql);
        if (!empty($orphanedusers)) {
            [$insql, $params] = $DB->get_in_or_equal($orphanedusers);
            $DB->delete_records_select('report_adeptus_insights_analytics', "id $insql", $params);
            $removed += count($orphanedusers);
        }

        // Remove records for deleted courses.
        $sql = "SELECT a.id
                FROM {report_adeptus_insights_analytics} a
                LEFT JOIN {course} c ON c.id = a.courseid
                WHERE c.id IS NULL";

        $orphanedcourses = $DB->get_fieldset_sql($sql);
        if (!empty($orphanedcourses)) {
            [$insql, $params] = $DB->get_in_or_equal($orphanedcourses);
            $DB->delete_records_select('report_adeptus_insights_analytics', "id $insql", $params);
            $removed += count($orphanedcourses);
        }

        return $removed;
    }

    /**
     * Clean up expired cache entries.
     *
     * @return int Number of records removed.
     */
    protected function cleanup_cache() {
        global $DB;

        $cutoff = time() - (self::CACHE_RETENTION_DAYS * DAYSECS);

        return $DB->delete_records_select(
            'report_adeptus_insights_cache',
            'timemodified < :cutoff',
            ['cutoff' => $cutoff]
        );
    }

    /**
     * Purge old usage tracking data based on retention settings.
     *
     * @return int Number of records removed.
     */
    protected function cleanup_usage_data() {
        global $DB;

        // Get retention period from plugin settings, default to 365 days.
        $retentiondays = get_config('report_adeptus_insights', 'usage_retention_days');
        if (empty($retentiondays) || $retentiondays < 30) {
            $retentiondays = self::DEFAULT_RETENTION_DAYS;
        }

        $cutoff = time() - ($retentiondays * DAYSECS);

        return $DB->delete_records_select(
            'report_adeptus_insights_usage',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
    }

    /**
     * Clean up old processed webhook events.
     *
     * @return int Number of records removed.
     */
    protected function cleanup_webhooks() {
        global $DB;

        // Keep webhook events for 30 days after processing.
        $cutoff = time() - (30 * DAYSECS);

        return $DB->delete_records_select(
            'report_adeptus_insights_webhooks',
            'processed = 1 AND timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
    }

    /**
     * Clean up old export tracking records.
     *
     * @return int Number of records removed.
     */
    protected function cleanup_exports() {
        global $DB;

        // Get retention period from plugin settings, default to 365 days.
        $retentiondays = get_config('report_adeptus_insights', 'usage_retention_days');
        if (empty($retentiondays) || $retentiondays < 30) {
            $retentiondays = self::DEFAULT_RETENTION_DAYS;
        }

        $cutoff = time() - ($retentiondays * DAYSECS);

        return $DB->delete_records_select(
            'report_adeptus_insights_exports',
            'exportedat < :cutoff',
            ['cutoff' => $cutoff]
        );
    }
}

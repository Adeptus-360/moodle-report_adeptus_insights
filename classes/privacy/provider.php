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

/**
 * Privacy Subsystem implementation for report_adeptus_insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for report_adeptus_insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\core_userlist_provider, \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        // Analytics base table - aggregated user activity data.
        $collection->add_database_table(
            'report_adeptus_insights_analytics',
            [
                'userid' => 'privacy:metadata:report_adeptus_insights_analytics:userid',
                'courseid' => 'privacy:metadata:report_adeptus_insights_analytics:courseid',
                'logins' => 'privacy:metadata:report_adeptus_insights_analytics:logins',
                'assignments_submitted' => 'privacy:metadata:report_adeptus_insights_analytics:assignments_submitted',
                'forum_posts' => 'privacy:metadata:report_adeptus_insights_analytics:forum_posts',
                'resource_clicks' => 'privacy:metadata:report_adeptus_insights_analytics:resource_clicks',
                'last_login' => 'privacy:metadata:report_adeptus_insights_analytics:last_login',
                'average_grade' => 'privacy:metadata:report_adeptus_insights_analytics:average_grade',
            ],
            'privacy:metadata:report_adeptus_insights_analytics'
        );

        // Report cache table - cached report results.
        $collection->add_database_table(
            'report_adeptus_insights_cache',
            [
                'userid' => 'privacy:metadata:report_adeptus_insights_cache:userid',
                'reportid' => 'privacy:metadata:report_adeptus_insights_cache:reportid',
                'jsondata' => 'privacy:metadata:report_adeptus_insights_cache:jsondata',
                'timecreated' => 'privacy:metadata:report_adeptus_insights_cache:timecreated',
            ],
            'privacy:metadata:report_adeptus_insights_cache'
        );

        // Report config table - user-created reports.
        $collection->add_database_table(
            'report_adeptus_insights_config',
            [
                'userid' => 'privacy:metadata:report_adeptus_insights_config:userid',
                'name' => 'privacy:metadata:report_adeptus_insights_config:name',
                'question' => 'privacy:metadata:report_adeptus_insights_config:question',
                'fields' => 'privacy:metadata:report_adeptus_insights_config:fields',
                'filters' => 'privacy:metadata:report_adeptus_insights_config:filters',
                'timecreated' => 'privacy:metadata:report_adeptus_insights_config:timecreated',
            ],
            'privacy:metadata:report_adeptus_insights_config'
        );

        // Report history table - report generation history.
        $collection->add_database_table(
            'report_adeptus_insights_history',
            [
                'userid' => 'privacy:metadata:report_adeptus_insights_history:userid',
                'reportid' => 'privacy:metadata:report_adeptus_insights_history:reportid',
                'parameters' => 'privacy:metadata:report_adeptus_insights_history:parameters',
                'generatedat' => 'privacy:metadata:report_adeptus_insights_history:generatedat',
            ],
            'privacy:metadata:report_adeptus_insights_history'
        );

        // Report bookmarks table - user bookmarks.
        $collection->add_database_table(
            'report_adeptus_insights_bookmarks',
            [
                'userid' => 'privacy:metadata:report_adeptus_insights_bookmarks:userid',
                'reportid' => 'privacy:metadata:report_adeptus_insights_bookmarks:reportid',
                'createdat' => 'privacy:metadata:report_adeptus_insights_bookmarks:createdat',
            ],
            'privacy:metadata:report_adeptus_insights_bookmarks'
        );

        // Usage tracking table - AI credit usage.
        $collection->add_database_table(
            'report_adeptus_insights_usage',
            [
                'userid' => 'privacy:metadata:report_adeptus_insights_usage:userid',
                'usage_type' => 'privacy:metadata:report_adeptus_insights_usage:usage_type',
                'credits_used' => 'privacy:metadata:report_adeptus_insights_usage:credits_used',
                'timecreated' => 'privacy:metadata:report_adeptus_insights_usage:timecreated',
            ],
            'privacy:metadata:report_adeptus_insights_usage'
        );

        // Export tracking table - export history.
        $collection->add_database_table(
            'report_adeptus_insights_exports',
            [
                'userid' => 'privacy:metadata:report_adeptus_insights_exports:userid',
                'reportname' => 'privacy:metadata:report_adeptus_insights_exports:reportname',
                'format' => 'privacy:metadata:report_adeptus_insights_exports:format',
                'exportedat' => 'privacy:metadata:report_adeptus_insights_exports:exportedat',
            ],
            'privacy:metadata:report_adeptus_insights_exports'
        );

        // External system - Adeptus 360 API.
        // Note: Only administrator registration data and AI queries are sent externally.
        // Student data, report results, and analytics data NEVER leave the Moodle server.
        $collection->add_external_location_link(
            'adeptus360_api',
            [
                'admin_email' => 'privacy:metadata:adeptus360_api:admin_email',
                'admin_name' => 'privacy:metadata:adeptus360_api:admin_name',
                'site_url' => 'privacy:metadata:adeptus360_api:site_url',
                'ai_queries' => 'privacy:metadata:adeptus360_api:ai_queries',
            ],
            'privacy:metadata:adeptus360_api'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Check if user has any data in our tables.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {report_adeptus_insights_analytics} aab ON aab.userid = :userid1
                 WHERE ctx.contextlevel = :contextlevel";

        $params = [
            'userid1' => $userid,
            'contextlevel' => CONTEXT_SYSTEM,
        ];

        $contextlist->add_from_sql($sql, $params);

        // Also check other tables.
        $tables = [
            'report_adeptus_insights_cache',
            'report_adeptus_insights_config',
            'report_adeptus_insights_history',
            'report_adeptus_insights_bookmarks',
            'report_adeptus_insights_usage',
            'report_adeptus_insights_exports',
        ];

        foreach ($tables as $table) {
            $sql = "SELECT DISTINCT ctx.id
                      FROM {context} ctx
                      JOIN {{$table}} t ON t.userid = :userid
                     WHERE ctx.contextlevel = :contextlevel";

            $params = [
                'userid' => $userid,
                'contextlevel' => CONTEXT_SYSTEM,
            ];

            $contextlist->add_from_sql($sql, $params);
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        // Get users from all our tables.
        $tables = [
            'report_adeptus_insights_analytics',
            'report_adeptus_insights_cache',
            'report_adeptus_insights_config',
            'report_adeptus_insights_history',
            'report_adeptus_insights_bookmarks',
            'report_adeptus_insights_usage',
            'report_adeptus_insights_exports',
        ];

        foreach ($tables as $table) {
            $sql = "SELECT DISTINCT userid FROM {{$table}}";
            $userlist->add_from_sql('userid', $sql, []);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        // Export analytics base data.
        $analytics = $DB->get_records('report_adeptus_insights_analytics', ['userid' => $userid]);
        if ($analytics) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'report_adeptus_insights'), get_string('privacy:analytics', 'report_adeptus_insights')],
                (object) ['analytics' => array_values($analytics)]
            );
        }

        // Export report cache data.
        $cache = $DB->get_records('report_adeptus_insights_cache', ['userid' => $userid]);
        if ($cache) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'report_adeptus_insights'), get_string('privacy:reportcache', 'report_adeptus_insights')],
                (object) ['report_cache' => array_values($cache)]
            );
        }

        // Export report config data.
        $configs = $DB->get_records('report_adeptus_insights_config', ['userid' => $userid]);
        if ($configs) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'report_adeptus_insights'), get_string('privacy:reportconfig', 'report_adeptus_insights')],
                (object) ['report_configs' => array_values($configs)]
            );
        }

        // Export report history data.
        $history = $DB->get_records('report_adeptus_insights_history', ['userid' => $userid]);
        if ($history) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'report_adeptus_insights'), get_string('privacy:reporthistory', 'report_adeptus_insights')],
                (object) ['report_history' => array_values($history)]
            );
        }

        // Export bookmarks data.
        $bookmarks = $DB->get_records('report_adeptus_insights_bookmarks', ['userid' => $userid]);
        if ($bookmarks) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'report_adeptus_insights'), get_string('privacy:bookmarks', 'report_adeptus_insights')],
                (object) ['bookmarks' => array_values($bookmarks)]
            );
        }

        // Export usage tracking data.
        $usage = $DB->get_records('report_adeptus_insights_usage', ['userid' => $userid]);
        if ($usage) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'report_adeptus_insights'), get_string('privacy:usagetracking', 'report_adeptus_insights')],
                (object) ['usage_tracking' => array_values($usage)]
            );
        }

        // Export export tracking data.
        $exports = $DB->get_records('report_adeptus_insights_exports', ['userid' => $userid]);
        if ($exports) {
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'report_adeptus_insights'), get_string('privacy:exporttracking', 'report_adeptus_insights')],
                (object) ['export_tracking' => array_values($exports)]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        // Delete all user data from our tables.
        $DB->delete_records('report_adeptus_insights_analytics');
        $DB->delete_records('report_adeptus_insights_cache');
        $DB->delete_records('report_adeptus_insights_config');
        $DB->delete_records('report_adeptus_insights_history');
        $DB->delete_records('report_adeptus_insights_bookmarks');
        $DB->delete_records('report_adeptus_insights_usage');
        $DB->delete_records('report_adeptus_insights_exports');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        // Delete user data from all our tables.
        $DB->delete_records('report_adeptus_insights_analytics', ['userid' => $userid]);
        $DB->delete_records('report_adeptus_insights_cache', ['userid' => $userid]);
        $DB->delete_records('report_adeptus_insights_config', ['userid' => $userid]);
        $DB->delete_records('report_adeptus_insights_history', ['userid' => $userid]);
        $DB->delete_records('report_adeptus_insights_bookmarks', ['userid' => $userid]);
        $DB->delete_records('report_adeptus_insights_usage', ['userid' => $userid]);
        $DB->delete_records('report_adeptus_insights_exports', ['userid' => $userid]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete from all tables.
        $tables = [
            'report_adeptus_insights_analytics',
            'report_adeptus_insights_cache',
            'report_adeptus_insights_config',
            'report_adeptus_insights_history',
            'report_adeptus_insights_bookmarks',
            'report_adeptus_insights_usage',
            'report_adeptus_insights_exports',
        ];

        foreach ($tables as $table) {
            $DB->delete_records_select($table, "userid $insql", $inparams);
        }
    }
}

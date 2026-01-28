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

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_system;

/**
 * External service to manage report bookmarks.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bookmark_report extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reportid' => new external_value(PARAM_TEXT, 'Report ID'),
            'action' => new external_value(PARAM_ALPHA, 'Action: toggle, add, remove, or clear_all', VALUE_DEFAULT, 'toggle'),
        ]);
    }

    /**
     * Manage report bookmark.
     *
     * @param string $reportid Report ID
     * @param string $action Action to perform
     * @return array Result
     */
    public static function execute(string $reportid, string $action = 'toggle'): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'reportid' => $reportid,
            'action' => $action,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            // Clear all bookmarks for this user.
            if ($params['action'] === 'clearall') {
                $DB->delete_records('report_adeptus_insights_bookmarks', ['userid' => $USER->id]);
                return [
                    'success' => true,
                    'error' => false,
                    'message' => get_string('bookmarks_cleared', 'report_adeptus_insights'),
                    'action' => 'clear_all',
                    'bookmarked' => false,
                    'bookmark_id' => 0,
                ];
            }

            // Check if already bookmarked.
            $existing = $DB->get_record('report_adeptus_insights_bookmarks', [
                'userid' => $USER->id,
                'reportid' => $params['reportid'],
            ]);

            if ($params['action'] === 'toggle') {
                if ($existing) {
                    // Remove bookmark.
                    $DB->delete_records('report_adeptus_insights_bookmarks', [
                        'userid' => $USER->id,
                        'reportid' => $params['reportid'],
                    ]);

                    return [
                        'success' => true,
                        'error' => false,
                        'message' => get_string('bookmark_removed', 'report_adeptus_insights'),
                        'action' => 'removed',
                        'bookmarked' => false,
                        'bookmark_id' => 0,
                    ];
                } else {
                    // Add bookmark.
                    $bookmark = new \stdClass();
                    $bookmark->userid = $USER->id;
                    $bookmark->reportid = $params['reportid'];
                    $bookmark->createdat = time();

                    $bookmarkid = $DB->insert_record('report_adeptus_insights_bookmarks', $bookmark);

                    if ($bookmarkid) {
                        return [
                            'success' => true,
                            'error' => false,
                            'message' => get_string('report_bookmarked', 'report_adeptus_insights'),
                            'action' => 'added',
                            'bookmarked' => true,
                            'bookmark_id' => (int) $bookmarkid,
                        ];
                    } else {
                        return [
                            'success' => false,
                            'error' => true,
                            'message' => get_string('error_bookmark_failed', 'report_adeptus_insights'),
                            'action' => '',
                            'bookmarked' => false,
                            'bookmark_id' => 0,
                        ];
                    }
                }
            } else if ($params['action'] === 'remove') {
                if ($existing) {
                    $DB->delete_records('report_adeptus_insights_bookmarks', [
                        'userid' => $USER->id,
                        'reportid' => $params['reportid'],
                    ]);

                    return [
                        'success' => true,
                        'error' => false,
                        'message' => get_string('bookmark_removed', 'report_adeptus_insights'),
                        'action' => 'removed',
                        'bookmarked' => false,
                        'bookmark_id' => 0,
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => true,
                        'message' => get_string('error_bookmark_not_found', 'report_adeptus_insights'),
                        'action' => '',
                        'bookmarked' => false,
                        'bookmark_id' => 0,
                    ];
                }
            } else {
                // Legacy 'add' action.
                if ($existing) {
                    return [
                        'success' => false,
                        'error' => true,
                        'message' => get_string('error_already_bookmarked', 'report_adeptus_insights'),
                        'action' => '',
                        'bookmarked' => true,
                        'bookmark_id' => (int) $existing->id,
                    ];
                }

                // Create bookmark.
                $bookmark = new \stdClass();
                $bookmark->userid = $USER->id;
                $bookmark->reportid = $params['reportid'];
                $bookmark->createdat = time();

                $bookmarkid = $DB->insert_record('report_adeptus_insights_bookmarks', $bookmark);

                if ($bookmarkid) {
                    return [
                        'success' => true,
                        'error' => false,
                        'message' => get_string('report_bookmarked', 'report_adeptus_insights'),
                        'action' => 'added',
                        'bookmarked' => true,
                        'bookmark_id' => (int) $bookmarkid,
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => true,
                        'message' => get_string('error_bookmark_failed', 'report_adeptus_insights'),
                        'action' => '',
                        'bookmarked' => false,
                        'bookmark_id' => 0,
                    ];
                }
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => true,
                'message' => get_string('error_database', 'report_adeptus_insights'),
                'action' => '',
                'bookmarked' => false,
                'bookmark_id' => 0,
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'error' => new external_value(PARAM_BOOL, 'Whether an error occurred'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'action' => new external_value(PARAM_TEXT, 'Action performed'),
            'bookmarked' => new external_value(PARAM_BOOL, 'Whether the report is now bookmarked'),
            'bookmark_id' => new external_value(PARAM_INT, 'Bookmark ID if created'),
        ]);
    }
}

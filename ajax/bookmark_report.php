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
 * Bookmark report AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Set content type
header('Content-Type: application/json');

// Get parameters
$reportid = required_param('reportid', PARAM_TEXT); // Changed to PARAM_TEXT for report names
$sesskey = required_param('sesskey', PARAM_ALPHANUM);
$action = optional_param('action', 'toggle', PARAM_ALPHA); // toggle, add, remove, clear_all

// Validate session key
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session key']);
    exit;
}

try {
    // Clear all bookmarks for this user
    if ($action === 'clear_all') {
        $DB->delete_records('adeptus_report_bookmarks', ['userid' => $USER->id]);
        echo json_encode([
            'success' => true,
            'message' => 'All bookmarks cleared successfully',
            'action' => 'clear_all',
        ]);
        exit;
    }
    // Note: We no longer check if report exists locally since reports come from backend
    // The report name is validated by the frontend before calling this endpoint

    // Check if already bookmarked
    $existing = $DB->get_record('adeptus_report_bookmarks', [
        'userid' => $USER->id,
        'reportid' => $reportid,
    ]);

    if ($action === 'toggle') {
        if ($existing) {
            // Remove bookmark
            $DB->delete_records('adeptus_report_bookmarks', [
                'userid' => $USER->id,
                'reportid' => $reportid,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Bookmark removed successfully',
                'action' => 'removed',
                'bookmarked' => false,
            ]);
        } else {
            // Add bookmark
            $bookmark = new stdClass();
            $bookmark->userid = $USER->id;
            $bookmark->reportid = $reportid;
            $bookmark->createdat = time();

            $bookmark_id = $DB->insert_record('adeptus_report_bookmarks', $bookmark);

            if ($bookmark_id) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Report bookmarked successfully',
                    'bookmark_id' => $bookmark_id,
                    'action' => 'added',
                    'bookmarked' => true,
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create bookmark']);
            }
        }
    } else if ($action === 'remove') {
        if ($existing) {
            $DB->delete_records('adeptus_report_bookmarks', [
                'userid' => $USER->id,
                'reportid' => $reportid,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Bookmark removed successfully',
                'action' => 'removed',
                'bookmarked' => false,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Bookmark not found']);
        }
    } else {
        // Legacy 'add' action
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Report already bookmarked']);
            exit;
        }

        // Create bookmark
        $bookmark = new stdClass();
        $bookmark->userid = $USER->id;
        $bookmark->reportid = $reportid;
        $bookmark->createdat = time();

        $bookmark_id = $DB->insert_record('adeptus_report_bookmarks', $bookmark);

        if ($bookmark_id) {
            echo json_encode([
            'success' => true,
            'message' => 'Report bookmarked successfully',
                'bookmark_id' => $bookmark_id,
                'action' => 'added',
                'bookmarked' => true,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create bookmark']);
        }
    }
} catch (Exception $e) {
    error_log('Error in bookmark_report.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}

exit;

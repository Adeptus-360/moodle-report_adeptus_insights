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
 * Manage recent reports AJAX endpoint.
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
$action = required_param('action', PARAM_TEXT); // 'clear_all' or 'remove_single' - changed to PARAM_TEXT to allow underscores
$reportid = optional_param('reportid', '', PARAM_TEXT); // Changed to PARAM_TEXT for string report names
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Trim whitespace from action parameter
$action = trim($action);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => get_string('error_invalid_sesskey', 'report_adeptus_insights')]);
    exit;
}

try {
    $userid = $USER->id;

    if ($action === 'clear_all') {
        // Clear all recent reports for this user
        $DB->delete_records('adeptus_report_history', ['userid' => $userid]);

        echo json_encode([
            'success' => true,
            'message' => get_string('recent_reports_cleared', 'report_adeptus_insights'),
            'action' => 'clear_all',
        ]);
    } else if ($action === 'remove_single') {
        if (empty($reportid)) {
            echo json_encode(['success' => false, 'message' => get_string('error_report_id_required', 'report_adeptus_insights')]);
            exit;
        }

        // Remove all history entries for this specific report for this user
        $deleted = $DB->delete_records('adeptus_report_history', [
            'userid' => $userid,
            'reportid' => $reportid,
        ]);

        if ($deleted) {
            echo json_encode([
                'success' => true,
                'message' => get_string('recent_report_removed', 'report_adeptus_insights'),
                'action' => 'remove_single',
                'reportid' => $reportid,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => get_string('error_recent_report_not_found', 'report_adeptus_insights')]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => get_string('error_invalid_action', 'report_adeptus_insights'),
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => get_string('error_database', 'report_adeptus_insights')]);
}

exit;

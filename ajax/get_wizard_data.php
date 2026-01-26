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
 * Get wizard data AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

// Require login and capability
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/adeptus_insights:view', $context);

// Set content type
header('Content-Type: application/json');

try {
    // Get user information
    global $USER, $CFG;

    // Generate session key
    $sesskey = sesskey();

    // Return wizard data
    echo json_encode([
        'success' => true,
        'data' => [
            'wwwroot' => $CFG->wwwroot,
            'sesskey' => $sesskey,
            'userid' => $USER->id,
            'username' => $USER->username,
            'fullname' => fullname($USER),
            'timezone' => $USER->timezone,
            'lang' => $USER->lang,
            'moodle_version' => $CFG->version,
            'plugin_version' => '1.0.0', // You can make this dynamic
        ],
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => get_string('error_wizard_data_failed', 'report_adeptus_insights')]);
}

exit;

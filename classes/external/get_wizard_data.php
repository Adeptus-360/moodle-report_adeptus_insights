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

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * External API for getting wizard initialization data.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_wizard_data extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Get wizard initialization data including user info and session key.
     *
     * @return array Wizard data including user information and config.
     */
    public static function execute() {
        global $USER, $CFG;

        // Parameter validation (none for this endpoint).
        $params = self::validate_parameters(self::execute_parameters(), []);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability check.
        require_capability('report/adeptus_insights:view', $context);

        // Generate session key.
        $sesskey = sesskey();

        // Return wizard data.
        return [
            'success' => true,
            'data' => [
                'wwwroot' => $CFG->wwwroot,
                'sesskey' => $sesskey,
                'userid' => (int) $USER->id,
                'username' => $USER->username,
                'fullname' => fullname($USER),
                'timezone' => $USER->timezone ?: '',
                'lang' => $USER->lang ?: '',
                'moodle_version' => (string) $CFG->version,
                'plugin_version' => '1.0.0',
            ],
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'data' => new external_single_structure([
                'wwwroot' => new external_value(PARAM_URL, 'Moodle site URL'),
                'sesskey' => new external_value(PARAM_ALPHANUMEXT, 'Session key for AJAX requests'),
                'userid' => new external_value(PARAM_INT, 'Current user ID'),
                'username' => new external_value(PARAM_USERNAME, 'Current username'),
                'fullname' => new external_value(PARAM_TEXT, 'Current user full name'),
                'timezone' => new external_value(PARAM_TEXT, 'User timezone'),
                'lang' => new external_value(PARAM_LANG, 'User language'),
                'moodle_version' => new external_value(PARAM_TEXT, 'Moodle version number'),
                'plugin_version' => new external_value(PARAM_TEXT, 'Plugin version'),
            ]),
        ]);
    }
}

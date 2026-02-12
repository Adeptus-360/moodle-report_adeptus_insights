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
 * External function to get subscription details.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/report/adeptus_insights/externallib.php');

/**
 * Get subscription details external function - delegates to legacy external class.
 */
class get_subscription_details extends \external_api {
    /**
     * Returns description of method parameters.
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return \report_adeptus_insights\external::get_subscription_details_parameters();
    }

    /**
     * Returns description of method result value.
     * @return \external_description
     */
    public static function execute_returns() {
        return \report_adeptus_insights\external::get_subscription_details_returns();
    }

    /**
     * Execute the function.
     * @return array Subscription details.
     */
    public static function execute() {
        return \report_adeptus_insights\external::get_subscription_details();
    }
}

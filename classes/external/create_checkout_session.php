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
 * External function to create a Stripe checkout session.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/report/adeptus_insights/externallib.php');

/**
 * Create checkout session external function - delegates to legacy external class.
 */
class create_checkout_session extends \external_api {
    /**
     * Returns description of method parameters.
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return \report_adeptus_insights\external::create_checkout_session_parameters();
    }

    /**
     * Returns description of method result value.
     * @return \external_description
     */
    public static function execute_returns() {
        return \report_adeptus_insights\external::create_checkout_session_returns();
    }

    /**
     * Execute the function.
     * @param int $planid Plan ID.
     * @param string $stripepriceid Stripe price ID.
     * @param string $returnurl Return URL.
     * @param string $sesskey Session key.
     * @return array Checkout session result.
     */
    public static function execute($planid, $stripepriceid, $returnurl, $sesskey) {
        return \report_adeptus_insights\external::create_checkout_session($planid, $stripepriceid, $returnurl, $sesskey);
    }
}

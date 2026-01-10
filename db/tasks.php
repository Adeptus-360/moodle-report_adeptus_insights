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
 * Scheduled tasks definition for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'report_adeptus_insights\task\build_materialized_table',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '1',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
    [
        'classname' => 'report_adeptus_insights\task\build_analytics_base',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '1',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
];

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
 * AJAX function definitions for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'report_adeptus_insights_fetch_preview' => [
        'classname'    => 'report_adeptus_insights\\external\\fetch_preview',
        'methodname'   => 'execute',
        'classpath'    => '',
        'description'  => 'Fetch report preview data',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'moodle/site:viewreports',
    ],
    'report_adeptus_insights_report_crud' => [
        'classname'    => 'report_adeptus_insights\\external\\report_crud',
        'methodname'   => 'execute',
        'classpath'    => '',
        'description'  => 'CRUD operations for reports',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'moodle/site:viewreports',
    ],
    'report_adeptus_insights_report_parameters' => [
        'classname'    => 'report_adeptus_insights\\external\\report_parameters',
        'methodname'   => 'execute',
        'classpath'    => '',
        'description'  => 'Fetch parameter options for reports',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'moodle/site:viewreports',
    ],
    'report_adeptus_insights_report_execute' => [
        'classname'    => 'report_adeptus_insights\\external\\report_execute',
        'methodname'   => 'execute',
        'classpath'    => '',
        'description'  => 'Execute a report with parameters',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'moodle/site:viewreports',
    ],
    'report_adeptus_insights_report_export' => [
        'classname'    => 'report_adeptus_insights\\external\\report_export',
        'methodname'   => 'execute',
        'classpath'    => '',
        'description'  => 'Export report results',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'moodle/site:viewreports',
    ],
    'report_adeptus_insights_report_history' => [
        'classname'    => 'report_adeptus_insights\\external\\report_history',
        'methodname'   => 'execute',
        'classpath'    => '',
        'description'  => 'User report history and bookmarks',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'moodle/site:viewreports',
    ],
];

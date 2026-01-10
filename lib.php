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
 * Library functions for the Adeptus Insights report plugin.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function report_adeptus_insights_before_http_headers() {
    global $PAGE;

    // Configure RequireJS for SweetAlert2
    // This script needs to run before any module tries to require 'sweetalert2'.
    $PAGE->requires->js_init_code("function setupAdeptusInsightsRequireJSConfig() {\n" .
        "    if (typeof requirejs !== 'undefined') {\n" .
        "        requirejs.config({\n" .
        "            paths: {\n" .
        "                'sweetalert2': ['https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min']\n" .
        "            },\n" .
        "            shim: {\n" .
        "                'sweetalert2': {\n" .
        "                    exports: 'Swal'\n" .
        "                }\n" .
        "            }\n" .
        "        });\n" .
        "    } else {\n" .
        "        console.error('Adeptus Insights: requirejs is not defined for sweetalert2 config.');\n" .
        "    }\n" .
        "}");

    // Call the setup function.
    $PAGE->requires->js_init_call('setupAdeptusInsightsRequireJSConfig');
}

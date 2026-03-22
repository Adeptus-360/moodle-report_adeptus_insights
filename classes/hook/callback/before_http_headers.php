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
 * Hook callback for before_http_headers.
 *
 * Loads bundled third-party JS/CSS libraries on plugin pages.
 * All libraries are bundled with the plugin — no external CDN calls.
 *
 * Libraries loaded here:
 * - SweetAlert2 v11.26.18 (lib/sweetalert2/)
 * - Simple DataTables v3.2.0 (amd/vendor/)
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights\hook\callback;

/**
 * Callback for the before_http_headers hook.
 */
class before_http_headers {

    /**
     * Load bundled JS/CSS libraries on plugin pages.
     *
     * @param \core\hook\output\before_http_headers $hook The hook instance.
     */
    public static function execute(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;

        try {
            $pageurl = $PAGE->url->out(false);
        } catch (\Exception $e) {
            return;
        }

        if (strpos($pageurl, '/report/adeptus_insights/') === false) {
            return;
        }

        // Load local SweetAlert2 library (bundled with plugin, no CDN).
        $PAGE->requires->js(
            new \moodle_url('/report/adeptus_insights/lib/sweetalert2/sweetalert2.all.min.js'),
            true
        );

        // Load local Simple DataTables library (bundled with plugin, no CDN).
        // This creates the global `simpleDatatables` used by assistant.js and generated_reports.js.
        $PAGE->requires->js(
            new \moodle_url('/report/adeptus_insights/amd/vendor/simple-datatables.js'),
            true
        );

        // Load Simple DataTables CSS (bundled with plugin, no CDN).
        $PAGE->requires->css(
            new \moodle_url('/report/adeptus_insights/amd/vendor/style.css')
        );
    }
}

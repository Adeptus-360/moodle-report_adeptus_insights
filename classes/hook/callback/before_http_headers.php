<?php
// This file is part of Moodle - http://moodle.org/.
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
     * Load SweetAlert2 on plugin pages.
     *
     * @param \core\hook\output\before_http_headers $hook
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

        $PAGE->requires->js(
            new \moodle_url('/report/adeptus_insights/lib/sweetalert2/sweetalert2.all.min.js'),
            true
        );
    }
}

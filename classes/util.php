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
 * Utility class for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

class util {
    /** Session key for redirect flag. */
    private const SESSION_KEY = 'report_adeptus_insights_redirect_subscription';

    /**
     * Mark that we should redirect after admin saves settings.
     *
     * Uses Moodle's session API ($SESSION global) to survive the admin_settings post/redirect cycle.
     *
     * @return void
     */
    public static function mark_post_settings_redirect(): void {
        global $SESSION;
        $SESSION->{self::SESSION_KEY} = 1;
    }

    /**
     * Consume and clear the redirect flag.
     *
     * @return bool True if the redirect flag was set, false otherwise.
     */
    public static function consume_post_settings_redirect(): bool {
        global $SESSION;
        if (!empty($SESSION->{self::SESSION_KEY})) {
            unset($SESSION->{self::SESSION_KEY});
            return true;
        }
        return false;
    }
}

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
 * Utility class for Adeptus Insights.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;
/**
 * Utility class for Adeptus Insights.
 *
 * Provides helper methods for session cache management and redirects.
 */
class util {
    /** Cache key for redirect flag. */
    private const CACHE_KEY_REDIRECT = 'redirect_subscription';

    /** Cache key for AI token. */
    private const CACHE_KEY_AI_TOKEN = 'ai_token';

    /**
     * Get the session cache instance.
     *
     * @return \cache The session cache instance.
     */
    private static function get_cache(): \cache {
        return \cache::make('report_adeptus_insights', 'session');
    }

    /**
     * Mark that we should redirect after admin saves settings.
     *
     * Uses Moodle's Cache API (MODE_SESSION) to survive the admin_settings post/redirect cycle.
     *
     * @return void
     */
    public static function mark_post_settings_redirect(): void {
        $cache = self::get_cache();
        $cache->set(self::CACHE_KEY_REDIRECT, 1);
    }

    /**
     * Consume and clear the redirect flag.
     *
     * @return bool True if the redirect flag was set, false otherwise.
     */
    public static function consume_post_settings_redirect(): bool {
        $cache = self::get_cache();
        $value = $cache->get(self::CACHE_KEY_REDIRECT);
        if (!empty($value)) {
            $cache->delete(self::CACHE_KEY_REDIRECT);
            return true;
        }
        return false;
    }

    /**
     * Store the AI authentication token in session cache.
     *
     * @param string $token The AI authentication token.
     * @return void
     */
    public static function set_ai_token(string $token): void {
        $cache = self::get_cache();
        $cache->set(self::CACHE_KEY_AI_TOKEN, $token);
    }

    /**
     * Get the AI authentication token from session cache.
     *
     * @return string The AI token, or empty string if not set.
     */
    public static function get_ai_token(): string {
        $cache = self::get_cache();
        $token = $cache->get(self::CACHE_KEY_AI_TOKEN);
        return $token !== false ? $token : '';
    }

    /**
     * Clear the AI authentication token from session cache.
     *
     * @return void
     */
    public static function clear_ai_token(): void {
        $cache = self::get_cache();
        $cache->delete(self::CACHE_KEY_AI_TOKEN);
    }
}

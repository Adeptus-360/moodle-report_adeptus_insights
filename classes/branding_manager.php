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
 * Branding Manager for Adeptus Insights.
 *
 * Handles secure retrieval of branding assets from the backend server.
 * The logo is fetched from the backend API and embedded directly in PDFs,
 * ensuring tamper-resistance as there is no local copy to modify.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages branding assets for PDF exports.
 *
 * This class fetches branding assets (logo, footer text) from the backend
 * server to ensure they cannot be tampered with locally. All branding
 * is controlled server-side for maximum security.
 */
class branding_manager {
    /**
     * Cache key for branding data.
     */
    private const CACHE_KEY = 'branding_data';

    /**
     * Cache TTL in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

    /**
     * Installation manager instance.
     *
     * @var installation_manager
     */
    private $installation_manager;

    /**
     * Cached branding data.
     *
     * @var array|null
     */
    private static $brandingcache = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->installation_manager = new installation_manager();
    }

    /**
     * Get branding data from the backend API.
     *
     * Fetches logo and branding configuration from the backend server.
     * Returns null if the backend is unreachable (strict security mode).
     *
     * @param bool $forcerefresh Force a refresh from the backend, bypassing cache.
     * @return array|null Branding data array or null on failure.
     */
    public function get_branding_data(bool $forcerefresh = false): ?array {
        // Return cached data if available and not forcing refresh.
        if (!$forcerefresh && self::$brandingcache !== null) {
            return self::$brandingcache;
        }

        // Check if plugin is registered.
        if (!$this->installation_manager->is_registered()) {
            return null;
        }

        try {
            $response = $this->installation_manager->make_api_request('branding/logo', [], 'GET');

            if ($response && isset($response['success']) && $response['success']) {
                $data = $response['data'];

                // Validate required fields.
                if (empty($data['logo'])) {
                    return null;
                }

                // Validate logo format (must be data URI).
                if (!$this->validate_logo_format($data['logo'])) {
                    return null;
                }

                // Build branding data structure.
                $brandingdata = [
                    'logo' => $data['logo'],
                    'logo_hash' => $data['hash'] ?? null,
                    'logo_width' => $data['dimensions']['width'] ?? 200,
                    'logo_height' => $data['dimensions']['height'] ?? 60,
                    'footer_text' => $data['footer_text'] ?? get_string('pdf_footer_default', 'report_adeptus_insights'),
                    'version' => $data['version'] ?? '1.0.0',
                    'fetched_at' => time(),
                ];

                // Cache the branding data.
                self::$brandingcache = $brandingdata;

                return $brandingdata;
            }
        } catch (\Exception $e) {
            // Log error but don't expose details.
            debugging('Branding fetch failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return null;
    }

    /**
     * Get the logo as a base64 data URI.
     *
     * @return string|null Logo data URI or null if unavailable.
     */
    public function get_logo_base64(): ?string {
        $branding = $this->get_branding_data();

        if ($branding === null) {
            return null;
        }

        return $branding['logo'];
    }

    /**
     * Get logo dimensions.
     *
     * @return array Array with 'width' and 'height' keys.
     */
    public function get_logo_dimensions(): array {
        $branding = $this->get_branding_data();

        if ($branding === null) {
            return ['width' => 0, 'height' => 0];
        }

        return [
            'width' => $branding['logo_width'],
            'height' => $branding['logo_height'],
        ];
    }

    /**
     * Get the footer text for PDF exports.
     *
     * @return string Footer text.
     */
    public function get_footer_text(): string {
        $branding = $this->get_branding_data();

        if ($branding === null) {
            return get_string('pdf_footer_default', 'report_adeptus_insights');
        }

        return $branding['footer_text'];
    }

    /**
     * Get the logo hash for integrity verification.
     *
     * @return string|null Logo hash or null if unavailable.
     */
    public function get_logo_hash(): ?string {
        $branding = $this->get_branding_data();

        if ($branding === null) {
            return null;
        }

        return $branding['logo_hash'];
    }

    /**
     * Check if branding is available.
     *
     * @return bool True if branding assets are available from backend.
     */
    public function is_branding_available(): bool {
        $branding = $this->get_branding_data();
        return $branding !== null && !empty($branding['logo']);
    }

    /**
     * Get complete branding configuration for PDF generation.
     *
     * Returns all branding settings needed for PDF export in a single call.
     *
     * @return array Branding configuration array.
     */
    public function get_pdf_branding_config(): array {
        $branding = $this->get_branding_data();

        if ($branding === null) {
            return [
                'has_branding' => false,
                'logo' => null,
                'logo_width' => 0,
                'logo_height' => 0,
                'footer_text' => get_string('pdf_footer_default', 'report_adeptus_insights'),
                'company_name' => 'Adeptus 360',
                'header_color' => [41, 128, 185],
                'footer_color' => [127, 140, 141],
            ];
        }

        return [
            'has_branding' => true,
            'logo' => $branding['logo'],
            'logo_width' => $branding['logo_width'],
            'logo_height' => $branding['logo_height'],
            'footer_text' => $branding['footer_text'],
            'company_name' => 'Adeptus 360',
            'header_color' => [41, 128, 185],
            'footer_color' => [127, 140, 141],
        ];
    }

    /**
     * Validate logo data URI format.
     *
     * Ensures the logo is a valid base64-encoded image data URI.
     *
     * @param string $logo Logo data URI.
     * @return bool True if valid format.
     */
    private function validate_logo_format(string $logo): bool {
        // Must be a data URI with image MIME type.
        if (!preg_match('/^data:image\/(png|jpeg|jpg|gif);base64,/', $logo)) {
            return false;
        }

        // Extract and validate base64 data.
        $base64data = preg_replace('/^data:image\/[a-z]+;base64,/', '', $logo);
        $decoded = base64_decode($base64data, true);

        if ($decoded === false) {
            return false;
        }

        // Check minimum size (logo should be at least 100 bytes).
        if (strlen($decoded) < 100) {
            return false;
        }

        // Check maximum size (5MB limit for safety).
        if (strlen($decoded) > 5 * 1024 * 1024) {
            return false;
        }

        return true;
    }

    /**
     * Clear the branding cache.
     *
     * Call this when branding may have been updated on the backend.
     */
    public function clear_cache(): void {
        self::$brandingcache = null;
    }

    // -------------------------------------------------------------------------
    // G9: White-Label / Reseller Branding (Enterprise tier only).
    // -------------------------------------------------------------------------

    /**
     * Check if white-label branding feature is available (Enterprise tier).
     *
     * @return bool True if the site has an Enterprise license.
     */
    public static function is_whitelabel_available(): bool {
        $tier = get_config('report_adeptus_insights', 'license_tier');
        return ($tier === 'enterprise');
    }

    /**
     * Get all white-label branding settings.
     *
     * Returns the locally configured branding overrides set by the admin.
     *
     * @return array Associative array of branding settings.
     */
    public function get_whitelabel_settings(): array {
        $component = 'report_adeptus_insights';

        return [
            'primary_colour'   => get_config($component, 'wl_primary_colour') ?: '#2980b9',
            'secondary_colour' => get_config($component, 'wl_secondary_colour') ?: '#7f8c8d',
            'footer_text'      => get_config($component, 'wl_footer_text') ?: '',
            'header_text'      => get_config($component, 'wl_header_text') ?: '',
            'powered_by'       => (bool) get_config($component, 'wl_powered_by'),
        ];
    }

    /**
     * Save white-label branding settings.
     *
     * @param array $settings Associative array of settings to save.
     */
    public function save_whitelabel_settings(array $settings): void {
        $component = 'report_adeptus_insights';
        $allowed = ['primary_colour', 'secondary_colour', 'footer_text', 'header_text', 'powered_by'];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $settings)) {
                set_config('wl_' . $key, $settings[$key], $component);
            }
        }
    }

    /**
     * Get the white-label logo URL served via pluginfile.php.
     *
     * @return \moodle_url|null URL to the logo or null if none uploaded.
     */
    public function get_whitelabel_logo_url(): ?\moodle_url {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $files = $fs->get_area_files(
            $context->id,
            'report_adeptus_insights',
            'whitelabel_logo',
            0,
            'timemodified DESC',
            false
        );

        if (empty($files)) {
            return null;
        }

        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            false
        );
    }

    /**
     * Get raw logo file for PDF embedding.
     *
     * @return \stored_file|null The stored file or null.
     */
    public function get_whitelabel_logo_file(): ?\stored_file {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $files = $fs->get_area_files(
            $context->id,
            'report_adeptus_insights',
            'whitelabel_logo',
            0,
            'timemodified DESC',
            false
        );

        if (empty($files)) {
            return null;
        }

        return reset($files);
    }

    /**
     * Save an uploaded logo file.
     *
     * @param int $draftitemid The draft area item id from the file picker.
     */
    public function save_whitelabel_logo(int $draftitemid): void {
        $context = \context_system::instance();
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'report_adeptus_insights',
            'whitelabel_logo',
            0,
            ['maxfiles' => 1, 'accepted_types' => ['image']]
        );
    }

    /**
     * Get merged PDF branding config that includes white-label overrides.
     *
     * If white-label is enabled and configured, overrides default branding.
     *
     * @return array Branding configuration array for PDF generation.
     */
    public function get_merged_pdf_branding_config(): array {
        $config = $this->get_pdf_branding_config();

        if (!self::is_whitelabel_available()) {
            return $config;
        }

        $wl = $this->get_whitelabel_settings();

        // Override colours.
        $config['header_color'] = $this->hex_to_rgb($wl['primary_colour']);
        $config['footer_color'] = $this->hex_to_rgb($wl['secondary_colour']);

        // Override footer text.
        if (!empty($wl['footer_text'])) {
            $config['footer_text'] = $wl['footer_text'];
        }

        // Override header text / company name.
        if (!empty($wl['header_text'])) {
            $config['company_name'] = $wl['header_text'];
        }

        // Show/hide powered by.
        $config['show_powered_by'] = $wl['powered_by'];

        // Override logo with local upload if available.
        $logofile = $this->get_whitelabel_logo_file();
        if ($logofile) {
            $mimetype = $logofile->get_mimetype();
            $content = $logofile->get_content();
            $datauri = 'data:' . $mimetype . ';base64,' . base64_encode($content);
            $config['logo'] = $datauri;
            $config['has_branding'] = true;

            $imageinfo = $logofile->get_imageinfo();
            if ($imageinfo) {
                $config['logo_width'] = $imageinfo['width'];
                $config['logo_height'] = $imageinfo['height'];
            }
        }

        return $config;
    }

    /**
     * Convert a hex colour string to an RGB array.
     *
     * @param string $hex Hex colour (e.g. '#2980b9' or '2980b9').
     * @return array [r, g, b] integer values.
     */
    private function hex_to_rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Extract raw image data from base64 data URI.
     *
     * Converts a data URI to raw binary image data suitable for
     * embedding in PDFs via TCPDF.
     *
     * @param string $datauri Base64 data URI.
     * @return string|null Raw binary image data or null on failure.
     */
    public function extract_image_data(string $datauri): ?string {
        if (!$this->validate_logo_format($datauri)) {
            return null;
        }

        $base64data = preg_replace('/^data:image\/[a-z]+;base64,/', '', $datauri);
        $decoded = base64_decode($base64data, true);

        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }

    /**
     * Get MIME type from data URI.
     *
     * @param string $datauri Base64 data URI.
     * @return string|null MIME type or null if invalid.
     */
    public function get_mime_type(string $datauri): ?string {
        if (preg_match('/^data:(image\/[a-z]+);base64,/', $datauri, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get image extension from data URI.
     *
     * @param string $datauri Base64 data URI.
     * @return string Image extension (png, jpg, gif) or 'png' as default.
     */
    public function get_image_extension(string $datauri): string {
        if (preg_match('/^data:image\/(png|jpeg|jpg|gif);base64,/', $datauri, $matches)) {
            $ext = $matches[1];
            // Normalize jpeg to jpg.
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }
        return 'png';
    }
}

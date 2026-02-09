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
    private $installationmanager;

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

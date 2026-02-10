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

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_system;

/**
 * Generic server-side proxy to forward requests to the backend API.
 *
 * Avoids CORS issues by routing browser requests through Moodle's server-side
 * PHP, which can reach the internal Docker backend without Cloudflare Access.
 *
 * Security: Only allows whitelisted path prefixes. Requires Moodle login
 * and the adeptus_insights:view capability.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class proxy_backend_request extends external_api {
    /** Allowed path prefixes for the proxy. */
    private const ALLOWED_PREFIXES = [
        '/chat/',
        '/ai-reports',
        '/reports/categories',
    ];

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'endpoint' => new external_value(PARAM_RAW, 'Backend API path (e.g. /chat/history)'),
            'method' => new external_value(PARAM_ALPHA, 'HTTP method (GET or POST)', VALUE_DEFAULT, 'GET'),
            'body' => new external_value(PARAM_RAW, 'JSON request body for POST requests', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Proxy a request to the backend API.
     *
     * @param string $endpoint The API path (without base URL).
     * @param string $method HTTP method.
     * @param string $body JSON body for POST.
     * @return array JSON-encoded response.
     */
    public static function execute(string $endpoint, string $method = 'GET', string $body = ''): array {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'endpoint' => $endpoint,
            'method' => $method,
            'body' => $body,
        ]);
        $endpoint = $params['endpoint'];
        $method = strtoupper($params['method']);
        $body = $params['body'];

        // Security: Only allow whitelisted prefixes.
        $endpoint = '/' . ltrim($endpoint, '/');
        $allowed = false;
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (strpos($endpoint, $prefix) === 0) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return [
                'success' => false,
                'data' => json_encode([
                    'error' => 'forbidden',
                    'message' => 'This endpoint is not allowed through the proxy.',
                ]),
                'httpcode' => 403,
            ];
        }

        try {
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apiurl = $installationmanager->get_api_url();
            $apikey = $installationmanager->get_api_key();

            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Accept: application/json');
            if ($apikey) {
                $curl->setHeader('X-API-Key: ' . $apikey);
                $curl->setHeader('Authorization: Bearer ' . $apikey);
            }

            $options = [
                'CURLOPT_TIMEOUT' => 30,
                'CURLOPT_RETURNTRANSFER' => true,
            ];

            $url = rtrim($apiurl, '/') . $endpoint;

            if ($method === 'POST') {
                $response = $curl->post($url, $body, $options);
            } else {
                $response = $curl->get($url, [], $options);
            }

            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;

            if ($response !== false && $httpcode > 0) {
                return [
                    'success' => ($httpcode >= 200 && $httpcode < 300),
                    'data' => $response ?: '{}',
                    'httpcode' => $httpcode,
                ];
            }

            return [
                'success' => false,
                'data' => json_encode(['error' => 'connection_failed', 'message' => 'Could not reach backend']),
                'httpcode' => 0,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => json_encode(['error' => 'exception', 'message' => $e->getMessage()]),
                'httpcode' => 500,
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request succeeded'),
            'data' => new external_value(PARAM_RAW, 'JSON-encoded response from backend'),
            'httpcode' => new external_value(PARAM_INT, 'HTTP status code from backend'),
        ]);
    }
}

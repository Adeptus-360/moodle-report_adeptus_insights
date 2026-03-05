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
 * API client for the Report Builder backend endpoints.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Communicates with the Report Builder backend API.
 */
class builder_api_client {

    /** @var string Base API URL. */
    private $baseurl;

    /** @var string API key for authentication. */
    private $apikey;

    /**
     * Constructor.
     */
    public function __construct() {
        global $DB;

        $this->baseurl = api_config::get_backend_url();

        // Get API key from installation settings.
        $settings = $DB->get_record('report_adeptus_insights_settings', [], '*', IGNORE_MULTIPLE);
        $this->apikey = $settings->api_key ?? '';
    }

    /**
     * List all saved builder reports.
     *
     * @return array Array of report objects.
     */
    public function list_reports(): array {
        $response = $this->request('GET', '/builder-reports');
        if (!empty($response->data)) {
            return $response->data;
        }
        return is_array($response) ? $response : [];
    }

    /**
     * Get a single report.
     *
     * @param int $id Report ID.
     * @return object|null Report object.
     */
    public function get_report(int $id): ?object {
        $response = $this->request('GET', '/builder-reports/' . $id);
        return $response->data ?? $response ?? null;
    }

    /**
     * Create a new report.
     *
     * @param array $payload Report data.
     * @return object Created report.
     */
    public function create_report(array $payload): object {
        $response = $this->request('POST', '/builder-reports', $payload);
        return $response->data ?? $response;
    }

    /**
     * Update an existing report.
     *
     * @param int $id Report ID.
     * @param array $payload Updated data.
     * @return object Updated report.
     */
    public function update_report(int $id, array $payload): object {
        $response = $this->request('PUT', '/builder-reports/' . $id, $payload);
        return $response->data ?? $response;
    }

    /**
     * Delete a report.
     *
     * @param int $id Report ID.
     * @return void
     */
    public function delete_report(int $id): void {
        $this->request('DELETE', '/builder-reports/' . $id);
    }

    /**
     * Execute a saved report.
     *
     * @param int $id Report ID.
     * @param int $limit Row limit.
     * @return object Execution result with sql, data, columns.
     */
    public function execute_report(int $id, int $limit = 1000): object {
        $response = $this->request('POST', '/builder-reports/' . $id . '/execute', [
            'limit' => $limit,
        ]);
        return $response->data ?? $response;
    }

    /**
     * Generate SQL preview without executing.
     *
     * @param array $definition Report definition.
     * @param int $limit Row limit.
     * @return object SQL preview result.
     */
    public function generate_sql(array $definition, int $limit = 100): object {
        $response = $this->request('POST', '/builder-reports/generate-sql', [
            'definition' => $definition,
            'limit' => $limit,
        ]);
        return $response->data ?? $response;
    }

    /**
     * Get the data catalog (available entities and columns).
     *
     * @return array Catalog data.
     */
    public function get_data_catalog(): array {
        $response = $this->request('GET', '/builder-reports/catalog');
        $data = $response->data ?? $response;
        return is_array($data) ? $data : (array) $data;
    }

    /**
     * Get tier information for the current installation.
     *
     * @return array Contains 'can_create' bool and optional 'warning' string.
     */
    public function get_tier_info(): array {
        // We determine tier from the list response or a dedicated check.
        // For MVP, attempt a lightweight check: try listing and see if tier limit info is present.
        try {
            $reports = $this->list_reports();
            $count = count($reports);
            // The backend returns 403 on create if limit exceeded,
            // so we just check count heuristically for Community (1 report max).
            // A more robust approach would call a dedicated tier endpoint.
            return [
                'can_create' => true,
                'warning' => '',
                'count' => $count,
            ];
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'saved_report_limit_exceeded') !== false) {
                return [
                    'can_create' => false,
                    'warning' => get_string('builder_tier_limit_reached', 'report_adeptus_insights'),
                    'count' => 0,
                ];
            }
            throw $e;
        }
    }

    /**
     * Make an HTTP request to the backend API.
     *
     * @param string $method HTTP method.
     * @param string $path API path (appended to base URL).
     * @param array|null $data Request body data.
     * @return object Decoded JSON response.
     * @throws \moodle_exception On HTTP error.
     */
    private function request(string $method, string $path, ?array $data = null): object {
        $url = $this->baseurl . $path;

        $curl = new \curl();
        $curl->setHeader([
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apikey,
        ]);

        $options = [
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_RETURNTRANSFER' => true,
        ];

        $body = $data ? json_encode($data) : '';

        switch (strtoupper($method)) {
            case 'GET':
                $response = $curl->get($url, [], $options);
                break;
            case 'POST':
                $response = $curl->post($url, $body, $options);
                break;
            case 'PUT':
                $response = $curl->put($url, $body, $options);
                break;
            case 'DELETE':
                $response = $curl->delete($url, [], $options);
                break;
            default:
                throw new \moodle_exception('error_method_not_allowed', 'report_adeptus_insights');
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;
        $decoded = json_decode($response);

        if ($httpcode >= 400) {
            $errormsg = $decoded->message ?? $decoded->error ?? 'API error (HTTP ' . $httpcode . ')';
            throw new \moodle_exception('error_api_request_failed', 'report_adeptus_insights', '', $errormsg);
        }

        return $decoded ?? (object) [];
    }
}

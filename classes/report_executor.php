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

namespace report_adeptus_insights;

/**
 * Shared report executor for fetching, running, and exporting reports.
 *
 * Used by the scheduled reports task and (in Phase 2) by download.php.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_executor {

    /** @var int Maximum rows to return from a report query. */
    const MAX_ROWS = 100000;

    /**
     * Fetch a report definition from the backend API.
     *
     * @param string $reportid The report identifier.
     * @return \stdClass The report definition object.
     * @throws \moodle_exception If the report cannot be fetched.
     */
    public function fetch_report_definition(string $reportid): \stdClass {
        global $DB;

        // Get API configuration from installation settings.
        $settings = $DB->get_record('report_adeptus_insights_settings', [], '*', IGNORE_MULTIPLE);
        if (!$settings || empty($settings->api_key) || empty($settings->api_url)) {
            throw new \moodle_exception('error_installation_not_configured', 'report_adeptus_insights');
        }

        $url = rtrim($settings->api_url, '/') . '/api/reports/' . urlencode($reportid);

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);
        $headers = [
            'Authorization: Bearer ' . $settings->api_key,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        // Add installation ID if available.
        if (!empty($settings->installation_id)) {
            $headers[] = 'X-Installation-Id: ' . $settings->installation_id;
        }

        $curl->setHeader($headers);
        $response = $curl->get($url);
        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode !== 200 || empty($response)) {
            throw new \moodle_exception(
                'error_fetch_report_definition_failed',
                'report_adeptus_insights'
            );
        }

        $data = json_decode($response);
        if (!$data) {
            throw new \moodle_exception(
                'error_invalid_backend_response',
                'report_adeptus_insights'
            );
        }

        // Normalise: backend may return report directly or wrapped.
        if (isset($data->report)) {
            return $data->report;
        }
        return $data;
    }

    /**
     * Execute a report's SQL query on the local Moodle database.
     *
     * @param \stdClass $report The report definition (must have ->sqlquery).
     * @param array $params Report parameters to bind.
     * @return array Array of row objects.
     * @throws \moodle_exception If the query fails or is not a SELECT.
     */
    public function execute_report(\stdClass $report, array $params = []): array {
        global $DB;

        $sql = $report->sqlquery ?? $report->sql ?? '';
        if (empty($sql)) {
            throw new \moodle_exception('error_sql_required', 'report_adeptus_insights');
        }

        // Safety: only allow SELECT queries.
        $trimmed = ltrim($sql);
        if (stripos($trimmed, 'SELECT') !== 0) {
            throw new \moodle_exception('error_sql_only_select', 'report_adeptus_insights');
        }

        // Check for dangerous keywords.
        $dangerous = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE'];
        foreach ($dangerous as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $sql)) {
                throw new \moodle_exception('error_sql_dangerous', 'report_adeptus_insights');
            }
        }

        // Bind parameters — replace named placeholders from report params.
        $sqlparams = [];
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $sqlparams[$key] = $value;
            }
        }

        // Replace {tablename} Moodle-style prefixes.
        $records = $DB->get_records_sql($sql, $sqlparams, 0, self::MAX_ROWS);

        return array_values($records);
    }

    /**
     * Extract column headers from the first row of results.
     *
     * @param array $rows Array of result row objects.
     * @return array Array of header strings.
     */
    public function get_headers(array $rows): array {
        if (empty($rows)) {
            return [];
        }
        return array_keys((array) reset($rows));
    }

    /**
     * Export rows to a CSV temp file.
     *
     * @param array $rows Array of result row objects.
     * @param array $headers Column headers.
     * @return string Path to the generated temp file.
     */
    public function export_to_csv(array $rows, array $headers): string {
        $tmpfile = tempnam(sys_get_temp_dir(), 'adeptus_sched_');

        $handle = fopen($tmpfile, 'w');
        if (!$handle) {
            throw new \moodle_exception('error_export_report', 'report_adeptus_insights', '', 'Cannot create temp file');
        }

        // Write BOM for Excel compatibility.
        fwrite($handle, "\xEF\xBB\xBF");

        // Write headers.
        $formattedheaders = array_map(function($h) {
            return ucwords(str_replace('_', ' ', $h));
        }, $headers);
        fputcsv($handle, $formattedheaders);

        // Write data rows.
        foreach ($rows as $row) {
            fputcsv($handle, array_values((array) $row));
        }

        fclose($handle);

        return $tmpfile;
    }

    /**
     * Clean up a temporary file.
     *
     * @param string $filepath Path to the file to delete.
     */
    public function cleanup_temp_file(string $filepath): void {
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
    }
}

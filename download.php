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
 * Export report data download endpoint.
 *
 * This endpoint handles binary file exports (CSV, Excel, JSON, PDF) which cannot be
 * converted to Moodle External Services. External services can only return JSON data,
 * not binary content with Content-Disposition headers required for file downloads.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
defined('AJAX_SCRIPT') || define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
// phpcs:enable

// Require login and capability.
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/adeptus_insights:view', $context);

// Get parameters.
$reportid = required_param('reportid', PARAM_TEXT);
$format = required_param('format', PARAM_ALPHA);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

$chartdata = optional_param('chart_data', '', PARAM_RAW);

$charttype = optional_param('chart_type', 'bar', PARAM_ALPHA);
$chartimage = optional_param('chart_image', '', PARAM_RAW);

// Validate chart_data: must be valid JSON if provided.
if (!empty($chartdata)) {
    $decodedchartdata = json_decode($chartdata, true);
    if ($decodedchartdata === null && json_last_error() !== JSON_ERROR_NONE) {
        $chartdata = '';
    }
}

// Validate chart image if provided: must be a valid base64 data URI.
if (!empty($chartimage)) {
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $chartimage)) {
        $chartimage = '';
    }
    $imagesize = strlen($chartimage);
    if ($imagesize > 2000000) {
        $chartimage = '';
    }
}


// Validate session key.
if (!confirm_sesskey($sesskey)) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => get_string('error_invalid_sesskey', 'report_adeptus_insights')]);
        exit;
    } else {
        throw new moodle_exception('invalidsesskey');
    }
}


try {
    // Check if we have report data from frontend.

    $reportdatajson = optional_param('report_data', '', PARAM_RAW);

    // Validate report_data: must be valid JSON if provided.
    if (!empty($reportdatajson)) {
        $testreportdata = json_decode($reportdatajson, true);
        if ($testreportdata === null && json_last_error() !== JSON_ERROR_NONE) {
            $reportdatajson = '';
        }
    }

    // SAFETY CHECK: Refuse to process frontend data if it's too large (>10MB)
    // Large datasets should be regenerated from backend instead.
    $maxfrontenddatasize = 10 * 1024 * 1024; // 10MB.
    $datasize = strlen($reportdatajson);

    if ($datasize > $maxfrontenddatasize) {
        $hasfrontenddata = false; // Force backend regeneration.
    } else {
        $hasfrontenddata = !empty($reportdatajson);
    }


    $resultsarray = [];
    $headers = [];
    $reportparams = [];
    $report = new stdClass();



    // Try to use frontend data first (preferred for small/medium datasets).
    if ($hasfrontenddata) {
        $reportdata = json_decode($reportdatajson, true);

        if ($reportdata && isset($reportdata['results']) && isset($reportdata['headers'])) {
            $resultsarray = $reportdata['results'];
            $headers = $reportdata['headers'];

            // Normalize results from cells format {cells: [{key, value}, ...]} to flat {key: value, ...}.
            $normalizedresults = [];
            foreach ($resultsarray as $row) {
                if (isset($row['cells']) && is_array($row['cells'])) {
                    $flatrow = [];
                    foreach ($row['cells'] as $cell) {
                        $flatrow[$cell['key']] = $cell['value'] ?? '';
                    }
                    $normalizedresults[] = $flatrow;
                } else {
                    $normalizedresults[] = $row;
                }
            }
            $resultsarray = $normalizedresults;

            // Create report object with metadata from frontend.
            $report->name = $reportdata['report_name'] ?? $reportid;
            $report->category = $reportdata['report_category'] ?? '';
            $report->charttype = $reportdata['chart_type'] ?? 'bar';
        } else {
            $hasfrontenddata = false; // Force regeneration.
        }
    }

    // If no frontend data or frontend data invalid, regenerate from backend.
    if (!$hasfrontenddata || empty($resultsarray)) {
        // Fetch report definition from Laravel backend (same as generate_report.php).

        $backendenabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
        $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();
        $apitimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;

        if (!$backendenabled) {
            throw new Exception(get_string('error_backend_disabled_no_data', 'report_adeptus_insights'));
        }

        // Get API key.
        $installationmanager = new \report_adeptus_insights\installation_manager();
        $apikey = $installationmanager->get_api_key();

        // Fetch report definition.
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');
        $curl->setHeader('X-API-Key: ' . $apikey);
        $options = [
            'CURLOPT_TIMEOUT' => $apitimeout,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        $response = $curl->get($backendapiurl . '/reports/definitions', [], $options);
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if (!$response || $httpcode !== 200) {
            throw new Exception(get_string('error_fetch_report_definition_failed', 'report_adeptus_insights'));
        }

        $backenddata = json_decode($response, true);
        if (!$backenddata || !$backenddata['success']) {
            throw new Exception(get_string('error_invalid_backend_response', 'report_adeptus_insights'));
        }

        // Find the report.
        $backendreport = null;
        foreach ($backenddata['data'] as $r) {
            if (trim($r['name']) === trim($reportid)) {
                $backendreport = $r;
                break;
            }
        }

        if (!$backendreport) {
            throw new Exception(get_string('error_report_not_found_name', 'report_adeptus_insights', $reportid));
        }

        // Create report object.
        $report->name = $backendreport['name'];
        $report->category = $backendreport['category'] ?? '';
        $report->charttype = $backendreport['charttype'] ?? 'bar';
        $report->sqlquery = $backendreport['sqlquery'];
        $report->parameters = json_encode($backendreport['parameters'] ?? []);

        // Collect parameters from request (same logic as generate_report.php).
        // These values are only used as SQL bind parameters via $DB->get_records_sql(),
        // which handles escaping. We apply clean_param for additional safety.
        if (!empty($backendreport['parameters'])) {
            foreach ($backendreport['parameters'] as $paramdef) {
                if (isset($paramdef['name'])) {
                    $paramvalue = optional_param($paramdef['name'], '', PARAM_RAW);
                    if (!empty($paramvalue)) {
                        $reportparams[clean_param($paramdef['name'], PARAM_ALPHANUMEXT)] = clean_param($paramvalue, PARAM_TEXT);
                    }
                }
            }
        }

        // Collect common parameters with appropriate PARAM types.
        $commonparamtypes = [
            'courseid' => PARAM_INT,
            'minimum_grade' => PARAM_FLOAT,
            'categoryid' => PARAM_INT,
            'userid' => PARAM_INT,
            'roleid' => PARAM_INT,
            'startdate' => PARAM_TEXT,
            'enddate' => PARAM_TEXT,
        ];
        foreach ($commonparamtypes as $paramname => $paramtype) {
            $paramvalue = optional_param($paramname, '', $paramtype);
            if (!empty($paramvalue) && !isset($reportparams[$paramname])) {
                $reportparams[$paramname] = $paramvalue;
            }
        }

        // Execute SQL query.
        $sql = $report->sqlquery;

        // Add safety limit.
        $safetylimit = 100000;
        $haslimit = preg_match('/\bLIMIT\s+\d+/i', $sql);
        if (!$haslimit) {
            $sql = rtrim(rtrim($sql), ';') . " LIMIT $safetylimit";
        }

        // Extract parameter names and build parameter array.
        $requiredparams = [];
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
        if (!empty($matches[1])) {
            $requiredparams = array_unique($matches[1]);
        }

        // Convert named to positional parameters.
        $positionalsql = $sql;
        $sqlparamsordered = [];
        foreach ($requiredparams as $paramname) {
            if (!isset($reportparams[$paramname])) {
                throw new Exception(get_string('error_missing_parameter', 'report_adeptus_insights', $paramname));
            }
            $positionalsql = preg_replace('/:' . $paramname . '\b/', '?', $positionalsql, 1);
            $sqlparamsordered[] = $reportparams[$paramname];
        }

        // Execute query.
        $results = $DB->get_records_sql($positionalsql, $sqlparamsordered);

        // Convert to array.
        foreach ($results as $row) {
            $resultsarray[] = (array)$row;
        }

        // Get headers.
        if (!empty($resultsarray)) {
            $headers = array_keys($resultsarray[0]);
        }
    }

    // PDF-specific row limit check.
    // PDFs cannot realistically render massive datasets due to memory and file size constraints.
    $pdfmaxrows = 5000;
    if ($format === 'pdf' && count($resultsarray) > $pdfmaxrows) {
        $rowcount = count($resultsarray);

        // Return user-friendly error.
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'dataset_too_large',
            'title' => get_string('export_dataset_too_large_title', 'report_adeptus_insights'),
            'message' => get_string(
                'export_dataset_too_large',
                'report_adeptus_insights',
                (object)['rows' => $rowcount, 'limit' => $pdfmaxrows]
            ),
        ]);
        exit;
    }

    /**
     * Convert header to title case format.
     *
     * @param string $header The header to format.
     * @return string Formatted header in title case.
     */
    function report_adeptus_insights_format_header($header) {
        // Convert to title case: capitalize first letter of each word.
        return ucwords(str_replace('_', ' ', strtolower($header)));
    }

    // Prepare table data for export.
    $tabledata = [];
    if (!empty($resultsarray)) {
        // Add headers as first row with title case.
        $formattedheaders = array_map('report_adeptus_insights_format_header', $headers);
        $tabledata[] = $formattedheaders;

        // Add data rows.
        foreach ($resultsarray as $row) {
            $tabledata[] = array_values($row);
        }
    } else {
        $tabledata[] = [get_string('pdf_no_data_found', 'report_adeptus_insights')];
    }

    // Generate chart data using the same logic as generate_report.php.
    $chartexportdata = null;
    if (!empty($resultsarray) && !empty($headers)) {
        // Find the best columns for labels and values.
        $labelcolumn = $headers[0] ?? 'id';
        $valuecolumn = null;

        // Analyze all columns to find numeric ones and their value ranges.
        $numericcolumns = [];
        $columnstats = [];
        $mbcolumn = null; // For (mb) column priority.

        foreach ($headers as $header) {
            $columnvalues = array_column($resultsarray, $header);
            $numericvalues = [];
            $isnumericcolumn = true;

            // Check if all values in this column are numeric.
            foreach ($columnvalues as $value) {
                if (is_numeric($value)) {
                    $numericvalues[] = (float)$value;
                } else if (is_string($value) && is_numeric(trim($value))) {
                    $numericvalues[] = (float)trim($value);
                } else {
                    $isnumericcolumn = false;
                    break;
                }
            }

            // If column is numeric, calculate its statistics.
            if ($isnumericcolumn && !empty($numericvalues)) {
                $numericcolumns[] = $header;
                $columnstats[$header] = [
                    'max' => max($numericvalues),
                    'min' => min($numericvalues),
                    'sum' => array_sum($numericvalues),
                    'count' => count($numericvalues),
                    'avg' => array_sum($numericvalues) / count($numericvalues),
                ];

                // Special case: Check if column name contains "(mb)".
                if (strpos($header, '(mb)') !== false) {
                    $mbcolumn = $header;
                }
            }
        }

        // Select the column with priority: (mb) column first, then highest maximum value.
        if (!empty($mbcolumn)) {
            $valuecolumn = $mbcolumn;
        } else if (!empty($numericcolumns)) {
            $maxvalue = 0;
            foreach ($numericcolumns as $column) {
                if ($columnstats[$column]['max'] > $maxvalue) {
                    $maxvalue = $columnstats[$column]['max'];
                    $valuecolumn = $column;
                }
            }
        } else {
            // Fallback to second column if no numeric columns found.
            $valuecolumn = $headers[1] ?? 'value';
        }

        // Convert values to numbers if they're strings.
        $chartvalues = array_column($resultsarray, $valuecolumn);
        $chartvalues = array_map(function ($value) {
            return is_numeric($value) ? (float)$value : (is_string($value) && is_numeric(trim($value)) ? (float)trim($value) : 0);
        }, $chartvalues);

        // Generate colors based on chart type.
        $colors = report_adeptus_insights_generate_chart_colors(count($chartvalues), $report->charttype);

        // Create chart data structure.
        $chartdatastructure = [
            'labels' => array_column($resultsarray, $labelcolumn),
            'datasets' => [
                [
                    'label' => $report->name,
                    'data' => $chartvalues,
                    'backgroundColor' => $colors,
                    'borderColor' => report_adeptus_insights_adjust_colors($colors, -20),
                    'borderWidth' => 2,
                ],
            ],
            'axis_labels' => [
                'x_axis' => $labelcolumn,
                'y_axis' => $valuecolumn,
            ],
        ];

        // Convert chart data to exportable format.
        $chartexportdata = [];
        $chartexportheaders = ['Label', 'Value'];
        $chartexportdata[] = $chartexportheaders;

        $labels = $chartdatastructure['labels'];
        $values = $chartdatastructure['datasets'][0]['data'] ?? [];

        for ($i = 0; $i < count($labels); $i++) {
            $chartexportdata[] = [
                $labels[$i] ?? '',
                $values[$i] ?? '',
            ];
        }
    }

    // Generate filename.
    $cleanreportname = preg_replace('/[^a-zA-Z0-9_-]/', '_', $report->name);
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $cleanreportname . '_' . $timestamp;

    // Handle different export formats.
    switch ($format) {
        case 'csv':
            // CSV: table only.
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Cache-Control: max-age=0');

            $output = fopen('php://output', 'w');
            foreach ($tabledata as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            break;

        case 'excel':
            // Excel: Table Data on sheet 1, Chart Visualization on sheet 2.
            // Use CSV format that Excel can open reliably.
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Cache-Control: max-age=0');

            echo report_adeptus_insights_generate_excel_csv($reportid, $tabledata, $chartexportdata, $reportparams);
            break;

        case 'json':
            // JSON: as is (current working format).
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');

            $jsondata = [
                'report_name' => $report->name,
                'report_category' => $report->category,
                'generated_at' => date('Y-m-d H:i:s'),
                'parameters' => $reportparams,
                'headers' => $headers,
                'table_data' => array_slice($tabledata, 1), // Remove header row for JSON.
                'chart_data' => $chartexportdata ? array_slice($chartexportdata, 1) : null,
            ];

            echo json_encode($jsondata, JSON_PRETTY_PRINT);
            break;

        case 'pdf':
            // PDF: table on page 1, chart on page 2.
            // Generate actual PDF using TCPDF.
            try {
                $pdfcontent = report_adeptus_insights_generate_pdf(
                    $reportid,
                    $tabledata,
                    $chartexportdata,
                    $reportparams,
                    $chartimage
                );

                if ($pdfcontent === false || empty($pdfcontent)) {
                    throw new Exception(get_string('error_pdf_generation_failed', 'report_adeptus_insights'));
                }


                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                header('Cache-Control: max-age=0');
                header('Content-Length: ' . strlen($pdfcontent));

                echo $pdfcontent;
            } catch (Exception $pdferror) {
                // Return JSON error instead of corrupted PDF.
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => get_string('pdf_generation_failed', 'report_adeptus_insights', $pdferror->getMessage()),
                ]);
            }
            break;

        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => get_string('error_unsupported_format', 'report_adeptus_insights')]);
            break;
    }
} catch (Exception $e) {
    // Always return JSON error for AJAX requests.
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => get_string('error_export_report', 'report_adeptus_insights', $e->getMessage()),
    ]);
}

/**
 * Generate branded PDF with Adeptus 360 branding.
 *
 * This function generates a PDF with secure branding fetched from the backend.
 * The branding (logo, footer) cannot be tampered with locally as it is
 * retrieved server-side on each export.
 *
 * @param string $reportname Report title.
 * @param array $tabledata Table data with headers as first row.
 * @param array $chartdata Chart data (unused, kept for compatibility).
 * @param array $reportparams Report parameters.
 * @param string $chartimage Base64 encoded chart image.
 * @return string PDF content.
 * @throws Exception If branding is unavailable or PDF generation fails.
 */
function report_adeptus_insights_generate_pdf($reportname, $tabledata, $chartdata, $reportparams, $chartimage = '') {
    global $CFG;

    // Load branding manager and get branding configuration.

    $brandingmanager = new \report_adeptus_insights\branding_manager();

    // SECURITY: Branding is REQUIRED - fail if backend is unreachable.
    // This prevents PDF exports without proper Adeptus 360 branding.
    if (!$brandingmanager->is_branding_available()) {
        throw new Exception(get_string('export_branding_required', 'report_adeptus_insights'));
    }

    // Get branding configuration from backend.
    $brandingconfig = $brandingmanager->get_pdf_branding_config();

    try {
        // Create branded PDF instance.
        $pdf = new \report_adeptus_insights\branded_pdf($brandingconfig, 'P', 'mm', 'A4');
        $pdf->set_report_title($reportname);
        $pdf->SetTitle($reportname);

        // Add first page for table data.
        $pdf->AddPage();

        // Add parameters section if present.
        if (!empty($reportparams)) {
            $pdf->add_parameters_section($reportparams);
        }

        // Add table data section.
        $pdf->add_section_title(get_string('pdf_table_data', 'report_adeptus_insights'));

        if (!empty($tabledata) && count($tabledata) > 1) {
            // First row is headers, rest is data.
            $headers = $tabledata[0];
            $data = array_slice($tabledata, 1);
            $pdf->add_data_table($headers, $data);
        } else {
            $pdf->add_no_data_message(get_string('pdf_no_data', 'report_adeptus_insights'));
        }

        // Add second page for chart visualization.
        $pdf->AddPage();
        $pdf->add_section_title(get_string('pdf_chart_visualization', 'report_adeptus_insights'));

        if (!empty($chartimage)) {
            $chartadded = $pdf->add_chart_image($chartimage);
            if (!$chartadded) {
                $pdf->add_no_data_message(get_string('pdf_no_chart', 'report_adeptus_insights'));
            }
        } else {
            $pdf->add_no_data_message(get_string('pdf_no_chart', 'report_adeptus_insights'));
        }

        // Generate PDF content.
        $pdfoutput = $pdf->get_pdf_content();

        if (empty($pdfoutput)) {
            throw new Exception(get_string('error_pdf_generation_failed', 'report_adeptus_insights'));
        }

        return $pdfoutput;
    } catch (Exception $e) {
        throw new Exception(get_string('pdf_generation_failed', 'report_adeptus_insights', $e->getMessage()));
    }
}

/**
 * Generate Excel-compatible CSV file with report data.
 *
 * @param string $reportname Report title.
 * @param array $tabledata Table data.
 * @param array $chartdata Chart data.
 * @param array $reportparams Report parameters.
 * @return string CSV content.
 */
function report_adeptus_insights_generate_excel_csv($reportname, $tabledata, $chartdata, $reportparams) {
    $output = '';

    // Add report header.
    $output .= '"' . str_replace('"', '""', $reportname) . '"' . "\n";
    $output .= '"Generated on: ' . date('Y-m-d H:i:s') . '"' . "\n";

    // Add parameters.
    if (!empty($reportparams)) {
        $output .= '"Parameters:"' . "\n";
        foreach ($reportparams as $key => $value) {
            $output .= '"' . str_replace('"', '""', $key . ': ' . $value) . '"' . "\n";
        }
        $output .= "\n"; // Empty line for spacing.
    }

    // Add table data.
    $output .= '"Table Data:"' . "\n";
    if (!empty($tabledata)) {
        foreach ($tabledata as $row) {
            $csvrow = [];
            foreach ($row as $cell) {
                $csvrow[] = '"' . str_replace('"', '""', $cell) . '"';
            }
            $output .= implode(',', $csvrow) . "\n";
        }
    } else {
        $output .= '"' . get_string('pdf_no_table_data', 'report_adeptus_insights') . '"' . "\n";
    }

    // Add separator for chart data.
    $output .= "\n";
    $output .= '"Chart Data:"' . "\n";

    // Add chart data.
    if (!empty($chartdata)) {
        foreach ($chartdata as $row) {
            $csvrow = [];
            foreach ($row as $cell) {
                $csvrow[] = '"' . str_replace('"', '""', $cell) . '"';
            }
            $output .= implode(',', $csvrow) . "\n";
        }
    } else {
        $output .= '"' . get_string('pdf_no_chart_data', 'report_adeptus_insights') . '"' . "\n";
    }

    return $output;
}



/**
 * Generate colors for charts based on chart type and data count.
 *
 * @param int $count Number of data points.
 * @param string $charttype The chart type.
 * @return array Array of color values.
 */
function report_adeptus_insights_generate_chart_colors($count, $charttype) {
    $basecolors = [
        '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1',
        '#fd7e14', '#20c997', '#e83e8c', '#6c757d', '#17a2b8',
        '#6610f2', '#fd7e14', '#20c997', '#e83e8c', '#6c757d',
    ];

    $charttype = strtolower($charttype);

    if ($charttype === 'pie' || $charttype === 'donut' || $charttype === 'polar') {
        // Generate distinct colors for each data point.
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $basecolors[$i % count($basecolors)];
        }
        return $colors;
    } else {
        // Use single color for bar, line, radar charts.
        return [$basecolors[0]];
    }
}

/**
 * Adjust colors (lighten or darken) for border colors.
 *
 * @param array|string $colors Color or array of colors.
 * @param int $amount Amount to adjust (negative = darken).
 * @return array|string Adjusted color(s).
 */
function report_adeptus_insights_adjust_colors($colors, $amount) {
    if (is_array($colors)) {
        return array_map(function ($color) use ($amount) {
            return report_adeptus_insights_adjust_color($color, $amount);
        }, $colors);
    } else {
        return report_adeptus_insights_adjust_color($colors, $amount);
    }
}

/**
 * Adjust a single color by lightening or darkening it.
 *
 * @param string $color Hex color value.
 * @param int $amount Amount to adjust (negative = darken).
 * @return string Adjusted hex color.
 */
function report_adeptus_insights_adjust_color($color, $amount) {
    // Remove # if present.
    $color = ltrim($color, '#');

    // Convert to RGB.
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));

    // Adjust each component.
    $r = max(0, min(255, $r + $amount));
    $g = max(0, min(255, $g + $amount));
    $b = max(0, min(255, $b + $amount));

    // Convert back to hex.
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

exit;

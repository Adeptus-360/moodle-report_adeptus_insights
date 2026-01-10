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
 * Export report data AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);


try {
    require_once(__DIR__ . '/../../../config.php');

    // Note: dataformatlib.php no longer exists in Moodle 4.x+, export functions are defined below

    // Require login and capability
    require_login();

    $context = context_system::instance();
    $PAGE->set_context($context);
    require_capability('report/adeptus_insights:view', $context);

    // Get parameters
    $reportid = required_param('reportid', PARAM_TEXT);

    $format = required_param('format', PARAM_ALPHA);

    $sesskey = required_param('sesskey', PARAM_ALPHANUM);
} catch (Exception $e) {
    throw $e;
}


$view = optional_param('view', 'table', PARAM_ALPHA);

$chart_data = optional_param('chart_data', '', PARAM_RAW);

$chart_type = optional_param('chart_type', 'bar', PARAM_ALPHA);
$chart_image = optional_param('chart_image', '', PARAM_RAW);

// Validate chart image if provided
if (!empty($chart_image)) {
    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $chart_image)) {
        $chart_image = '';
    }
    $image_size = strlen($chart_image);
    if ($image_size > 2000000) {
        $chart_image = '';
    }
}


// Validate session key
if (!confirm_sesskey($sesskey)) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid session key']);
    } else {
        print_error('invalidsesskey');
    }
    exit;
}


try {

    // Check if we have report data from frontend

    $report_data_json = optional_param('report_data', '', PARAM_RAW);


    // SAFETY CHECK: Refuse to process frontend data if it's too large (>10MB)
    // Large datasets should be regenerated from backend instead
    $MAX_FRONTEND_DATA_SIZE = 10 * 1024 * 1024; // 10MB
    $data_size = strlen($report_data_json);

    if ($data_size > $MAX_FRONTEND_DATA_SIZE) {
        $has_frontend_data = false; // Force backend regeneration
    } else {
        $has_frontend_data = !empty($report_data_json);
    }


    $results_array = [];
    $headers = [];
    $report_params = [];
    $report = new stdClass();



    // Try to use frontend data first (preferred for small/medium datasets)
    if ($has_frontend_data) {
        $report_data = json_decode($report_data_json, true);

        if ($report_data && isset($report_data['results']) && isset($report_data['headers'])) {
            $results_array = $report_data['results'];
            $headers = $report_data['headers'];

            // Create report object with metadata from frontend
            $report->name = $report_data['report_name'] ?? $reportid;
            $report->category = $report_data['report_category'] ?? '';
            $report->charttype = $report_data['chart_type'] ?? 'bar';
        } else {
            $has_frontend_data = false; // Force regeneration
        }
    }

    // If no frontend data or frontend data invalid, regenerate from backend
    if (!$has_frontend_data || empty($results_array)) {

        // Fetch report definition from Laravel backend (same as generate_report.php)
        require_once(__DIR__ . '/../classes/api_config.php');
        require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');

        $backendEnabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
        $backendApiUrl = \report_adeptus_insights\api_config::get_backend_url();
        $apiTimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;

        if (!$backendEnabled) {
            throw new Exception('Backend API is disabled and no data provided');
        }

        // Get API key
        $installation_manager = new \report_adeptus_insights\installation_manager();
        $api_key = $installation_manager->get_api_key();

        // Fetch report definition
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/reports/definitions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $apiTimeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $api_key
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            throw new Exception('Failed to fetch report definition from backend');
        }

        $backendData = json_decode($response, true);
        if (!$backendData || !$backendData['success']) {
            throw new Exception('Invalid response from backend API');
        }

        // Find the report
        $backendReport = null;
        foreach ($backendData['data'] as $r) {
            if (trim($r['name']) === trim($reportid)) {
                $backendReport = $r;
                break;
            }
        }

        if (!$backendReport) {
            throw new Exception('Report not found: ' . $reportid);
        }

        // Create report object
        $report->name = $backendReport['name'];
        $report->category = $backendReport['category'] ?? '';
        $report->charttype = $backendReport['charttype'] ?? 'bar';
        $report->sqlquery = $backendReport['sqlquery'];
        $report->parameters = json_encode($backendReport['parameters'] ?? []);

        // Collect parameters from request (same logic as generate_report.php)
        if (!empty($backendReport['parameters'])) {
            foreach ($backendReport['parameters'] as $param_def) {
                if (isset($param_def['name'])) {
                    $param_value = optional_param($param_def['name'], '', PARAM_RAW);
                    if (!empty($param_value)) {
                        $report_params[$param_def['name']] = $param_value;
                    }
                }
            }
        }

        // Collect common parameters
        $common_params = ['courseid', 'minimum_grade', 'categoryid', 'userid', 'roleid', 'startdate', 'enddate'];
        foreach ($common_params as $param_name) {
            $param_value = optional_param($param_name, '', PARAM_RAW);
            if (!empty($param_value) && !isset($report_params[$param_name])) {
                $report_params[$param_name] = $param_value;
            }
        }

        // Execute SQL query
        $sql = $report->sqlquery;

        // Add safety limit
        $SAFETY_LIMIT = 100000;
        $has_limit = preg_match('/\bLIMIT\s+\d+/i', $sql);
        if (!$has_limit) {
            $sql = rtrim(rtrim($sql), ';') . " LIMIT $SAFETY_LIMIT";
        }

        // Extract parameter names and build parameter array
        $required_params = [];
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
        if (!empty($matches[1])) {
            $required_params = array_unique($matches[1]);
        }

        // Convert named to positional parameters
        $positional_sql = $sql;
        $sql_params_ordered = [];
        foreach ($required_params as $param_name) {
            if (!isset($report_params[$param_name])) {
                throw new Exception('Missing required parameter: ' . $param_name);
            }
            $positional_sql = preg_replace('/:' . $param_name . '\b/', '?', $positional_sql, 1);
            $sql_params_ordered[] = $report_params[$param_name];
        }

        // Execute query
        $results = $DB->get_records_sql($positional_sql, $sql_params_ordered);

        // Convert to array
        foreach ($results as $row) {
            $results_array[] = (array)$row;
        }

        // Get headers
        if (!empty($results_array)) {
            $headers = array_keys($results_array[0]);
        }

    }

    // PDF-specific row limit check
    // PDFs cannot realistically render massive datasets due to memory and file size constraints
    $PDF_MAX_ROWS = 5000;
    if ($format === 'pdf' && count($results_array) > $PDF_MAX_ROWS) {
        $row_count = count($results_array);

        // Return user-friendly error
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'dataset_too_large',
            'title' => 'Export Restriction',
            'message' => "This report contains 5000+ rows, which exceeds the PDF export limit of $PDF_MAX_ROWS rows. Please use CSV, Excel, or JSON export for large datasets, or add filters to reduce the result set."
        ]);
        exit;
    }

    // Helper function to convert headers to title case
    function format_header($header) {
        // Convert to title case: capitalize first letter of each word
        return ucwords(str_replace('_', ' ', strtolower($header)));
    }

    // Prepare table data for export
    $table_data = [];
    if (!empty($results_array)) {
        // Add headers as first row with title case
        $formatted_headers = array_map('format_header', $headers);
        $table_data[] = $formatted_headers;
        
        // Add data rows
        foreach ($results_array as $row) {
            $table_data[] = array_values($row);
        }
    } else {
        $table_data[] = ['No data found'];
    }

    // Generate chart data using the same logic as generate_report.php
    $chart_export_data = null;
    if (!empty($results_array) && !empty($headers)) {
        // Find the best columns for labels and values
        $label_column = $headers[0] ?? 'id';
        $value_column = null;
        
        // Analyze all columns to find numeric ones and their value ranges
        $numeric_columns = [];
        $column_stats = [];
        $mb_column = null; // For (mb) column priority
        
        foreach ($headers as $header) {
            $column_values = array_column($results_array, $header);
            $numeric_values = [];
            $is_numeric_column = true;
            
            // Check if all values in this column are numeric
            foreach ($column_values as $value) {
                if (is_numeric($value)) {
                    $numeric_values[] = (float)$value;
                } elseif (is_string($value) && is_numeric(trim($value))) {
                    $numeric_values[] = (float)trim($value);
                } else {
                    $is_numeric_column = false;
                    break;
                }
            }
            
            // If column is numeric, calculate its statistics
            if ($is_numeric_column && !empty($numeric_values)) {
                $numeric_columns[] = $header;
                $column_stats[$header] = [
                    'max' => max($numeric_values),
                    'min' => min($numeric_values),
                    'sum' => array_sum($numeric_values),
                    'count' => count($numeric_values),
                    'avg' => array_sum($numeric_values) / count($numeric_values)
                ];
                
                // Special case: Check if column name contains "(mb)"
                if (strpos($header, '(mb)') !== false) {
                    $mb_column = $header;
                }
            }
        }
        
        // Select the column with priority: (mb) column first, then highest maximum value
        if (!empty($mb_column)) {
            $value_column = $mb_column;
        } elseif (!empty($numeric_columns)) {
            $max_value = 0;
            foreach ($numeric_columns as $column) {
                if ($column_stats[$column]['max'] > $max_value) {
                    $max_value = $column_stats[$column]['max'];
                    $value_column = $column;
                }
            }
        } else {
            // Fallback to second column if no numeric columns found
            $value_column = $headers[1] ?? 'value';
        }
        
        // Convert values to numbers if they're strings
        $chart_values = array_column($results_array, $value_column);
        $chart_values = array_map(function($value) {
            return is_numeric($value) ? (float)$value : (is_string($value) && is_numeric(trim($value)) ? (float)trim($value) : 0);
        }, $chart_values);
        
        // Generate colors based on chart type
        $colors = generateChartColors(count($chart_values), $report->charttype);
        
        // Create chart data structure
        $chart_data_structure = [
            'labels' => array_column($results_array, $label_column),
            'datasets' => [
                [
                    'label' => $report->name,
                    'data' => $chart_values,
                    'backgroundColor' => $colors,
                    'borderColor' => adjustColors($colors, -20),
                    'borderWidth' => 2
                ]
            ],
            'axis_labels' => [
                'x_axis' => $label_column,
                'y_axis' => $value_column
            ]
        ];
        
        // Convert chart data to exportable format
        $chart_export_data = [];
        $chart_export_headers = ['Label', 'Value'];
        $chart_export_data[] = $chart_export_headers;
        
        $labels = $chart_data_structure['labels'];
        $values = $chart_data_structure['datasets'][0]['data'] ?? [];
        
        for ($i = 0; $i < count($labels); $i++) {
            $chart_export_data[] = [
                $labels[$i] ?? '',
                $values[$i] ?? ''
            ];
        }
    }

    // Generate filename
    $clean_report_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $report->name);
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $clean_report_name . '_' . $timestamp;

    // Handle different export formats
    switch ($format) {
        case 'csv':
            // CSV: table only
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Cache-Control: max-age=0');
            
            $output = fopen('php://output', 'w');
            foreach ($table_data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            break;
            
        case 'excel':
            // Excel: Table Data on sheet 1, Chart Visualization on sheet 2
            // Use CSV format that Excel can open reliably
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Cache-Control: max-age=0');
            
            echo generateExcelCSV($reportid, $table_data, $chart_export_data, $report_params);
            break;
            
        case 'json':
            // JSON: as is (current working format)
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            
            $json_data = [
                'report_name' => $report->name,
                'report_category' => $report->category,
                'generated_at' => date('Y-m-d H:i:s'),
                'parameters' => $report_params,
                'headers' => $headers,
                'table_data' => array_slice($table_data, 1), // Remove header row for JSON
                'chart_data' => $chart_export_data ? array_slice($chart_export_data, 1) : null
            ];
            
            echo json_encode($json_data, JSON_PRETTY_PRINT);
            break;
            
        case 'pdf':
            // PDF: table on page 1, chart on page 2
            // Generate actual PDF using TCPDF
            try {
                
                $pdf_content = generatePDF($reportid, $table_data, $chart_export_data, $report_params, $chart_image);
                
                if ($pdf_content === false || empty($pdf_content)) {
                    error_log('PDF Export - PDF content is empty or false');
                    throw new Exception('Failed to generate PDF content');
                }
                
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                header('Cache-Control: max-age=0');
                header('Content-Length: ' . strlen($pdf_content));
                
                echo $pdf_content;
            } catch (Exception $pdf_error) {
                error_log('PDF Generation Error: ' . $pdf_error->getMessage());
                error_log('PDF Generation Stack Trace: ' . $pdf_error->getTraceAsString());
                
                // Return JSON error instead of corrupted PDF
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'message' => 'PDF generation failed: ' . $pdf_error->getMessage()
                ]);
            }
            break;
            
        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unsupported export format']);
            break;
    }

} catch (Exception $e) {
    error_log('Error in export_report.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    // Always return JSON error for AJAX requests
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error exporting report: ' . $e->getMessage()
    ]);
}

/**
 * Generate Excel HTML format with multiple sheets
 */
function generateExcelHTML($sheets_data, $report_name) {
    $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    $html .= '<head><meta charset="UTF-8">';
    $html .= '<style>';
    $html .= 'table { border-collapse: collapse; }';
    $html .= 'th, td { border: 1px solid #000; padding: 5px; }';
    $html .= 'th { background-color: #f0f0f0; font-weight: bold; }';
    $html .= 'h2 { color: #333; margin-top: 20px; }';
    $html .= '</style>';
    $html .= '</head><body>';
    
    $html .= '<h1>' . htmlspecialchars($report_name) . '</h1>';
    $html .= '<p>Generated on: ' . date('Y-m-d H:i:s') . '</p>';
    
    foreach ($sheets_data as $sheet_name => $data) {
        $html .= '<h2>' . htmlspecialchars($sheet_name) . '</h2>';
        $html .= '<table>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table><br><br>';
    }
    
    $html .= '</body></html>';
    
    return $html;
}

/**
 * Generate PDF with chart on page 1 and table on page 2
 */
function generatePDF($report_name, $table_data, $chart_data, $report_params, $chart_image = '') {
    // Use Moodle's built-in TCPDF library
    global $CFG;
    
    try {
        // Check if TCPDF is available
        if (!class_exists('TCPDF')) {
            // Load TCPDF config first
            $tcpdf_config_path = $CFG->libdir . '/tcpdf/config/tcpdf_config.php';
            if (file_exists($tcpdf_config_path)) {
                require_once($tcpdf_config_path);
            }
            
            $tcpdf_path = $CFG->libdir . '/tcpdf/tcpdf.php';
            if (!file_exists($tcpdf_path)) {
                throw new Exception('TCPDF library not found at: ' . $tcpdf_path);
            }
            require_once($tcpdf_path);
        }
        
        // Define PDF constants if not already defined
        if (!defined('PDF_PAGE_ORIENTATION')) {
            define('PDF_PAGE_ORIENTATION', 'P');
        }
        if (!defined('PDF_UNIT')) {
            define('PDF_UNIT', 'mm');
        }
        if (!defined('PDF_PAGE_FORMAT')) {
            define('PDF_PAGE_FORMAT', 'A4');
        }
        if (!defined('PDF_MARGIN_LEFT')) {
            define('PDF_MARGIN_LEFT', 15);
        }
        if (!defined('PDF_MARGIN_TOP')) {
            define('PDF_MARGIN_TOP', 27);
        }
        if (!defined('PDF_MARGIN_RIGHT')) {
            define('PDF_MARGIN_RIGHT', 15);
        }
        if (!defined('PDF_MARGIN_HEADER')) {
            define('PDF_MARGIN_HEADER', 5);
        }
        if (!defined('PDF_MARGIN_FOOTER')) {
            define('PDF_MARGIN_FOOTER', 10);
        }
        if (!defined('PDF_MARGIN_BOTTOM')) {
            define('PDF_MARGIN_BOTTOM', 25);
        }
        if (!defined('PDF_IMAGE_SCALE_RATIO')) {
            define('PDF_IMAGE_SCALE_RATIO', 1.25);
        }
        if (!defined('PDF_FONT_NAME_MAIN')) {
            define('PDF_FONT_NAME_MAIN', 'helvetica');
        }
        if (!defined('PDF_FONT_SIZE_MAIN')) {
            define('PDF_FONT_SIZE_MAIN', 10);
        }
        if (!defined('PDF_FONT_NAME_DATA')) {
            define('PDF_FONT_NAME_DATA', 'helvetica');
        }
        if (!defined('PDF_FONT_SIZE_DATA')) {
            define('PDF_FONT_SIZE_DATA', 8);
        }
        if (!defined('PDF_FONT_MONOSPACED')) {
            define('PDF_FONT_MONOSPACED', 'courier');
        }
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    } catch (Exception $e) {
        error_log('TCPDF initialization error: ' . $e->getMessage());
        throw new Exception('Failed to initialize PDF library: ' . $e->getMessage());
    }
    
    // Set document information
    $pdf->SetCreator('Moodle Adeptus Insights');
    $pdf->SetAuthor('Adeptus Insights Report');
    $pdf->SetTitle($report_name);
    $pdf->SetSubject('Report Export');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, $report_name, 'Generated on: ' . date('Y-m-d H:i:s'));
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Page 1: Report Information and Table Data
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $report_name, 0, 1, 'L');
    $pdf->Ln(5);
    
    // Date
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'L');
    $pdf->Ln(5);
    
    // Parameters
    if (!empty($report_params)) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Parameters:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        foreach ($report_params as $key => $value) {
            $pdf->Cell(0, 6, $key . ': ' . $value, 0, 1, 'L');
        }
        $pdf->Ln(5);
    }
    
    // Table Data
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Table Data', 0, 1, 'L');
    $pdf->Ln(5);
    
    if (!empty($table_data)) {
        $pdf->SetFont('helvetica', '', 8);
        
        // Calculate column widths
        $max_cols = 0;
        foreach ($table_data as $row) {
            $max_cols = max($max_cols, count($row));
        }
        
        $page_width = $pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT;
        $col_width = $page_width / $max_cols;
        
        // Add table headers
        if (!empty($table_data)) {
            $first_row = $table_data[0];
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(240, 240, 240);
            foreach ($first_row as $cell) {
                $pdf->Cell($col_width, 6, $cell, 1, 0, 'L', true);
            }
            $pdf->Ln();
            
            // Add table data
            $pdf->SetFont('helvetica', '', 7);
            for ($i = 1; $i < count($table_data); $i++) {
                $row = $table_data[$i];
                foreach ($row as $cell) {
                    $pdf->Cell($col_width, 5, $cell, 1, 0, 'L');
                }
                $pdf->Ln();
            }
        }
    } else {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'No table data available for this report.', 0, 1, 'L');
    }
    
    // Add a new page for chart visualization
    $pdf->AddPage();
    
    // Page 2: Chart Visualization
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Chart Visualization', 0, 1, 'L');
    $pdf->Ln(5);
    
    // Display chart image if available
    if (!empty($chart_image)) {
        // Extract base64 data
        $image_data = base64_decode(preg_replace('/^data:image\/(png|jpeg|jpg);base64,/', '', $chart_image));
        
        // Save temporary image file
        $temp_file = tempnam(sys_get_temp_dir(), 'chart_');
        file_put_contents($temp_file, $image_data);
        
        // Add image to PDF
        $pdf->Image($temp_file, PDF_MARGIN_LEFT, $pdf->GetY(), $page_width, 0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
        
        // Clean up temporary file
        unlink($temp_file);
    } else {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'No chart visualization available for this report.', 0, 1, 'L');
    }
    
    // Output PDF
    try {
        $pdf_output = $pdf->Output('', 'S'); // Return as string
        
        if (empty($pdf_output)) {
            throw new Exception('PDF Output returned empty content');
        }
        
        return $pdf_output;
    } catch (Exception $e) {
        error_log('PDF Output error: ' . $e->getMessage());
        throw new Exception('Failed to output PDF: ' . $e->getMessage());
    }
}

/**
 * Generate Excel-compatible CSV file with report data
 */
function generateExcelCSV($report_name, $table_data, $chart_data, $report_params) {
    $output = '';
    
    // Add report header
    $output .= '"' . str_replace('"', '""', $report_name) . '"' . "\n";
    $output .= '"Generated on: ' . date('Y-m-d H:i:s') . '"' . "\n";
    
    // Add parameters
    if (!empty($report_params)) {
        $output .= '"Parameters:"' . "\n";
        foreach ($report_params as $key => $value) {
            $output .= '"' . str_replace('"', '""', $key . ': ' . $value) . '"' . "\n";
        }
        $output .= "\n"; // Empty line for spacing
    }
    
    // Add table data
    $output .= '"Table Data:"' . "\n";
    if (!empty($table_data)) {
        foreach ($table_data as $row) {
            $csv_row = [];
            foreach ($row as $cell) {
                $csv_row[] = '"' . str_replace('"', '""', $cell) . '"';
            }
            $output .= implode(',', $csv_row) . "\n";
        }
    } else {
        $output .= '"No table data available"' . "\n";
    }
    
    // Add separator for chart data
    $output .= "\n";
    $output .= '"Chart Data:"' . "\n";
    
    // Add chart data
    if (!empty($chart_data)) {
        foreach ($chart_data as $row) {
            $csv_row = [];
            foreach ($row as $cell) {
                $csv_row[] = '"' . str_replace('"', '""', $cell) . '"';
            }
            $output .= implode(',', $csv_row) . "\n";
        }
    } else {
        $output .= '"No chart data available"' . "\n";
    }
    
    return $output;
}



/**
 * Generate colors for charts based on chart type and data count
 */
function generateChartColors($count, $chartType) {
    $baseColors = [
        '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1',
        '#fd7e14', '#20c997', '#e83e8c', '#6c757d', '#17a2b8',
        '#6610f2', '#fd7e14', '#20c997', '#e83e8c', '#6c757d'
    ];
    
    $chartType = strtolower($chartType);
    
    if ($chartType === 'pie' || $chartType === 'donut' || $chartType === 'polar') {
        // Generate distinct colors for each data point
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $baseColors[$i % count($baseColors)];
        }
        return $colors;
    } else {
        // Use single color for bar, line, radar charts
        return [$baseColors[0]];
    }
}

/**
 * Adjust colors (lighten or darken) for border colors
 */
function adjustColors($colors, $amount) {
    if (is_array($colors)) {
        return array_map(function($color) use ($amount) {
            return adjustColor($color, $amount);
        }, $colors);
    } else {
        return adjustColor($colors, $amount);
    }
}

/**
 * Adjust a single color by lightening or darkening it
 */
function adjustColor($color, $amount) {
    // Remove # if present
    $color = ltrim($color, '#');
    
    // Convert to RGB
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    
    // Adjust each component
    $r = max(0, min(255, $r + $amount));
    $g = max(0, min(255, $g + $amount));
    $b = max(0, min(255, $b + $amount));
    
    // Convert back to hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

exit;
?> 
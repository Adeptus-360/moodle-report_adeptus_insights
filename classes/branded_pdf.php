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
 * Branded PDF class for Adeptus Insights.
 *
 * Extends TCPDF to provide custom branded headers and footers for PDF exports.
 * Branding assets are fetched from the backend server for tamper-resistance.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

// Ensure TCPDF is loaded.
global $CFG;
require_once($CFG->libdir . '/tcpdf/tcpdf.php');

/**
 * Branded PDF generator with custom header and footer.
 *
 * This class extends TCPDF to add Adeptus 360 branding to all PDF exports.
 * The branding cannot be modified locally as it is fetched from the backend.
 */
class branded_pdf extends \TCPDF {

    /**
     * Branding configuration.
     *
     * @var array
     */
    protected $branding_config;

    /**
     * Report title for header.
     *
     * @var string
     */
    protected $report_title = '';

    /**
     * Generation timestamp.
     *
     * @var string
     */
    protected $generation_timestamp = '';

    /**
     * Temporary file path for logo.
     *
     * @var string|null
     */
    protected $logo_temp_file = null;

    /**
     * Constructor.
     *
     * @param array $branding_config Branding configuration from branding_manager.
     * @param string $orientation Page orientation (P=portrait, L=landscape).
     * @param string $unit Unit of measure.
     * @param string $format Page format.
     */
    public function __construct(
        array $branding_config,
        string $orientation = 'P',
        string $unit = 'mm',
        string $format = 'A4'
    ) {
        parent::__construct($orientation, $unit, $format, true, 'UTF-8', false);

        $this->branding_config = $branding_config;
        $this->generation_timestamp = date('Y-m-d H:i:s');

        // Prepare logo temp file if branding is available.
        $this->prepare_logo_temp_file();

        // Configure PDF metadata.
        $this->configure_metadata();

        // Configure margins to accommodate header/footer.
        $this->configure_margins();
    }

    /**
     * Destructor - clean up temporary files.
     */
    public function __destruct() {
        $this->cleanup_temp_files();
    }

    /**
     * Set the report title for the header.
     *
     * @param string $title Report title.
     */
    public function set_report_title(string $title): void {
        $this->report_title = $title;
    }

    /**
     * Custom header with Adeptus 360 branding.
     *
     * Renders the logo on the left, report title centered, and timestamp on right.
     */
    public function Header() {
        // Save current position.
        $orig_x = $this->GetX();
        $orig_y = $this->GetY();

        // Header background color (subtle).
        $this->SetFillColor(248, 249, 250);
        $this->Rect(0, 0, $this->getPageWidth(), 22, 'F');

        // Logo on the left (if available).
        if ($this->branding_config['has_branding'] && $this->logo_temp_file && file_exists($this->logo_temp_file)) {
            // Calculate logo dimensions for header (max height 12mm).
            $max_height = 12;
            $ratio = $this->branding_config['logo_width'] / max($this->branding_config['logo_height'], 1);
            $logo_height = min($max_height, $this->branding_config['logo_height'] * 0.2);
            $logo_width = $logo_height * $ratio;

            // Position logo.
            $this->Image(
                $this->logo_temp_file,
                15,
                5,
                $logo_width,
                $logo_height,
                '',
                '',
                '',
                false,
                300,
                '',
                false,
                false,
                0,
                false,
                false,
                false
            );
        }

        // Report title - centered.
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(44, 62, 80);
        $this->SetXY(0, 7);
        $this->Cell(0, 8, $this->report_title, 0, 0, 'C');

        // Timestamp on the right.
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(127, 140, 141);
        $this->SetXY(0, 7);
        $this->Cell($this->getPageWidth() - 15, 8, $this->generation_timestamp, 0, 0, 'R');

        // Header line.
        $this->SetDrawColor(41, 128, 185);
        $this->SetLineWidth(0.5);
        $this->Line(15, 20, $this->getPageWidth() - 15, 20);

        // Restore position.
        $this->SetXY($orig_x, $orig_y);
    }

    /**
     * Custom footer with Adeptus 360 branding.
     *
     * Renders branding text on left and page numbers on right.
     */
    public function Footer() {
        // Position footer 15mm from bottom.
        $this->SetY(-15);

        // Footer line.
        $this->SetDrawColor(189, 195, 199);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());

        $this->Ln(2);

        // Footer text on left.
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(127, 140, 141);

        $footer_text = $this->branding_config['footer_text'];
        $this->Cell(0, 5, $footer_text, 0, 0, 'L');

        // Page numbers on right.
        $page_text = 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages();
        $this->Cell(0, 5, $page_text, 0, 0, 'R');
    }

    /**
     * Prepare temporary file for logo image.
     *
     * TCPDF requires a file path for images, so we write the logo
     * data to a temporary file that is cleaned up on destruct.
     */
    protected function prepare_logo_temp_file(): void {
        if (!$this->branding_config['has_branding'] || empty($this->branding_config['logo'])) {
            return;
        }

        $branding_manager = new branding_manager();
        $image_data = $branding_manager->extract_image_data($this->branding_config['logo']);

        if ($image_data === null) {
            return;
        }

        $extension = $branding_manager->get_image_extension($this->branding_config['logo']);
        $this->logo_temp_file = tempnam(sys_get_temp_dir(), 'adeptus_logo_') . '.' . $extension;

        file_put_contents($this->logo_temp_file, $image_data);
    }

    /**
     * Clean up temporary files.
     */
    protected function cleanup_temp_files(): void {
        if ($this->logo_temp_file && file_exists($this->logo_temp_file)) {
            @unlink($this->logo_temp_file);
            $this->logo_temp_file = null;
        }
    }

    /**
     * Configure PDF metadata.
     */
    protected function configure_metadata(): void {
        $this->SetCreator('Adeptus Insights by Adeptus 360');
        $this->SetAuthor('Adeptus 360');
        $this->SetSubject('Report Export');
        $this->SetKeywords('Adeptus, Insights, Moodle, Report, Analytics');
    }

    /**
     * Configure page margins.
     */
    protected function configure_margins(): void {
        // Top margin increased for header.
        $this->SetMargins(15, 27, 15);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(15);
        $this->SetAutoPageBreak(true, 25);
    }

    /**
     * Add a styled section title.
     *
     * @param string $title Section title.
     */
    public function add_section_title(string $title): void {
        $this->Ln(5);
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 10, $title, 0, 1, 'L');
        $this->Ln(2);
    }

    /**
     * Add a styled subsection title.
     *
     * @param string $title Subsection title.
     */
    public function add_subsection_title(string $title): void {
        $this->Ln(3);
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(52, 73, 94);
        $this->Cell(0, 8, $title, 0, 1, 'L');
        $this->Ln(1);
    }

    /**
     * Add a data table with styling.
     *
     * @param array $headers Table headers.
     * @param array $data Table data rows.
     * @param array $options Optional styling options.
     */
    public function add_data_table(array $headers, array $data, array $options = []): void {
        $this->SetFont('helvetica', '', 8);

        // Calculate column widths.
        $page_width = $this->getPageWidth() - 30; // Account for margins.
        $num_cols = count($headers);
        $col_width = $page_width / max($num_cols, 1);

        // Header row.
        $this->SetFont('helvetica', 'B', 8);
        $this->SetFillColor(41, 128, 185);
        $this->SetTextColor(255, 255, 255);

        foreach ($headers as $header) {
            $this->Cell($col_width, 7, $header, 1, 0, 'L', true);
        }
        $this->Ln();

        // Data rows.
        $this->SetFont('helvetica', '', 7);
        $this->SetTextColor(44, 62, 80);
        $fill = false;

        foreach ($data as $row) {
            // Alternating row colors.
            if ($fill) {
                $this->SetFillColor(245, 247, 250);
            } else {
                $this->SetFillColor(255, 255, 255);
            }

            foreach ($row as $cell) {
                $this->Cell($col_width, 6, $cell, 1, 0, 'L', true);
            }
            $this->Ln();
            $fill = !$fill;
        }
    }

    /**
     * Add a chart image to the PDF.
     *
     * @param string $chart_image Base64 encoded chart image.
     * @return bool True if image was added successfully.
     */
    public function add_chart_image(string $chart_image): bool {
        if (empty($chart_image)) {
            return false;
        }

        // Validate chart image format.
        if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $chart_image)) {
            return false;
        }

        // Extract image data.
        $image_data = base64_decode(
            preg_replace('/^data:image\/(png|jpeg|jpg);base64,/', '', $chart_image)
        );

        if ($image_data === false) {
            return false;
        }

        // Create temp file for chart.
        $temp_file = tempnam(sys_get_temp_dir(), 'chart_');
        file_put_contents($temp_file, $image_data);

        try {
            // Calculate image dimensions to fit page width.
            $page_width = $this->getPageWidth() - 30;
            $this->Image(
                $temp_file,
                15,
                $this->GetY(),
                $page_width,
                0,
                'PNG',
                '',
                '',
                false,
                300,
                '',
                false,
                false,
                0,
                false,
                false,
                false
            );
            $result = true;
        } catch (\Exception $e) {
            $result = false;
        }

        // Clean up temp file.
        @unlink($temp_file);

        return $result;
    }

    /**
     * Add report parameters section.
     *
     * @param array $params Report parameters.
     */
    public function add_parameters_section(array $params): void {
        if (empty($params)) {
            return;
        }

        $this->add_subsection_title(get_string('pdf_parameters', 'report_adeptus_insights'));

        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(52, 73, 94);

        foreach ($params as $key => $value) {
            $formatted_key = ucwords(str_replace('_', ' ', $key));
            $this->Cell(50, 6, $formatted_key . ':', 0, 0, 'L');
            $this->Cell(0, 6, $value, 0, 1, 'L');
        }

        $this->Ln(3);
    }

    /**
     * Add a "no data" message.
     *
     * @param string $message Optional custom message.
     */
    public function add_no_data_message(string $message = ''): void {
        if (empty($message)) {
            $message = get_string('pdf_no_data', 'report_adeptus_insights');
        }

        $this->SetFont('helvetica', 'I', 10);
        $this->SetTextColor(127, 140, 141);
        $this->Cell(0, 10, $message, 0, 1, 'C');
    }

    /**
     * Output PDF as string.
     *
     * @return string PDF content.
     */
    public function get_pdf_content(): string {
        return $this->Output('', 'S');
    }
}

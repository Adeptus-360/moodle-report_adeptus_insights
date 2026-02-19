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

namespace report_adeptus_insights\pdf;

use report_adeptus_insights\branded_pdf;
use report_adeptus_insights\branding_manager;

/**
 * PDF template for scheduled report delivery.
 *
 * Renders report data as a styled PDF with branded header/footer,
 * schedule metadata, and auto-layout for varying column counts.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scheduled_report_template {

    /** @var int Column threshold for switching to landscape orientation. */
    const LANDSCAPE_THRESHOLD = 6;

    /** @var int Maximum rows to render in PDF before truncation. */
    const MAX_PDF_ROWS = 5000;

    /**
     * Generate a PDF file for a scheduled report.
     *
     * @param array $data Array of row objects from the report query.
     * @param array $columns Column header strings.
     * @param \stdClass $schedule The schedule record.
     * @param string|null $reportname Optional report display name.
     * @return string Path to the generated temp PDF file.
     * @throws \moodle_exception If PDF generation fails critically.
     */
    public function generate(array $data, array $columns, \stdClass $schedule, ?string $reportname = null): string {
        $brandingmanager = new branding_manager();
        $brandingconfig = $brandingmanager->get_pdf_branding_config();

        // Determine orientation based on column count.
        $orientation = count($columns) > self::LANDSCAPE_THRESHOLD ? 'L' : 'P';

        $pdf = new branded_pdf($brandingconfig, $orientation, 'mm', 'A4');

        // Set report title.
        $title = $reportname ?? $schedule->label ?? get_string('pluginname', 'report_adeptus_insights');
        $pdf->set_report_title($title);

        $pdf->AddPage();

        // Schedule metadata section.
        $this->render_metadata($pdf, $schedule, $title, count($data));

        // Data table.
        if (empty($data)) {
            $pdf->add_no_data_message();
        } else {
            $truncated = false;
            $renderdata = $data;
            if (count($data) > self::MAX_PDF_ROWS) {
                $renderdata = array_slice($data, 0, self::MAX_PDF_ROWS);
                $truncated = true;
            }

            // Format headers.
            $formattedheaders = array_map(function($h) {
                return ucwords(str_replace('_', ' ', $h));
            }, $columns);

            // Convert row objects to arrays of values.
            $tabledata = [];
            foreach ($renderdata as $row) {
                $rowarray = [];
                foreach ($columns as $col) {
                    $val = (array) $row;
                    $cell = $val[$col] ?? '';
                    // Truncate long cell values for PDF readability.
                    if (is_string($cell) && mb_strlen($cell) > 80) {
                        $cell = mb_substr($cell, 0, 77) . '...';
                    }
                    $rowarray[] = (string) $cell;
                }
                $tabledata[] = $rowarray;
            }

            $pdf->add_data_table($formattedheaders, $tabledata);

            if ($truncated) {
                $pdf->Ln(5);
                $pdf->SetFont('helvetica', 'I', 8);
                $pdf->SetTextColor(180, 80, 80);
                $a = new \stdClass();
                $a->shown = self::MAX_PDF_ROWS;
                $a->total = count($data);
                $pdf->Cell(0, 6, get_string('scheduled_pdf_truncated', 'report_adeptus_insights', $a), 0, 1, 'C');
            }
        }

        // Write to temp file.
        $tmpfile = tempnam(sys_get_temp_dir(), 'adeptus_sched_pdf_') . '.pdf';
        $content = $pdf->get_pdf_content();

        if (empty($content)) {
            throw new \moodle_exception('error_pdf_generation_failed', 'report_adeptus_insights');
        }

        file_put_contents($tmpfile, $content);

        return $tmpfile;
    }

    /**
     * Render schedule metadata at the top of the PDF.
     *
     * @param branded_pdf $pdf The PDF instance.
     * @param \stdClass $schedule The schedule record.
     * @param string $title Report title.
     * @param int $rowcount Number of data rows.
     */
    protected function render_metadata(branded_pdf $pdf, \stdClass $schedule, string $title, int $rowcount): void {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);

        $dateformat = get_string('strftimedaydatetime', 'langconfig');
        $generatedstr = userdate(time(), $dateformat);

        // Report name.
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 6, get_string('scheduled_pdf_report_label', 'report_adeptus_insights'), 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, $title, 0, 1, 'L');

        // Schedule label (if different from title).
        if (!empty($schedule->label) && $schedule->label !== $title) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(40, 6, get_string('scheduled_pdf_schedule_label', 'report_adeptus_insights'), 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, $schedule->label, 0, 1, 'L');
        }

        // Frequency.
        $freqkey = 'frequency_' . ($schedule->frequency ?? 'daily');
        $freqstr = get_string($freqkey, 'report_adeptus_insights');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 6, get_string('scheduled_pdf_frequency_label', 'report_adeptus_insights'), 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, $freqstr, 0, 1, 'L');

        // Generated timestamp.
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 6, get_string('scheduled_pdf_generated_label', 'report_adeptus_insights'), 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, $generatedstr, 0, 1, 'L');

        // Row count.
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 6, get_string('scheduled_pdf_rows_label', 'report_adeptus_insights'), 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, (string) $rowcount, 0, 1, 'L');

        // Separator line.
        $pdf->Ln(3);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(15, $pdf->GetY(), $pdf->getPageWidth() - 15, $pdf->GetY());
        $pdf->Ln(5);
    }
}

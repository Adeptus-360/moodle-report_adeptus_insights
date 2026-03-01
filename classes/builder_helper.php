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
 * Helper utilities for the Report Builder.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper methods for building report definitions and exporting data.
 */
class builder_helper {

    /**
     * Build a report definition array from Moodle form data.
     *
     * @param object $data Form data from moodleform.
     * @param array $catalog Data catalog for validation.
     * @return array The definition structure expected by the backend.
     */
    public static function build_definition_from_form(object $data, array $catalog): array {
        $definition = [
            'entity' => $data->datasource,
            'columns' => [],
            'filters' => [],
            'sortBy' => [],
        ];

        // Parse selected columns.
        if (!empty($data->columns)) {
            $definition['columns'] = is_array($data->columns) ? $data->columns : [$data->columns];
        }

        // Parse filters — submitted as parallel arrays.
        if (!empty($data->filter_field)) {
            foreach ($data->filter_field as $i => $field) {
                if (empty($field)) {
                    continue;
                }
                $definition['filters'][] = [
                    'field' => $field,
                    'operator' => $data->filter_operator[$i] ?? '=',
                    'value' => $data->filter_value[$i] ?? '',
                ];
            }
        }

        // Parse sort order.
        if (!empty($data->sort_field)) {
            $definition['sortBy'][] = [
                'field' => $data->sort_field,
                'direction' => $data->sort_direction ?? 'ASC',
            ];
        }

        return $definition;
    }

    /**
     * Export report data as a CSV download.
     *
     * @param string $reportname Report name for the filename.
     * @param array $columns Column headers.
     * @param array $rows Data rows.
     * @return void Outputs CSV and exits.
     */
    public static function export_csv(string $reportname, array $columns, array $rows): void {
        $filename = clean_filename($reportname . '_' . date('Ymd_His')) . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $fp = fopen('php://output', 'w');

        // Write BOM for Excel UTF-8 compatibility.
        fwrite($fp, "\xEF\xBB\xBF");

        // Header row.
        fputcsv($fp, $columns);

        // Data rows.
        foreach ($rows as $row) {
            $rowdata = [];
            foreach ($columns as $col) {
                $rowdata[] = $row->$col ?? '';
            }
            fputcsv($fp, $rowdata);
        }

        fclose($fp);
    }
}

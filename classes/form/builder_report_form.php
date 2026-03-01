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
 * Report Builder form — create/edit a custom report definition.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Moodleform for creating/editing a builder report.
 */
class builder_report_form extends \moodleform {

    /**
     * Define the form elements.
     */
    protected function definition() {
        $mform = $this->_form;
        $catalog = $this->_customdata['catalog'] ?? [];
        $report = $this->_customdata['report'] ?? null;
        $id = $this->_customdata['id'] ?? 0;

        // Hidden fields.
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        // ---- Report Details ----
        $mform->addElement('header', 'detailshdr', get_string('builder_details_header', 'report_adeptus_insights'));

        $mform->addElement('text', 'name', get_string('builder_report_name', 'report_adeptus_insights'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('builder_report_description', 'report_adeptus_insights'),
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        // ---- Data Source ----
        $mform->addElement('header', 'datasourcehdr', get_string('builder_datasource_header', 'report_adeptus_insights'));

        $sources = ['' => get_string('builder_select_datasource', 'report_adeptus_insights')];
        if (!empty($catalog)) {
            foreach ($catalog as $key => $entity) {
                $entityname = is_object($entity) ? ($entity->label ?? $key) : ($entity['label'] ?? $key);
                $sources[$key] = $entityname;
            }
        }
        $mform->addElement('select', 'datasource', get_string('builder_datasource', 'report_adeptus_insights'), $sources,
            ['id' => 'id_datasource']);
        $mform->addRule('datasource', get_string('required'), 'required', null, 'client');

        // ---- Column Picker ----
        // Columns are rendered dynamically by AMD JS based on selected datasource.
        // We add a hidden container div that JS will populate with checkboxes.
        $mform->addElement('header', 'columnshdr', get_string('builder_columns_header', 'report_adeptus_insights'));
        $mform->addElement('html', '<div id="builder-columns-container">' .
            '<p class="text-muted">' . get_string('builder_select_datasource_first', 'report_adeptus_insights') . '</p>' .
            '</div>');
        // The actual column values are submitted as a multi-select hidden field populated by JS.
        $mform->addElement('hidden', 'columns_json', '');
        $mform->setType('columns_json', PARAM_RAW);

        // ---- Filters ----
        $mform->addElement('header', 'filtershdr', get_string('builder_filters_header', 'report_adeptus_insights'));
        $mform->addElement('html', '<div id="builder-filters-container">' .
            '<p class="text-muted">' . get_string('builder_select_datasource_first', 'report_adeptus_insights') . '</p>' .
            '</div>');
        $mform->addElement('hidden', 'filters_json', '');
        $mform->setType('filters_json', PARAM_RAW);

        // ---- Sort Order ----
        $mform->addElement('header', 'sorthdr', get_string('builder_sort_header', 'report_adeptus_insights'));
        $mform->addElement('html', '<div id="builder-sort-container">' .
            '<p class="text-muted">' . get_string('builder_select_datasource_first', 'report_adeptus_insights') . '</p>' .
            '</div>');
        $mform->addElement('hidden', 'sort_json', '');
        $mform->setType('sort_json', PARAM_RAW);

        // ---- Actions ----
        $this->add_action_buttons(true, $id ? get_string('builder_save_report', 'report_adeptus_insights')
            : get_string('builder_create_report', 'report_adeptus_insights'));

        // Pre-fill form if editing.
        if ($report) {
            $defaults = [
                'name' => $report->name ?? '',
                'description' => $report->description ?? '',
                'datasource' => $report->definition->entity ?? '',
                'columns_json' => !empty($report->definition->columns) ? json_encode($report->definition->columns) : '',
                'filters_json' => !empty($report->definition->filters) ? json_encode($report->definition->filters) : '',
                'sort_json' => !empty($report->definition->sortBy) ? json_encode($report->definition->sortBy) : '',
            ];
            $this->set_data($defaults);
        }
    }

    /**
     * Validate the form submission.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['name'])) {
            $errors['name'] = get_string('required');
        }
        if (empty($data['datasource'])) {
            $errors['datasource'] = get_string('required');
        }

        // Require at least one column.
        $columns = json_decode($data['columns_json'] ?? '', true);
        if (empty($columns)) {
            $errors['columns_json'] = get_string('builder_columns_required', 'report_adeptus_insights');
        }

        return $errors;
    }

    /**
     * Get the submitted data, parsing JSON fields into structured arrays.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return null;
        }

        // Parse JSON fields into arrays the helper expects.
        $data->columns = json_decode($data->columns_json ?? '[]', true) ?: [];

        $filters = json_decode($data->filters_json ?? '[]', true) ?: [];
        $data->filter_field = [];
        $data->filter_operator = [];
        $data->filter_value = [];
        foreach ($filters as $f) {
            $data->filter_field[] = $f['field'] ?? '';
            $data->filter_operator[] = $f['operator'] ?? '=';
            $data->filter_value[] = $f['value'] ?? '';
        }

        $sort = json_decode($data->sort_json ?? '[]', true) ?: [];
        $data->sort_field = $sort[0]['field'] ?? '';
        $data->sort_direction = $sort[0]['direction'] ?? 'ASC';

        return $data;
    }
}

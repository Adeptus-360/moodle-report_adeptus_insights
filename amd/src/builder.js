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
 * AMD module for the Report Builder dynamic form UI.
 *
 * Handles dynamic column picker, filter builder, and sort order
 * based on the selected data source from the backend catalog.
 *
 * @module     report_adeptus_insights/builder
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/str'], function(Str) {
    'use strict';

    let catalog = {};
    let reportData = {};

    /**
     * Get columns for a given entity from the catalog.
     * @param {string} entity
     * @returns {Array}
     */
    const getEntityColumns = function(entity) {
        if (!entity || !catalog[entity]) {
            return [];
        }
        const entityData = catalog[entity];
        return entityData.columns || entityData.fields || [];
    };

    /**
     * Render column checkboxes into the container.
     * @param {string} entity
     */
    const renderColumns = function(entity) {
        const container = document.getElementById('builder-columns-container');
        if (!container) {
            return;
        }

        const columns = getEntityColumns(entity);
        if (!columns.length) {
            container.innerHTML = '<p class="text-muted">No columns available for this data source.</p>';
            return;
        }

        // Parse currently selected columns.
        const hiddenField = document.querySelector('input[name="columns_json"]');
        let selected = [];
        try {
            selected = JSON.parse(hiddenField?.value || '[]');
        } catch (e) {
            selected = [];
        }

        let html = '<div class="row">';
        columns.forEach(function(col) {
            const colName = typeof col === 'string' ? col : (col.name || col.column);
            const colLabel = typeof col === 'string' ? col : (col.label || col.name || col.column);
            const checked = selected.includes(colName) ? ' checked' : '';
            html += '<div class="col-md-4 col-sm-6 mb-1">' +
                '<div class="form-check">' +
                '<input class="form-check-input builder-column-cb" type="checkbox" ' +
                'value="' + colName + '" id="col_' + colName + '"' + checked + '>' +
                '<label class="form-check-label" for="col_' + colName + '">' + colLabel + '</label>' +
                '</div></div>';
        });
        html += '</div>';

        // Select all / none buttons.
        html = '<div class="mb-2">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary" id="builder-select-all">Select All</button> ' +
            '<button type="button" class="btn btn-sm btn-outline-secondary" id="builder-select-none">Select None</button>' +
            '</div>' + html;

        container.innerHTML = html;

        // Bind events.
        container.querySelectorAll('.builder-column-cb').forEach(function(cb) {
            cb.addEventListener('change', syncColumnsToHidden);
        });

        const selectAll = document.getElementById('builder-select-all');
        if (selectAll) {
            selectAll.addEventListener('click', function(e) {
                e.preventDefault();
                container.querySelectorAll('.builder-column-cb').forEach(function(cb) {
                    cb.checked = true;
                });
                syncColumnsToHidden();
            });
        }

        const selectNone = document.getElementById('builder-select-none');
        if (selectNone) {
            selectNone.addEventListener('click', function(e) {
                e.preventDefault();
                container.querySelectorAll('.builder-column-cb').forEach(function(cb) {
                    cb.checked = false;
                });
                syncColumnsToHidden();
            });
        }
    };

    /**
     * Sync checked column checkboxes to the hidden JSON field.
     */
    const syncColumnsToHidden = function() {
        const hiddenField = document.querySelector('input[name="columns_json"]');
        if (!hiddenField) {
            return;
        }
        const checked = [];
        document.querySelectorAll('.builder-column-cb:checked').forEach(function(cb) {
            checked.push(cb.value);
        });
        hiddenField.value = JSON.stringify(checked);
    };

    /**
     * Render the filter builder UI.
     * @param {string} entity
     */
    const renderFilters = function(entity) {
        const container = document.getElementById('builder-filters-container');
        if (!container) {
            return;
        }

        const columns = getEntityColumns(entity);
        if (!columns.length) {
            container.innerHTML = '<p class="text-muted">Select a data source first.</p>';
            return;
        }

        // Parse existing filters.
        const hiddenField = document.querySelector('input[name="filters_json"]');
        let filters = [];
        try {
            filters = JSON.parse(hiddenField?.value || '[]');
        } catch (e) {
            filters = [];
        }

        let html = '<div id="builder-filter-rows">';
        if (filters.length) {
            filters.forEach(function(f, idx) {
                html += buildFilterRow(columns, idx, f);
            });
        }
        html += '</div>';
        html += '<button type="button" class="btn btn-sm btn-outline-primary mt-2" id="builder-add-filter">' +
            '<i class="fa fa-plus"></i> Add Filter</button>';

        container.innerHTML = html;

        // Bind add filter.
        document.getElementById('builder-add-filter').addEventListener('click', function(e) {
            e.preventDefault();
            const rowsDiv = document.getElementById('builder-filter-rows');
            const idx = rowsDiv.querySelectorAll('.builder-filter-row').length;
            rowsDiv.insertAdjacentHTML('beforeend', buildFilterRow(columns, idx, {}));
            bindFilterEvents();
        });

        bindFilterEvents();
    };

    /**
     * Build HTML for a single filter row.
     * @param {Array} columns
     * @param {number} idx
     * @param {Object} filter
     * @returns {string}
     */
    const buildFilterRow = function(columns, idx, filter) {
        let fieldOptions = '<option value="">-- Field --</option>';
        columns.forEach(function(col) {
            const colName = typeof col === 'string' ? col : (col.name || col.column);
            const colLabel = typeof col === 'string' ? col : (col.label || col.name || col.column);
            const sel = (filter.field === colName) ? ' selected' : '';
            fieldOptions += '<option value="' + colName + '"' + sel + '>' + colLabel + '</option>';
        });

        const operators = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'IS NULL', 'IS NOT NULL'];
        let opOptions = '';
        operators.forEach(function(op) {
            const sel = (filter.operator === op) ? ' selected' : '';
            opOptions += '<option value="' + op + '"' + sel + '>' + op + '</option>';
        });

        return '<div class="builder-filter-row d-flex align-items-center gap-2 mb-2" data-idx="' + idx + '">' +
            '<select class="form-control form-control-sm filter-field" style="max-width:200px">' +
            fieldOptions + '</select>' +
            '<select class="form-control form-control-sm filter-operator" style="max-width:140px">' +
            opOptions + '</select>' +
            '<input type="text" class="form-control form-control-sm filter-value" ' +
            'value="' + (filter.value || '') + '" placeholder="Value" style="max-width:200px">' +
            '<button type="button" class="btn btn-sm btn-outline-danger filter-remove" title="Remove">' +
            '<i class="fa fa-times"></i></button>' +
            '</div>';
    };

    /**
     * Bind change/remove events on filter rows.
     */
    const bindFilterEvents = function() {
        document.querySelectorAll('.builder-filter-row').forEach(function(row) {
            row.querySelectorAll('select, input').forEach(function(el) {
                el.removeEventListener('change', syncFiltersToHidden);
                el.addEventListener('change', syncFiltersToHidden);
                el.removeEventListener('input', syncFiltersToHidden);
                el.addEventListener('input', syncFiltersToHidden);
            });
            const removeBtn = row.querySelector('.filter-remove');
            if (removeBtn) {
                removeBtn.onclick = function(e) {
                    e.preventDefault();
                    row.remove();
                    syncFiltersToHidden();
                };
            }
        });
    };

    /**
     * Sync filter rows to hidden JSON field.
     */
    const syncFiltersToHidden = function() {
        const hiddenField = document.querySelector('input[name="filters_json"]');
        if (!hiddenField) {
            return;
        }
        const filters = [];
        document.querySelectorAll('.builder-filter-row').forEach(function(row) {
            const field = row.querySelector('.filter-field')?.value || '';
            const operator = row.querySelector('.filter-operator')?.value || '=';
            const value = row.querySelector('.filter-value')?.value || '';
            if (field) {
                filters.push({field: field, operator: operator, value: value});
            }
        });
        hiddenField.value = JSON.stringify(filters);
    };

    /**
     * Render sort order selector.
     * @param {string} entity
     */
    const renderSort = function(entity) {
        const container = document.getElementById('builder-sort-container');
        if (!container) {
            return;
        }

        const columns = getEntityColumns(entity);
        if (!columns.length) {
            container.innerHTML = '<p class="text-muted">Select a data source first.</p>';
            return;
        }

        const hiddenField = document.querySelector('input[name="sort_json"]');
        let sortArr = [];
        try {
            sortArr = JSON.parse(hiddenField?.value || '[]');
        } catch (e) {
            sortArr = [];
        }
        const currentField = sortArr.length ? sortArr[0].field : '';
        const currentDir = sortArr.length ? sortArr[0].direction : 'ASC';

        let fieldOptions = '<option value="">-- None --</option>';
        columns.forEach(function(col) {
            const colName = typeof col === 'string' ? col : (col.name || col.column);
            const colLabel = typeof col === 'string' ? col : (col.label || col.name || col.column);
            const sel = (currentField === colName) ? ' selected' : '';
            fieldOptions += '<option value="' + colName + '"' + sel + '>' + colLabel + '</option>';
        });

        const ascSel = currentDir === 'ASC' ? ' selected' : '';
        const descSel = currentDir === 'DESC' ? ' selected' : '';

        const html = '<div class="d-flex align-items-center gap-2">' +
            '<select class="form-control form-control-sm" id="builder-sort-field" style="max-width:250px">' +
            fieldOptions + '</select>' +
            '<select class="form-control form-control-sm" id="builder-sort-dir" style="max-width:120px">' +
            '<option value="ASC"' + ascSel + '>ASC</option>' +
            '<option value="DESC"' + descSel + '>DESC</option>' +
            '</select></div>';

        container.innerHTML = html;

        const syncSort = function() {
            const field = document.getElementById('builder-sort-field')?.value || '';
            const dir = document.getElementById('builder-sort-dir')?.value || 'ASC';
            if (field) {
                hiddenField.value = JSON.stringify([{field: field, direction: dir}]);
            } else {
                hiddenField.value = '[]';
            }
        };

        document.getElementById('builder-sort-field').addEventListener('change', syncSort);
        document.getElementById('builder-sort-dir').addEventListener('change', syncSort);
    };

    /**
     * Handle data source change — re-render columns, filters, sort.
     */
    const onDatasourceChange = function() {
        const select = document.getElementById('id_datasource');
        if (!select) {
            return;
        }
        const entity = select.value;
        renderColumns(entity);
        renderFilters(entity);
        renderSort(entity);
    };

    return {
        /**
         * Initialise the builder form dynamic UI.
         * @param {Object} params
         * @param {string} params.catalogJson
         * @param {string} params.reportJson
         */
        init: function(params) {
            try {
                catalog = JSON.parse(params.catalogJson || '{}');
            } catch (e) {
                catalog = {};
            }
            try {
                reportData = JSON.parse(params.reportJson || '{}');
            } catch (e) {
                reportData = {};
            }

            const select = document.getElementById('id_datasource');
            if (select) {
                select.addEventListener('change', onDatasourceChange);
                // If editing, trigger initial render.
                if (select.value) {
                    onDatasourceChange();
                }
            }
        }
    };
});

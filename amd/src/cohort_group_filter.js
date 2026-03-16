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
 * Shared Cohort & Group filter module for Adeptus Insights.
 *
 * Provides init(), loadFilters(), applyFilters(), clearFilters(), getActiveFilters()
 * to be used by wizard.js, assistant.js, and generated_reports.js.
 *
 * @module     report_adeptus_insights/cohort_group_filter
 * @package    report_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax'], function($, Ajax) {

    /** @type {Array} Cohort options from server */
    var cohortOptions = [];
    /** @type {Array} Group options from server */
    var groupOptions = [];
    /** @type {Array} Currently selected cohort IDs */
    var activeCohortIds = [];
    /** @type {Array} Currently selected group IDs */
    var activeGroupIds = [];
    /** @type {boolean} Whether filters have been loaded */
    var filtersLoaded = false;
    /** @type {Function|null} Callback when filters are applied/cleared */
    var onFilterChange = null;

    return {
        /**
         * Initialize the filter bar: bind events and load filter options.
         *
         * @param {Object} options Configuration options.
         * @param {Function} options.onFilterChange Callback invoked with {cohortids, groupids} when filters change.
         */
        init: function(options) {
            options = options || {};
            onFilterChange = options.onFilterChange || null;

            // Bind button events.
            $(document).off('click.adeptusFilter').on('click.adeptusFilter', '#apply-filters', function() {
                this.applyFilters();
            }.bind(this));

            $(document).off('click.adeptusFilterClear').on('click.adeptusFilterClear', '#clear-filters', function() {
                this.clearFilters();
            }.bind(this));

            // Load filter options.
            this.loadFilters();
        },

        /**
         * Load cohort and group filter options from the Moodle external service.
         *
         * @returns {Promise}
         */
        loadFilters: function() {
            if (filtersLoaded) {
                return $.Deferred().resolve().promise();
            }

            var self = this;

            var promises = Ajax.call([{
                methodname: 'report_adeptus_insights_get_cohort_group_filters',
                args: {}
            }]);

            return promises[0].then(function(data) {
                cohortOptions = data.cohorts || [];
                groupOptions = data.groups || [];
                filtersLoaded = true;
                self.populateDropdowns();
                return data;
            }).catch(function() {
                // Filters unavailable — hide the bar.
                $('#cohort-group-filter-bar').hide();
            });
        },

        /**
         * Populate the filter dropdown selects with loaded options.
         */
        populateDropdowns: function() {
            var cohortSelect = document.getElementById('filter-cohort');
            var groupSelect = document.getElementById('filter-group');
            var filterBar = document.getElementById('cohort-group-filter-bar');

            if (!cohortSelect || !groupSelect || !filterBar) {
                return;
            }

            // If no options, keep hidden.
            if (cohortOptions.length === 0 && groupOptions.length === 0) {
                filterBar.style.display = 'none';
                return;
            }

            // Don't auto-show — let the calling code decide when to display via show().

            // Populate cohorts.
            cohortSelect.innerHTML = '';
            if (cohortOptions.length === 0) {
                var opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No cohorts available';
                opt.disabled = true;
                cohortSelect.appendChild(opt);
                cohortSelect.disabled = true;
            } else {
                cohortOptions.forEach(function(cohort) {
                    var opt = document.createElement('option');
                    opt.value = cohort.id;
                    opt.textContent = cohort.name + ' (' + cohort.membercount + ' members)';
                    cohortSelect.appendChild(opt);
                });
            }

            // Populate groups (grouped by course).
            groupSelect.innerHTML = '';
            if (groupOptions.length === 0) {
                var opt2 = document.createElement('option');
                opt2.value = '';
                opt2.textContent = 'No groups available';
                opt2.disabled = true;
                groupSelect.appendChild(opt2);
                groupSelect.disabled = true;
            } else {
                var courseMap = {};
                groupOptions.forEach(function(group) {
                    var cname = group.coursename || 'Unknown Course';
                    if (!courseMap[cname]) {
                        courseMap[cname] = [];
                    }
                    courseMap[cname].push(group);
                });

                Object.keys(courseMap).sort().forEach(function(coursename) {
                    var optgroup = document.createElement('optgroup');
                    optgroup.label = coursename;
                    courseMap[coursename].forEach(function(group) {
                        var opt3 = document.createElement('option');
                        opt3.value = group.id;
                        opt3.textContent = group.name + ' (' + group.membercount + ' members)';
                        optgroup.appendChild(opt3);
                    });
                    groupSelect.appendChild(optgroup);
                });
            }
        },

        /**
         * Read selected values from dropdowns, store them, render tags, and fire callback.
         */
        applyFilters: function() {
            var cohortSelect = document.getElementById('filter-cohort');
            var groupSelect = document.getElementById('filter-group');

            var selectedCohorts = Array.from(cohortSelect.selectedOptions)
                .map(function(o) { return parseInt(o.value); })
                .filter(function(v) { return v > 0; });

            var selectedGroups = Array.from(groupSelect.selectedOptions)
                .map(function(o) { return parseInt(o.value); })
                .filter(function(v) { return v > 0; });

            if (selectedCohorts.length === 0 && selectedGroups.length === 0) {
                return;
            }

            activeCohortIds = selectedCohorts;
            activeGroupIds = selectedGroups;

            this.renderFilterTags();

            if (typeof onFilterChange === 'function') {
                onFilterChange(this.getActiveFilters());
            }
        },

        /**
         * Clear all active filters, reset dropdowns, and fire callback.
         */
        clearFilters: function() {
            var cohortSelect = document.getElementById('filter-cohort');
            var groupSelect = document.getElementById('filter-group');

            if (cohortSelect) {
                Array.from(cohortSelect.options).forEach(function(o) { o.selected = false; });
            }
            if (groupSelect) {
                Array.from(groupSelect.options).forEach(function(o) { o.selected = false; });
            }

            activeCohortIds = [];
            activeGroupIds = [];

            var tagsContainer = document.getElementById('filter-active-tags');
            if (tagsContainer) {
                tagsContainer.style.display = 'none';
            }

            if (typeof onFilterChange === 'function') {
                onFilterChange(this.getActiveFilters());
            }
        },

        /**
         * Get the current active filter state.
         *
         * @returns {Object} {cohortids: number[], groupids: number[]}
         */
        getActiveFilters: function() {
            return {
                cohortids: activeCohortIds.slice(),
                groupids: activeGroupIds.slice()
            };
        },

        /**
         * Render active filter tags with remove buttons.
         */
        renderFilterTags: function() {
            var self = this;
            var tagsContainer = document.getElementById('filter-active-tags');
            var tagsDiv = document.getElementById('filter-tags');

            if (!tagsContainer || !tagsDiv) {
                return;
            }

            tagsDiv.innerHTML = '';

            activeCohortIds.forEach(function(id) {
                var cohort = cohortOptions.find(function(c) { return c.id === id; });
                if (cohort) {
                    var tag = document.createElement('span');
                    tag.className = 'adeptus-filter-tag adeptus-filter-tag-cohort';
                    tag.innerHTML = '<i class="fa-solid fa-user-group"></i> ' + cohort.name +
                        ' <button class="adeptus-filter-tag-remove" data-type="cohort" data-id="' + id + '">&times;</button>';
                    tagsDiv.appendChild(tag);
                }
            });

            activeGroupIds.forEach(function(id) {
                var group = groupOptions.find(function(g) { return g.id === id; });
                if (group) {
                    var tag = document.createElement('span');
                    tag.className = 'adeptus-filter-tag adeptus-filter-tag-group';
                    tag.innerHTML = '<i class="fa-solid fa-people-group"></i> ' + group.name +
                        ' <button class="adeptus-filter-tag-remove" data-type="group" data-id="' + id + '">&times;</button>';
                    tagsDiv.appendChild(tag);
                }
            });

            // Remove-tag click handlers.
            $(tagsDiv).find('.adeptus-filter-tag-remove').on('click', function(e) {
                var type = $(e.target).data('type');
                var removeId = parseInt($(e.target).data('id'));
                if (type === 'cohort') {
                    activeCohortIds = activeCohortIds.filter(function(cid) { return cid !== removeId; });
                } else {
                    activeGroupIds = activeGroupIds.filter(function(gid) { return gid !== removeId; });
                }
                self.renderFilterTags();
                if (typeof onFilterChange === 'function') {
                    onFilterChange(self.getActiveFilters());
                }
            });

            tagsContainer.style.display = (activeCohortIds.length || activeGroupIds.length) ? '' : 'none';
        },

        /**
         * Check if any filters are currently active.
         *
         * @returns {boolean}
         */
        hasActiveFilters: function() {
            return activeCohortIds.length > 0 || activeGroupIds.length > 0;
        },

        /**
         * Get the cohort options loaded from the server.
         *
         * @returns {Array}
         */
        getCohortOptions: function() {
            return cohortOptions;
        },

        /**
         * Get the group options loaded from the server.
         *
         * @returns {Array}
         */
        getGroupOptions: function() {
            return groupOptions;
        },

        /**
         * Show the filter bar (only if filters are loaded and available).
         */
        show: function() {
            var filterBar = document.getElementById('cohort-group-filter-bar');
            if (filterBar && (cohortOptions.length > 0 || groupOptions.length > 0)) {
                filterBar.style.display = '';
            }
        },

        /**
         * Hide the filter bar.
         */
        hide: function() {
            var filterBar = document.getElementById('cohort-group-filter-bar');
            if (filterBar) {
                filterBar.style.display = 'none';
            }
        },

        /**
         * Check if filter data has been loaded and options are available.
         * @return {boolean}
         */
        isAvailable: function() {
            return filtersLoaded && (cohortOptions.length > 0 || groupOptions.length > 0);
        }
    };
});

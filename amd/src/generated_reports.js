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
 * Generated Reports AMD module for Adeptus Insights.
 *
 * @module     report_adeptus_insights/generated_reports
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/str', 'core/chartjs'], function($, Ajax, Str, Chart) {
    'use strict';

    /**
     * String storage object - populated from core/str.
     * @type {Object}
     */
    var STRINGS = {};

    /**
     * Load all localized strings from Moodle's language API.
     * @returns {Promise}
     */
    var loadStrings = function() {
        return Str.get_strings([
            // General
            {key: 'general', component: 'report_adeptus_insights'},
            {key: 'wizard', component: 'report_adeptus_insights'},
            {key: 'ai', component: 'report_adeptus_insights'},
            {key: 'report', component: 'report_adeptus_insights'},
            {key: 'report_details', component: 'report_adeptus_insights'},
            {key: 'untitled_report', component: 'report_adeptus_insights'},
            {key: 'unknown', component: 'report_adeptus_insights'},
            {key: 'rows', component: 'report_adeptus_insights'},
            {key: 'table', component: 'report_adeptus_insights'},
            {key: 'chart', component: 'report_adeptus_insights'},
            {key: 'retry', component: 'report_adeptus_insights'},
            {key: 'cancel', component: 'report_adeptus_insights'},
            {key: 'error', component: 'report_adeptus_insights'},
            // Export
            {key: 'export_pdf', component: 'report_adeptus_insights'},
            {key: 'export_csv', component: 'report_adeptus_insights'},
            {key: 'export_json', component: 'report_adeptus_insights'},
            {key: 'exporting_as', component: 'report_adeptus_insights'},
            {key: 'export_not_available', component: 'report_adeptus_insights'},
            {key: 'export_not_eligible', component: 'report_adeptus_insights'},
            {key: 'export_failed', component: 'report_adeptus_insights'},
            {key: 'export_failed_message', component: 'report_adeptus_insights'},
            {key: 'file_downloaded_success', component: 'report_adeptus_insights'},
            {key: 'no_data', component: 'report_adeptus_insights'},
            {key: 'no_data_to_export', component: 'report_adeptus_insights'},
            {key: 'no_data_available', component: 'report_adeptus_insights'},
            {key: 'no_data_rows', component: 'report_adeptus_insights'},
            // Premium
            {key: 'premium_export_format', component: 'report_adeptus_insights'},
            {key: 'premium_feature', component: 'report_adeptus_insights'},
            {key: 'upgrade_now', component: 'report_adeptus_insights'},
            {key: 'free_plan_label', component: 'report_adeptus_insights'},
            {key: 'free_plan_pdf_only', component: 'report_adeptus_insights'},
            {key: 'paid_plans_label', component: 'report_adeptus_insights'},
            {key: 'paid_plans_all_formats', component: 'report_adeptus_insights'},
            {key: 'export_upgrade_message', component: 'report_adeptus_insights'},
            // Categories
            {key: 'categories', component: 'report_adeptus_insights'},
            {key: 'create_new_category', component: 'report_adeptus_insights'},
            {key: 'custom_color', component: 'report_adeptus_insights'},
            {key: 'your_categories', component: 'report_adeptus_insights'},
            {key: 'default_categories', component: 'report_adeptus_insights'},
            {key: 'read_only', component: 'report_adeptus_insights'},
            {key: 'no_custom_categories', component: 'report_adeptus_insights'},
            {key: 'create_first_category', component: 'report_adeptus_insights'},
            {key: 'category_created', component: 'report_adeptus_insights'},
            {key: 'category_updated', component: 'report_adeptus_insights'},
            {key: 'category_deleted', component: 'report_adeptus_insights'},
            {key: 'edit_category', component: 'report_adeptus_insights'},
            {key: 'delete_category', component: 'report_adeptus_insights'},
            {key: 'delete_category_confirm', component: 'report_adeptus_insights'},
            {key: 'delete_category_warning', component: 'report_adeptus_insights'},
            {key: 'yes_delete', component: 'report_adeptus_insights'},
            {key: 'category_name', component: 'report_adeptus_insights'},
            {key: 'please_enter_category_name', component: 'report_adeptus_insights'},
            {key: 'name', component: 'report_adeptus_insights'},
            {key: 'color', component: 'report_adeptus_insights'},
            {key: 'custom', component: 'report_adeptus_insights'},
            {key: 'save_changes', component: 'report_adeptus_insights'},
            // Reports
            {key: 'select_a_report', component: 'report_adeptus_insights'},
            {key: 'no_report_selected', component: 'report_adeptus_insights'},
            {key: 'delete_report', component: 'report_adeptus_insights'},
            {key: 'delete_report_confirm', component: 'report_adeptus_insights'},
            {key: 'delete_report_warning', component: 'report_adeptus_insights'},
            {key: 'deleting_report', component: 'report_adeptus_insights'},
            {key: 'deleting_report_wait', component: 'report_adeptus_insights'},
            {key: 'delete_failed', component: 'report_adeptus_insights'},
            {key: 'report_deleted_success', component: 'report_adeptus_insights'},
            {key: 'report_count', component: 'report_adeptus_insights'},
            {key: 'reports_count', component: 'report_adeptus_insights'},
            {key: 'you_have_reports_saved', component: 'report_adeptus_insights'},
            // Loading/Errors
            {key: 'session_expired', component: 'report_adeptus_insights'},
            {key: 'refresh_page', component: 'report_adeptus_insights'},
            {key: 'refresh_page_to_continue', component: 'report_adeptus_insights'},
            {key: 'failed_to_load_reports', component: 'report_adeptus_insights'},
            {key: 'try_again_later', component: 'report_adeptus_insights'},
            {key: 'failed_to_execute_report', component: 'report_adeptus_insights'},
            {key: 'failed_to_load_report_details', component: 'report_adeptus_insights'},
            {key: 'failed_to_generate_report', component: 'report_adeptus_insights'},
            {key: 'failed_to_execute_wizard_report', component: 'report_adeptus_insights'},
            {key: 'authentication_required', component: 'report_adeptus_insights'},
            {key: 'authentication_required_message', component: 'report_adeptus_insights'},
            // DataTable
            {key: 'search_reports', component: 'report_adeptus_insights'},
            {key: 'no_reports_found', component: 'report_adeptus_insights'},
            {key: 'showing_reports', component: 'report_adeptus_insights'},
            {key: 'showing_first_rows', component: 'report_adeptus_insights'},
            {key: 'export_to_see_all', component: 'report_adeptus_insights'},
            // Chart
            {key: 'chart_type', component: 'report_adeptus_insights'},
            {key: 'bar_chart', component: 'report_adeptus_insights'},
            {key: 'line_chart', component: 'report_adeptus_insights'},
            {key: 'pie_chart', component: 'report_adeptus_insights'},
            {key: 'doughnut_chart', component: 'report_adeptus_insights'},
            {key: 'x_axis_labels', component: 'report_adeptus_insights'},
            {key: 'y_axis_values', component: 'report_adeptus_insights'},
            {key: 'error_rendering_chart', component: 'report_adeptus_insights'},
            {key: 'loading', component: 'report_adeptus_insights'}
        ]).then(function(results) {
            // Map results to keys
            var keys = [
                'general', 'wizard', 'ai', 'report', 'reportDetails', 'untitledReport',
                'unknown', 'rows', 'table', 'chart', 'retry', 'cancel', 'error',
                'exportPdf', 'exportCsv', 'exportJson', 'exportingAs', 'exportNotAvailable',
                'exportNotEligible', 'exportFailed', 'exportFailedMessage', 'fileDownloadedSuccess',
                'noData', 'noDataToExport', 'noDataAvailable', 'noDataRows',
                'premiumExportFormat', 'premiumFeature', 'upgradeNow', 'freePlan',
                'freePlanPdfOnly', 'paidPlans', 'paidPlansAllFormats', 'exportUpgradeMessage',
                'categories', 'createNewCategory', 'customColor', 'yourCategories',
                'defaultCategories', 'readOnly', 'noCustomCategories', 'createFirstCategory',
                'categoryCreated', 'categoryUpdated', 'categoryDeleted', 'editCategory',
                'deleteCategory', 'deleteCategoryConfirm', 'deleteCategoryWarning', 'yesDelete',
                'categoryName', 'pleaseEnterCategoryName', 'name', 'color', 'custom', 'saveChanges',
                'selectAReport', 'noReportSelected', 'deleteReport', 'deleteReportConfirm',
                'deleteReportWarning', 'deletingReport', 'deletingReportWait', 'deleteFailed',
                'reportDeletedSuccess', 'reportCount', 'reportsCount', 'youHaveReportsSaved',
                'sessionExpired', 'refreshPage', 'refreshPageToContinue', 'failedToLoadReports',
                'tryAgainLater', 'failedToExecuteReport', 'failedToLoadReportDetails',
                'failedToGenerateReport', 'failedToExecuteWizardReport',
                'authenticationRequired', 'authenticationRequiredMessage',
                'searchReports', 'noReportsFound', 'showingReports', 'showingFirstRows', 'exportToSeeAll',
                'chartType', 'barChart', 'lineChart', 'pieChart', 'doughnutChart',
                'xAxisLabels', 'yAxisValues', 'errorRenderingChart', 'loading'
            ];
            keys.forEach(function(key, index) {
                STRINGS[key] = results[index] || key;
            });
            return STRINGS;
        }).catch(function() {
            // Provide fallback strings if loading fails
            STRINGS = {
                general: 'General',
                wizard: 'Wizard',
                ai: 'AI',
                report: 'Report',
                reportDetails: 'Report Details',
                untitledReport: 'Untitled Report',
                unknown: 'Unknown',
                rows: 'rows',
                table: 'Table',
                chart: 'Chart',
                retry: 'Retry',
                cancel: 'Cancel',
                error: 'Error',
                exportPdf: 'Export PDF',
                exportCsv: 'Export CSV',
                exportJson: 'Export JSON',
                exportingAs: 'Exporting as {format}...',
                exportNotAvailable: 'Export Not Available',
                exportNotEligible: 'Export not eligible',
                exportFailed: 'Export Failed',
                exportFailedMessage: 'Failed to export report',
                fileDownloadedSuccess: 'file downloaded successfully',
                noData: 'No Data',
                noDataToExport: 'No data to export',
                noDataAvailable: 'No data available',
                noDataRows: 'This report contains no data rows',
                premiumExportFormat: 'Premium Export Format',
                premiumFeature: 'Premium Feature',
                upgradeNow: 'Upgrade Now',
                freePlan: 'Free Plan:',
                freePlanPdfOnly: 'PDF export only',
                paidPlans: 'Paid Plans:',
                paidPlansAllFormats: 'All formats (PDF, CSV, JSON)',
                exportUpgradeMessage: '{format} export requires a paid plan',
                categories: 'Categories',
                createNewCategory: 'Create New Category',
                customColor: 'Custom color',
                yourCategories: 'Your Categories',
                defaultCategories: 'Default Categories',
                readOnly: 'Read Only',
                noCustomCategories: 'No custom categories yet',
                createFirstCategory: 'Create your first category above',
                categoryCreated: 'Category Created',
                categoryUpdated: 'Category Updated',
                categoryDeleted: 'Category Deleted',
                editCategory: 'Edit Category',
                deleteCategory: 'Delete Category',
                deleteCategoryConfirm: 'Delete Category?',
                deleteCategoryWarning: 'Are you sure you want to delete {name}?',
                yesDelete: 'Yes, Delete',
                categoryName: 'Category name',
                pleaseEnterCategoryName: 'Please enter a category name',
                name: 'Name',
                color: 'Color',
                custom: 'Custom',
                saveChanges: 'Save Changes',
                selectAReport: 'Select a Report',
                noReportSelected: 'No Report Selected',
                deleteReport: 'Delete Report',
                deleteReportConfirm: 'Delete Report?',
                deleteReportWarning: 'Are you sure you want to delete {name}?',
                deletingReport: 'Deleting Report',
                deletingReportWait: 'Please wait...',
                deleteFailed: 'Delete Failed',
                reportDeletedSuccess: 'Report deleted successfully',
                reportCount: '1 report',
                reportsCount: 'reports',
                youHaveReportsSaved: 'You have {count} saved',
                sessionExpired: 'Session Expired',
                refreshPage: 'Refresh Page',
                refreshPageToContinue: 'Please refresh the page to continue',
                failedToLoadReports: 'Failed to Load Reports',
                tryAgainLater: 'Please try again later',
                failedToExecuteReport: 'Failed to execute report',
                failedToLoadReportDetails: 'Failed to load report details',
                failedToGenerateReport: 'Failed to generate report',
                failedToExecuteWizardReport: 'Failed to execute wizard report',
                authenticationRequired: 'Authentication Required',
                authenticationRequiredMessage: 'Please ensure you are logged in',
                searchReports: 'Search reports...',
                noReportsFound: 'No reports found',
                showingReports: 'Showing reports',
                showingFirstRows: 'Showing first {first} of {total} rows',
                exportToSeeAll: 'Export to see all data',
                chartType: 'Chart Type',
                barChart: 'Bar Chart',
                lineChart: 'Line Chart',
                pieChart: 'Pie Chart',
                doughnutChart: 'Doughnut Chart',
                xAxisLabels: 'X-Axis (Labels)',
                yAxisValues: 'Y-Axis (Values)',
                errorRenderingChart: 'Error rendering chart',
                loading: 'Loading...'
            };
            return STRINGS;
        });
    };

    /**
     * Main GeneratedReports object.
     * @type {Object}
     */
    var GeneratedReports = {
        backendUrl: '',
        cachedReports: [],
        cachedCategories: [],
        currentReport: null,
        currentReportData: null,
        reportsDataTable: null,
        retryCount: 0,
        maxRetries: 3,
        currentView: 'table',
        chartInstance: null,
        Chart: Chart,
        strings: STRINGS,
        isFreePlan: true,

        /**
         * Initialize the module.
         * @param {Object} config - Configuration object from PHP.
         */
        init: function(config) {
            var self = this;
            self.backendUrl = config.backendUrl || '';
            self.isFreePlan = config.isFreePlan !== false;
            self.strings = STRINGS;

            self.loadReports();
            self.loadCategories();
            self.bindViewToggle();
            self.bindCategoryManagement();
        },

        /**
         * Show upgrade prompt for premium export formats.
         * @param {string} format - The export format.
         */
        showExportUpgradePrompt: function(format) {
            var subscriptionUrl = M.cfg.wwwroot + '/report/adeptus_insights/subscription.php';
            var formatNames = {
                'csv': 'CSV',
                'excel': 'Excel',
                'json': 'JSON'
            };
            var formatName = formatNames[format] || format.toUpperCase();

            Swal.fire({
                title: STRINGS.premiumExportFormat,
                html: '<div style="text-align: center; padding: 20px;">' +
                    '<div style="font-size: 48px; color: #f39c12; margin-bottom: 15px;">' +
                    '<i class="fa fa-crown"></i></div>' +
                    '<h3 style="color: #2c3e50; margin-bottom: 15px;">' + formatName + ' ' + STRINGS.premiumFeature + '</h3>' +
                    '<p style="color: #7f8c8d; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">' +
                    STRINGS.exportUpgradeMessage.replace('{format}', formatName) + '</p>' +
                    '<div style="background: #ecf0f1; padding: 15px; border-radius: 8px; margin-bottom: 20px;">' +
                    '<p style="margin: 0; font-size: 14px; color: #34495e;">' +
                    '<strong>' + STRINGS.freePlan + '</strong> ' + STRINGS.freePlanPdfOnly + '<br>' +
                    '<strong>' + STRINGS.paidPlans + '</strong> ' + STRINGS.paidPlansAllFormats + '</p></div></div>',
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-arrow-up"></i> ' + STRINGS.upgradeNow,
                cancelButtonText: '<i class="fa fa-times"></i> ' + STRINGS.cancel,
                confirmButtonColor: '#3498db',
                cancelButtonColor: '#95a5a6',
                width: 500
            }).then(function(result) {
                if (result.isConfirmed) {
                    window.open(subscriptionUrl, '_blank');
                }
            });
        },

        /**
         * Check export eligibility before exporting using external service.
         * @param {string} format - The export format.
         * @returns {Promise}
         */
        checkExportEligibility: function(format) {
            return new Promise(function(resolve) {
                Ajax.call([{
                    methodname: 'report_adeptus_insights_check_export_eligibility',
                    args: {format: format}
                }])[0].done(function(result) {
                    var data = result.data ? result.data : result;
                    resolve(data);
                }).fail(function(error) {
                    window.console.error('Error checking export eligibility:', error);
                    resolve({success: false, eligible: false, message: 'Unable to verify export eligibility.'});
                });
            });
        },

        /**
         * Capture chart as image for PDF export.
         * Uses native toDataURL with html2canvas fallback.
         * @returns {Promise}
         */
        captureChartImage: function() {
            var self = this;

            return new Promise(function(resolve) {
                // Wait for chart animation to complete
                setTimeout(function() {
                    var chartCanvas = document.getElementById('report-chart');
                    if (!chartCanvas) {
                        window.console.warn('[GeneratedReports] Chart canvas not found');
                        resolve(null);
                        return;
                    }

                    // Method 1: Native canvas toDataURL (fastest, most reliable)
                    try {
                        var dataUrl = chartCanvas.toDataURL('image/png', 0.8);
                        if (dataUrl && dataUrl.length > 100 && dataUrl.length < 2000000) {
                            window.console.log('[GeneratedReports] Chart captured via toDataURL, size:', dataUrl.length);
                            resolve(dataUrl);
                            return;
                        }
                    } catch (e) {
                        window.console.warn('[GeneratedReports] Native canvas capture failed:', e);
                    }

                    // Method 2: html2canvas fallback (handles CORS/tainted canvas)
                    self.loadHtml2Canvas().then(function() {
                        if (window.html2canvas) {
                            window.html2canvas(chartCanvas, {
                                backgroundColor: '#ffffff',
                                scale: 1.5,
                                useCORS: true,
                                allowTaint: true,
                                logging: false
                            }).then(function(capturedCanvas) {
                                var dataUrl = capturedCanvas.toDataURL('image/png', 0.8);
                                if (dataUrl && dataUrl.length > 100 && dataUrl.length < 2000000) {
                                    window.console.log('[GeneratedReports] Chart captured via html2canvas, size:', dataUrl.length);
                                    resolve(dataUrl);
                                    return;
                                }
                                resolve(null);
                            }).catch(function() {
                                resolve(null);
                            });
                        } else {
                            resolve(null);
                        }
                    }).catch(function() {
                        resolve(null);
                    });
                }, 300);
            });
        },

        /**
         * Load html2canvas library from CDN.
         * @returns {Promise}
         */
        loadHtml2Canvas: function() {
            return new Promise(function(resolve, reject) {
                if (window.html2canvas) {
                    resolve();
                    return;
                }
                var script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                script.onload = function() {
                    resolve();
                };
                script.onerror = function() {
                    reject(new Error('Failed to load html2canvas'));
                };
                document.head.appendChild(script);
            });
        },

        /**
         * Show toast notification.
         * @param {string} message - The message to display.
         * @param {string} type - The type of toast (success, error, warning, info).
         */
        showToast: function(message, type) {
            type = type || 'success';
            var toastContainer = document.getElementById('adeptus-toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'adeptus-toast-container';
                toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
                document.body.appendChild(toastContainer);
            }

            var iconMap = {
                'success': 'fa-check-circle',
                'error': 'fa-times-circle',
                'warning': 'fa-exclamation-triangle',
                'info': 'fa-info-circle'
            };
            var bgColorMap = {
                'success': '#28a745',
                'error': '#dc3545',
                'warning': '#ffc107',
                'info': '#17a2b8'
            };
            var textColorMap = {
                'success': 'white',
                'error': 'white',
                'warning': '#212529',
                'info': 'white'
            };

            var toast = document.createElement('div');
            toast.style.cssText = 'background: ' + bgColorMap[type] + '; color: ' + textColorMap[type] +
                '; padding: 12px 20px; border-radius: 8px; margin-bottom: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);' +
                ' display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease; min-width: 280px;';
            toast.innerHTML = '<i class="fa ' + iconMap[type] + '"></i><span>' + message + '</span>';
            toastContainer.appendChild(toast);

            setTimeout(function() {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(function() {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        },

        /**
         * Track export for usage monitoring.
         * @param {string} format - The export format.
         * @param {string} reportName - The report name.
         */
        trackExport: function(format, reportName) {
            fetch(M.cfg.wwwroot + '/report/adeptus_insights/ajax/track_export.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'format=' + encodeURIComponent(format) + '&report_name=' + encodeURIComponent(reportName) +
                    '&sesskey=' + M.cfg.sesskey
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                window.console.log('[GeneratedReports] Export tracked:', data);
            })
            .catch(function(error) {
                window.console.error('[GeneratedReports] Error tracking export:', error);
            });
        },

        /**
         * Bind view toggle button handlers.
         */
        bindViewToggle: function() {
            var self = this;
            $(document).on('click', '.adeptus-view-toggle-btn', function() {
                var view = $(this).data('view');
                self.switchView(view);
            });
            $(document).on('change', '#chart-type-select, #chart-x-axis, #chart-y-axis', function() {
                self.renderChart();
            });
        },

        /**
         * Bind category management button handler.
         */
        bindCategoryManagement: function() {
            var self = this;
            $('#manage-categories-btn').on('click', function() {
                self.showCategoryManagementModal();
            });
        },

        /**
         * Load categories from server.
         */
        loadCategories: function() {
            var self = this;

            $.ajax({
                url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/manage_category.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'list',
                    sesskey: M.cfg.sesskey
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.cachedCategories = response.data;
                        // Enrich reports with category info now that categories are loaded.
                        if (self.cachedReports && self.cachedReports.length > 0) {
                            self.enrichReportsWithCategoryInfo();
                        }
                    }
                },
                error: function() {
                    self.cachedCategories = [];
                }
            });
        },

        /**
         * Enrich cached reports with category_info from cached categories.
         * This ensures category colors and full info are available after page refresh.
         */
        enrichReportsWithCategoryInfo: function() {
            var self = this;
            if (!this.cachedCategories || this.cachedCategories.length === 0) {
                return;
            }

            this.cachedReports.forEach(function(report) {
                // Skip if already has full category_info.
                if (report.category_info && report.category_info.id && report.category_info.color) {
                    return;
                }

                var matchedCategory = null;

                // Try to match by category_id first.
                if (report.category_id) {
                    matchedCategory = self.cachedCategories.find(function(c) {
                        return c.id === report.category_id || c.id === parseInt(report.category_id, 10);
                    });
                }

                // Try to match by category name if no id match.
                if (!matchedCategory && report.category) {
                    matchedCategory = self.cachedCategories.find(function(c) {
                        return c.name && c.name.toLowerCase() === report.category.toLowerCase();
                    });
                }

                // Try to match by category_info.name if available.
                if (!matchedCategory && report.category_info && report.category_info.name) {
                    matchedCategory = self.cachedCategories.find(function(c) {
                        return c.name && c.name.toLowerCase() === report.category_info.name.toLowerCase();
                    });
                }

                // Build category_info from matched category or defaults.
                if (matchedCategory) {
                    report.category_info = {
                        id: matchedCategory.id,
                        name: matchedCategory.name,
                        color: matchedCategory.color || '#6c757d'
                    };
                    report.category = matchedCategory.name;
                    report.category_id = matchedCategory.id;
                } else {
                    // Default to General if no match found.
                    var generalCat = self.cachedCategories.find(function(c) {
                        return c.name && c.name.toLowerCase() === 'general';
                    });
                    if (generalCat) {
                        report.category_info = {
                            id: generalCat.id,
                            name: generalCat.name,
                            color: generalCat.color || '#6c757d'
                        };
                    }
                }
            });
        },

        /**
         * Show category management modal.
         */
        showCategoryManagementModal: function() {
            var self = this;

            // Separate system and custom categories
            var systemCategories = self.cachedCategories.filter(function(c) {
                return c.is_system;
            });
            var customCategories = self.cachedCategories.filter(function(c) {
                return !c.is_system;
            });

            // Preset colors for quick selection
            var presetColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];

            // Build custom categories HTML
            var customCategoriesHtml = customCategories.length > 0 ?
                customCategories.map(function(cat) {
                    return '<div class="adeptus-cat-card" data-id="' + cat.id + '">' +
                        '<div class="adeptus-cat-card-color" style="background: ' + (cat.color || '#6c757d') + ';"></div>' +
                        '<div class="adeptus-cat-card-content">' +
                            '<div class="adeptus-cat-card-name">' + cat.name + '</div>' +
                            '<div class="adeptus-cat-card-meta">' + (cat.report_count || 0) + ' report' +
                            (cat.report_count !== 1 ? 's' : '') + '</div>' +
                        '</div>' +
                        '<div class="adeptus-cat-card-actions">' +
                            '<button class="adeptus-cat-action-btn edit-cat-btn" data-id="' + cat.id + '" title="Edit">' +
                                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
                                'stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">' +
                                '</path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>' +
                            '</button>' +
                            '<button class="adeptus-cat-action-btn adeptus-cat-action-btn-danger delete-cat-btn" ' +
                            'data-id="' + cat.id + '" title="Delete">' +
                                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
                                'stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 ' +
                                '0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>' +
                            '</button>' +
                        '</div>' +
                    '</div>';
                }).join('') :
                '<div class="adeptus-cat-empty-state">' +
                    '<div class="adeptus-cat-empty-icon">' +
                        '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
                        'stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 ' +
                        '2 0 0 1 2 2z"></path><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" ' +
                        'x2="15" y2="14"></line></svg>' +
                    '</div>' +
                    '<div class="adeptus-cat-empty-text">' + STRINGS.noCustomCategories + '</div>' +
                    '<div class="adeptus-cat-empty-hint">' + STRINGS.createFirstCategory + '</div>' +
                '</div>';

            // Build system categories HTML
            var systemCategoriesHtml = systemCategories.map(function(cat) {
                return '<div class="adeptus-cat-system-item">' +
                    '<span class="adeptus-cat-system-dot" style="background: ' + (cat.color || '#6c757d') + ';"></span>' +
                    '<span class="adeptus-cat-system-name">' + cat.name + '</span>' +
                    '<span class="adeptus-cat-system-count">' + (cat.report_count || 0) + '</span>' +
                '</div>';
            }).join('');

            // Build color swatches HTML
            var colorSwatchesHtml = presetColors.map(function(color) {
                return '<button type="button" class="adeptus-cat-color-swatch" data-color="' + color +
                    '" style="background: ' + color + ';"></button>';
            }).join('');

            Swal.fire({
                title: STRINGS.categories,
                html: '<div class="adeptus-cat-modal">' +
                    '<div class="adeptus-cat-add-section">' +
                        '<div class="adeptus-cat-add-header">' + STRINGS.createNewCategory + '</div>' +
                        '<div class="adeptus-cat-add-form">' +
                            '<div class="adeptus-cat-add-input-wrapper">' +
                                '<input type="text" id="new-category-name" class="adeptus-cat-add-input" ' +
                                'placeholder="' + STRINGS.categoryName + '">' +
                                '<div class="adeptus-cat-add-color-wrapper">' +
                                    '<button type="button" class="adeptus-cat-add-color-btn" id="color-picker-toggle">' +
                                        '<span class="adeptus-cat-add-color-preview" id="selected-color-preview" ' +
                                        'style="background: #3b82f6;"></span>' +
                                        '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" ' +
                                        'stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9">' +
                                        '</polyline></svg>' +
                                    '</button>' +
                                    '<div class="adeptus-cat-color-dropdown" id="color-dropdown">' +
                                        '<div class="adeptus-cat-color-swatches">' + colorSwatchesHtml + '</div>' +
                                        '<div class="adeptus-cat-color-custom">' +
                                            '<input type="color" id="new-category-color" value="#3b82f6">' +
                                            '<span>' + STRINGS.customColor + '</span>' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                            '<button type="button" class="adeptus-cat-add-btn" id="add-category-btn">' +
                                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
                                'stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" ' +
                                'x2="19" y2="12"></line></svg> Add' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="adeptus-cat-section">' +
                        '<div class="adeptus-cat-section-header">' +
                            '<span class="adeptus-cat-section-title">' + STRINGS.yourCategories + '</span>' +
                            '<span class="adeptus-cat-section-count">' + customCategories.length + '</span>' +
                        '</div>' +
                        '<div class="adeptus-cat-cards-list">' + customCategoriesHtml + '</div>' +
                    '</div>' +
                    '<div class="adeptus-cat-section adeptus-cat-section-system">' +
                        '<div class="adeptus-cat-section-header">' +
                            '<span class="adeptus-cat-section-title">' + STRINGS.defaultCategories + '</span>' +
                            '<span class="adeptus-cat-section-badge">' + STRINGS.readOnly + '</span>' +
                        '</div>' +
                        '<div class="adeptus-cat-system-list">' + systemCategoriesHtml + '</div>' +
                    '</div>' +
                '</div>',
                showConfirmButton: false,
                showCloseButton: true,
                width: '480px',
                customClass: {
                    popup: 'adeptus-cat-modal-popup',
                    title: 'adeptus-cat-modal-title',
                    closeButton: 'adeptus-cat-modal-close',
                    htmlContainer: 'adeptus-cat-modal-container'
                },
                didOpen: function() {
                    var selectedColor = '#3b82f6';

                    $('#color-picker-toggle').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var dropdown = $('#color-dropdown');
                        dropdown.toggleClass('show');
                    });

                    $('#color-dropdown').on('click', function(e) {
                        e.stopPropagation();
                    });

                    $(document).on('click.colorDropdown', function(e) {
                        if (!$(e.target).closest('.adeptus-cat-add-color-wrapper').length) {
                            $('#color-dropdown').removeClass('show');
                        }
                    });

                    $('.adeptus-cat-color-swatch').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        selectedColor = $(this).data('color');
                        $('#selected-color-preview').css('background', selectedColor);
                        $('#new-category-color').val(selectedColor);
                        $('#color-dropdown').removeClass('show');
                    });

                    $('#new-category-color').on('input change', function(e) {
                        e.stopPropagation();
                        selectedColor = $(this).val();
                        $('#selected-color-preview').css('background', selectedColor);
                    });

                    $('#add-category-btn').on('click', function() {
                        var name = $('#new-category-name').val().trim();
                        if (name) {
                            self.createCategory(name, selectedColor);
                        } else {
                            $('#new-category-name').addClass('error').focus();
                            setTimeout(function() {
                                $('#new-category-name').removeClass('error');
                            }, 500);
                        }
                    });

                    $('#new-category-name').on('keypress', function(e) {
                        if (e.which === 13) {
                            $('#add-category-btn').click();
                        }
                    });

                    $('.edit-cat-btn').on('click', function(e) {
                        e.stopPropagation();
                        var catId = $(this).data('id');
                        var cat = self.cachedCategories.find(function(c) {
                            return c.id == catId;
                        });
                        if (cat) {
                            $(document).off('click.colorDropdown');
                            self.showEditCategoryDialog(cat);
                        }
                    });

                    $('.delete-cat-btn').on('click', function(e) {
                        e.stopPropagation();
                        var catId = $(this).data('id');
                        var cat = self.cachedCategories.find(function(c) {
                            return c.id == catId;
                        });
                        if (cat) {
                            $(document).off('click.colorDropdown');
                            self.confirmDeleteCategory(cat);
                        }
                    });
                },
                willClose: function() {
                    $(document).off('click.colorDropdown');
                }
            });
        },

        /**
         * Create a new category.
         * @param {string} name - The category name.
         * @param {string} color - The category color.
         */
        createCategory: function(name, color) {
            var self = this;

            $.ajax({
                url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/manage_category.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'create',
                    name: name,
                    color: color,
                    sesskey: M.cfg.sesskey
                },
                success: function(response) {
                    if (response.success) {
                        self.cachedCategories.push(response.data);
                        Swal.close();
                        Swal.fire({
                            icon: 'success',
                            title: STRINGS.categoryCreated,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            self.showCategoryManagementModal();
                        });
                    } else {
                        Swal.showValidationMessage(response.message);
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to create category';
                    Swal.fire({icon: 'error', title: STRINGS.error, text: msg});
                }
            });
        },

        /**
         * Show edit category dialog.
         * @param {Object} category - The category to edit.
         */
        showEditCategoryDialog: function(category) {
            var self = this;
            var presetColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];

            var colorSwatchesHtml = presetColors.map(function(color) {
                var isSelected = color.toLowerCase() === (category.color || '#6c757d').toLowerCase();
                return '<button type="button" class="adeptus-edit-color-swatch' + (isSelected ? ' selected' : '') +
                    '" data-color="' + color + '" style="background: ' + color + ';"></button>';
            }).join('');

            Swal.fire({
                title: STRINGS.editCategory,
                html: '<div class="adeptus-cat-edit-modal">' +
                    '<div class="adeptus-cat-edit-preview" id="edit-preview">' +
                        '<div class="adeptus-cat-edit-preview-color" id="edit-preview-color" style="background: ' +
                        (category.color || '#6c757d') + ';"></div>' +
                        '<span class="adeptus-cat-edit-preview-name" id="edit-preview-name">' + category.name +
                        '</span>' +
                    '</div>' +
                    '<div class="adeptus-cat-edit-field">' +
                        '<label class="adeptus-cat-edit-label">' + STRINGS.name + '</label>' +
                        '<input type="text" id="edit-cat-name" class="adeptus-cat-edit-input" value="' +
                        category.name.replace(/"/g, '&quot;') + '">' +
                    '</div>' +
                    '<div class="adeptus-cat-edit-field">' +
                        '<label class="adeptus-cat-edit-label">' + STRINGS.color + '</label>' +
                        '<div class="adeptus-cat-edit-colors">' +
                            '<div class="adeptus-cat-edit-swatches">' + colorSwatchesHtml + '</div>' +
                            '<div class="adeptus-cat-edit-custom">' +
                                '<input type="color" id="edit-adeptus-cat-color" value="' +
                                (category.color || '#6c757d') + '">' +
                                '<span>' + STRINGS.custom + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>',
                showCancelButton: true,
                confirmButtonText: STRINGS.saveChanges,
                cancelButtonText: STRINGS.cancel,
                customClass: {
                    popup: 'adeptus-cat-edit-popup',
                    confirmButton: 'adeptus-cat-edit-confirm-btn',
                    cancelButton: 'adeptus-cat-edit-cancel-btn'
                },
                didOpen: function() {
                    var selectedColor = category.color || '#6c757d';

                    $('#edit-cat-name').on('input', function() {
                        $('#edit-preview-name').text($(this).val() || 'Category');
                    });

                    $('.adeptus-edit-color-swatch').on('click', function() {
                        selectedColor = $(this).data('color');
                        $('.adeptus-edit-color-swatch').removeClass('selected');
                        $(this).addClass('selected');
                        $('#edit-preview-color').css('background', selectedColor);
                        $('#edit-adeptus-cat-color').val(selectedColor);
                    });

                    $('#edit-adeptus-cat-color').on('input', function() {
                        selectedColor = $(this).val();
                        $('.adeptus-edit-color-swatch').removeClass('selected');
                        $('#edit-preview-color').css('background', selectedColor);
                    });
                },
                preConfirm: function() {
                    var name = document.getElementById('edit-cat-name').value.trim();
                    var color = document.getElementById('edit-adeptus-cat-color').value;
                    if (!name) {
                        Swal.showValidationMessage(STRINGS.pleaseEnterCategoryName);
                        return false;
                    }
                    return {name: name, color: color};
                }
            }).then(function(result) {
                if (result.isConfirmed) {
                    self.updateCategory(category.id, result.value.name, result.value.color);
                }
            });
        },

        /**
         * Update a category.
         * @param {number} categoryId - The category ID.
         * @param {string} name - The new name.
         * @param {string} color - The new color.
         */
        updateCategory: function(categoryId, name, color) {
            var self = this;

            $.ajax({
                url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/manage_category.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'update',
                    category_id: categoryId,
                    name: name,
                    color: color,
                    sesskey: M.cfg.sesskey
                },
                success: function(response) {
                    if (response.success) {
                        var idx = self.cachedCategories.findIndex(function(c) {
                            return c.id == categoryId;
                        });
                        if (idx !== -1) {
                            self.cachedCategories[idx] = response.data;
                        }
                        Swal.fire({
                            icon: 'success',
                            title: STRINGS.categoryUpdated,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            self.showCategoryManagementModal();
                        });
                    } else {
                        Swal.fire({icon: 'error', title: STRINGS.error, text: response.message});
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || '';
                    Swal.fire({icon: 'error', title: STRINGS.error, text: msg});
                }
            });
        },

        /**
         * Confirm delete category dialog.
         * @param {Object} category - The category to delete.
         */
        confirmDeleteCategory: function(category) {
            var self = this;

            Swal.fire({
                title: STRINGS.deleteCategoryConfirm,
                html: '<p>' + STRINGS.deleteCategoryWarning.replace('{name}', '<strong>' + category.name +
                    '</strong>') + '</p>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: STRINGS.yesDelete,
                cancelButtonText: STRINGS.cancel
            }).then(function(result) {
                if (result.isConfirmed) {
                    self.deleteCategory(category.id);
                }
            });
        },

        /**
         * Delete a category.
         * @param {number} categoryId - The category ID to delete.
         */
        deleteCategory: function(categoryId) {
            var self = this;

            $.ajax({
                url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/manage_category.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'delete',
                    category_id: categoryId,
                    sesskey: M.cfg.sesskey
                },
                success: function(response) {
                    if (response.success) {
                        self.cachedCategories = self.cachedCategories.filter(function(c) {
                            return c.id != categoryId;
                        });
                        Swal.fire({
                            icon: 'success',
                            title: STRINGS.categoryDeleted,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(function() {
                            self.showCategoryManagementModal();
                        });
                    } else {
                        Swal.fire({icon: 'error', title: STRINGS.error, text: response.message});
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to delete category';
                    Swal.fire({icon: 'error', title: STRINGS.error, text: msg});
                }
            });
        },

        /**
         * Show delete confirmation dialog for a report.
         * @param {Object} report - The report to delete.
         */
        confirmDeleteReport: function(report) {
            var self = this;

            if (!report) {
                report = self.currentReport;
            }

            if (!report || !report.slug) {
                Swal.fire({
                    icon: 'warning',
                    title: STRINGS.noReportSelected,
                    text: STRINGS.selectAReport
                });
                return;
            }

            var reportName = report.description || report.name || STRINGS.untitledReport;
            var source = (report.source || 'assistant').toLowerCase();

            Swal.fire({
                title: STRINGS.deleteReportConfirm,
                html: '<p>' + STRINGS.deleteReportWarning.replace('{name}', '<strong>' + reportName +
                    '</strong>') + '</p>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: '<i class="fa fa-trash"></i> ' + STRINGS.yesDelete,
                cancelButtonText: STRINGS.cancel
            }).then(function(result) {
                if (result.isConfirmed) {
                    self.deleteReport(report.slug, source, reportName);
                }
            });
        },

        /**
         * Delete a report.
         * @param {string} slug - The report slug/identifier.
         * @param {string} source - The report source ('assistant' or 'wizard').
         * @param {string} reportName - The report name for display.
         */
        deleteReport: function(slug, source, reportName) {
            var self = this;

            Swal.fire({
                title: STRINGS.deletingReport,
                text: STRINGS.deletingReportWait,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });

            var deletePromise = $.ajax({
                url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/manage_generated_reports.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'remove_single',
                    slug: slug,
                    source: source,
                    sesskey: M.cfg.sesskey
                }
            });

            deletePromise.then(function(response) {
                if (!response.success) {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: STRINGS.deleteFailed,
                        text: response.message
                    });
                    return;
                }

                self.trackReportDeleted(reportName, source === 'assistant');

                self.cachedReports = self.cachedReports.filter(function(r) {
                    return r.slug !== slug;
                });

                if (self.currentReport && self.currentReport.slug === slug) {
                    self.currentReport = null;
                    self.currentReportData = null;
                }

                self.renderReportsList(self.cachedReports);
                self.updateReportCount(self.cachedReports.length);

                $('#report-content').addClass('d-none');
                $('#report-placeholder').removeClass('d-none');
                $('#report-category-selector').addClass('d-none');
                $('.adeptus-report-view-title').text(STRINGS.selectAReport);

                Swal.close();
                self.showToast(STRINGS.reportDeletedSuccess, 'success');

            }).catch(function(xhr) {
                Swal.close();
                var errorMsg = '';
                try {
                    var response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || '';
                } catch (e) {
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                }
                Swal.fire({
                    icon: 'error',
                    title: STRINGS.deleteFailed,
                    text: errorMsg
                });
            });
        },

        /**
         * Track report deletion to update quota.
         * @param {string} reportName - The name of the deleted report.
         * @param {boolean} isAiGenerated - Whether the report was AI-generated.
         */
        trackReportDeleted: function(reportName, isAiGenerated) {
            $.ajax({
                url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/track_report_deleted.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    report_name: reportName,
                    is_ai_generated: isAiGenerated ? 1 : 0,
                    sesskey: M.cfg.sesskey
                }
            }).done(function(response) {
                window.console.log('[GeneratedReports] Report deletion tracked:', response);
            }).fail(function(error) {
                window.console.warn('[GeneratedReports] Failed to track report deletion:', error);
            });
        },

        /**
         * Show the category selector for the current report.
         * @param {Object} report - The report to show category selector for.
         */
        showReportCategorySelector: function(report) {
            var self = this;

            // Store the report slug so we can always get fresh data from cache.
            this.currentCategoryReportSlug = report.slug;

            // Helper to get fresh report from cache.
            var getFreshReport = function() {
                var cached = self.cachedReports.find(function(r) {
                    return r.slug === self.currentCategoryReportSlug;
                });
                return cached || report;
            };

            // Helper to get fresh category info.
            var getFreshCategoryInfo = function() {
                var r = getFreshReport();
                return r.category_info || {name: r.category || STRINGS.general, color: '#6c757d'};
            };

            // Update the displayed category.
            var updateCategoryDisplay = function() {
                var catInfo = getFreshCategoryInfo();
                $('#category-dot').css('background', catInfo.color || '#6c757d');
                $('#category-name').text(catInfo.name || STRINGS.general);
            };

            updateCategoryDisplay();
            $('#report-category-selector').removeClass('d-none');

            var buildDropdown = function() {
                var currentReport = getFreshReport();
                var catInfo = getFreshCategoryInfo();

                var menuHtml = self.cachedCategories.map(function(cat) {
                    var isSelected = (catInfo.id && cat.id === catInfo.id) ||
                                     (currentReport.category_id && cat.id === currentReport.category_id) ||
                                     (!catInfo.id && !currentReport.category_id && cat.name.toLowerCase() ===
                                     (catInfo.name || 'general').toLowerCase());
                    return '<button type="button" class="adeptus-category-menu-item' +
                        (isSelected ? ' selected' : '') + '" data-id="' + cat.id + '" data-name="' + cat.name +
                        '" data-color="' + (cat.color || '#6c757d') + '">' +
                        '<span class="adeptus-category-menu-dot" style="background: ' + (cat.color || '#6c757d') +
                        ';"></span>' +
                        '<span class="adeptus-category-menu-name">' + cat.name + '</span>' +
                        (isSelected ? '<svg class="adeptus-category-menu-check" width="16" height="16" ' +
                        'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline ' +
                        'points="20 6 9 17 4 12"></polyline></svg>' : '') +
                    '</button>';
                }).join('');

                $('#category-dropdown-menu').html(menuHtml);

                $('.adeptus-category-menu-item').off('click').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var catId = $(this).data('id');
                    var catName = $(this).data('name');
                    var catColor = $(this).data('color');
                    $('#category-dropdown-menu').removeClass('show');
                    var freshReport = getFreshReport();
                    self.updateReportCategory(freshReport.slug, catId, catName, catColor, freshReport);
                    // Update display immediately.
                    $('#category-dot').css('background', catColor);
                    $('#category-name').text(catName);
                });
            };

            // Always rebuild dropdown when button is clicked to get fresh data.
            $('#category-current-btn').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Rebuild dropdown with fresh category info before showing.
                if (self.cachedCategories && self.cachedCategories.length > 0) {
                    buildDropdown();
                }
                $('#category-dropdown-menu').toggleClass('show');
            });

            // Initial dropdown build.
            if (!this.cachedCategories || this.cachedCategories.length === 0) {
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/manage_category.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'list',
                        sesskey: M.cfg.sesskey
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            self.cachedCategories = response.data;
                        }
                        buildDropdown();
                    },
                    error: function() {
                        self.cachedCategories = [];
                        buildDropdown();
                    }
                });
            } else {
                buildDropdown();
            }

            $('#category-dropdown-menu').off('click').on('click', function(e) {
                e.stopPropagation();
            });

            $(document).off('click.categoryDropdown').on('click.categoryDropdown', function(e) {
                if (!$(e.target).closest('.category-dropdown-wrapper').length) {
                    $('#category-dropdown-menu').removeClass('show');
                }
            });
        },

        /**
         * Update a report's category.
         * @param {string} slug - The report slug.
         * @param {number} categoryId - The new category ID.
         * @param {string} categoryName - The new category name.
         * @param {string} categoryColor - The new category color.
         * @param {Object} report - The report object.
         */
        updateReportCategory: function(slug, categoryId, categoryName, categoryColor, report) {
            var self = this;
            var source = report ? (report.source || 'assistant') : 'assistant';

            // Update display immediately.
            $('#category-dot').css('background', categoryColor);
            $('#category-name').text(categoryName);

            // Optimistic update: update cache immediately so dropdown shows correct selection.
            var cacheIndex = self.cachedReports.findIndex(function(r) {
                return r.slug === slug;
            });
            var oldCategory = null;
            var oldCategoryId = null;
            var oldCategoryInfo = null;

            if (cacheIndex !== -1) {
                // Store old values for potential rollback.
                oldCategory = self.cachedReports[cacheIndex].category;
                oldCategoryId = self.cachedReports[cacheIndex].category_id;
                oldCategoryInfo = self.cachedReports[cacheIndex].category_info;

                // Update cache immediately.
                self.cachedReports[cacheIndex].category = categoryName;
                self.cachedReports[cacheIndex].category_id = categoryId;
                self.cachedReports[cacheIndex].category_info = {
                    id: categoryId,
                    name: categoryName,
                    color: categoryColor
                };
            }

            $.ajax({
                url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/update_report_category.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    slug: slug,
                    category_id: categoryId,
                    source: source,
                    sesskey: M.cfg.sesskey
                },
                success: function(response) {
                    if (response.success) {
                        $('#category-current-btn').addClass('success-flash');
                        setTimeout(function() {
                            $('#category-current-btn').removeClass('success-flash');
                        }, 600);
                    } else {
                        // Rollback on failure.
                        if (cacheIndex !== -1) {
                            self.cachedReports[cacheIndex].category = oldCategory;
                            self.cachedReports[cacheIndex].category_id = oldCategoryId;
                            self.cachedReports[cacheIndex].category_info = oldCategoryInfo;
                            // Update display back to old values.
                            var oldColor = (oldCategoryInfo && oldCategoryInfo.color) || '#6c757d';
                            var oldName = (oldCategoryInfo && oldCategoryInfo.name) || oldCategory || STRINGS.general;
                            $('#category-dot').css('background', oldColor);
                            $('#category-name').text(oldName);
                        }
                        Swal.fire({
                            icon: 'error',
                            title: STRINGS.error,
                            text: response.message
                        });
                    }
                },
                error: function(xhr) {
                    // Rollback on error.
                    if (cacheIndex !== -1) {
                        self.cachedReports[cacheIndex].category = oldCategory;
                        self.cachedReports[cacheIndex].category_id = oldCategoryId;
                        self.cachedReports[cacheIndex].category_info = oldCategoryInfo;
                        // Update display back to old values.
                        var oldColor = (oldCategoryInfo && oldCategoryInfo.color) || '#6c757d';
                        var oldName = (oldCategoryInfo && oldCategoryInfo.name) || oldCategory || STRINGS.general;
                        $('#category-dot').css('background', oldColor);
                        $('#category-name').text(oldName);
                    }
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || '';
                    Swal.fire({icon: 'error', title: STRINGS.error, text: msg});
                }
            });
        },

        /**
         * Detect numeric columns in data.
         * @param {Array} data - The data array.
         * @param {Array} headers - The headers array.
         * @returns {Array}
         */
        detectNumericColumns: function(data, headers) {
            if (!data || data.length === 0) {
                return [];
            }

            return headers.filter(function(header) {
                var numericCount = 0;
                var sampleSize = Math.min(data.length, 20);

                for (var i = 0; i < sampleSize; i++) {
                    var val = data[i][header];
                    if (val !== null && val !== undefined && val !== '') {
                        var num = parseFloat(val);
                        if (!isNaN(num) && isFinite(num)) {
                            numericCount++;
                        }
                    }
                }

                return numericCount >= sampleSize * 0.5;
            });
        },

        /**
         * Switch between table and chart views.
         * @param {string} view - The view to switch to ('table' or 'chart').
         */
        switchView: function(view) {
            this.currentView = view;
            $('.adeptus-view-toggle-btn').removeClass('active');
            $('.adeptus-view-toggle-btn[data-view="' + view + '"]').addClass('active');

            if (view === 'table') {
                $('#report-table-view').removeClass('d-none');
                $('#report-chart-view').addClass('d-none');
            } else {
                $('#report-table-view').addClass('d-none');
                $('#report-chart-view').removeClass('d-none');
                this.renderChart();
            }
        },

        /**
         * Get the authentication token.
         * @returns {string|null}
         */
        getAuthToken: function() {
            var authData = window.adeptusAuthData;
            if (authData && authData.api_key) {
                return authData.api_key;
            }
            return null;
        },

        /**
         * Format a date for display.
         * @param {string} dateStr - The date string to format.
         * @returns {string}
         */
        formatDate: function(dateStr) {
            if (!dateStr) {
                return 'N/A';
            }
            var date = new Date(dateStr);
            var now = new Date();
            var diff = now - date;
            var days = Math.floor(diff / (1000 * 60 * 60 * 24));

            if (days === 0) {
                return 'Today, ' + date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            } else if (days === 1) {
                return 'Yesterday, ' + date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            } else if (days < 7) {
                var weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                return weekdays[date.getDay()] + ', ' + date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            } else {
                return date.toLocaleDateString() + ', ' + date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            }
        },

        /**
         * Load reports from server.
         */
        loadReports: function() {
            var self = this;
            var token = this.getAuthToken();

            if (!token) {
                if (self.retryCount < self.maxRetries) {
                    self.retryCount++;
                    setTimeout(function() {
                        self.loadReports();
                    }, 500);
                    return;
                } else {
                    $('#reports-loader').addClass('d-none');
                    $('#no-reports-message').removeClass('d-none').html(
                        '<i class="fa fa-exclamation-triangle fa-3x text-warning mb-3"></i>' +
                        '<h5 class="text-muted">Authentication Required</h5>' +
                        '<p class="text-muted">Please ensure you are logged in to view reports.</p>'
                    );
                    return;
                }
            }

            var assistantPromise = new Promise(function(resolve) {
                Ajax.call([{
                    methodname: 'report_adeptus_insights_get_ai_reports',
                    args: {}
                }])[0].done(function(result) {
                    try {
                        var parsed = JSON.parse(result.data || '{}');
                        resolve([parsed]);
                    } catch (e) {
                        resolve([{reports: [], data: []}]);
                    }
                }).fail(function() {
                    resolve([{reports: [], data: []}]);
                });
            });

            var wizardPromise = new Promise(function(resolve) {
                Ajax.call([{
                    methodname: 'report_adeptus_insights_get_wizard_reports',
                    args: {}
                }])[0].done(function(result) {
                    var response = (result && result.data) ? result.data : (result || {});
                    resolve({success: true, reports: response.reports || []});
                }).fail(function() {
                    resolve({success: true, reports: []});
                });
            });

            $.when(assistantPromise, wizardPromise).then(
                function(assistantRes, wizardRes) {
                    var aRes = assistantRes[0] || assistantRes || {};
                    var wRes = wizardRes[0] || wizardRes || {};

                    var assistantReports = aRes.reports || aRes.data || [];
                    var wizardReports = wRes.reports || [];

                    assistantReports = (Array.isArray(assistantReports) ? assistantReports : []).map(function(r) {
                        r.source = r.source || 'assistant';
                        // Parse category_info if it's a JSON string.
                        if (r.category_info && typeof r.category_info === 'string') {
                            try {
                                r.category_info = JSON.parse(r.category_info);
                            } catch (e) {
                                r.category_info = null;
                            }
                        }
                        return r;
                    });
                    wizardReports = (Array.isArray(wizardReports) ? wizardReports : []).map(function(r) {
                        r.source = 'wizard';
                        // Parse category_info if it's a JSON string.
                        if (r.category_info && typeof r.category_info === 'string') {
                            try {
                                r.category_info = JSON.parse(r.category_info);
                            } catch (e) {
                                r.category_info = null;
                            }
                        }
                        return r;
                    });

                    self.cachedReports = assistantReports.concat(wizardReports);

                    self.cachedReports.sort(function(a, b) {
                        return new Date(b.created_at) - new Date(a.created_at);
                    });

                    // Enrich reports with category_info from cached categories.
                    self.enrichReportsWithCategoryInfo();

                    self.renderReportsList(self.cachedReports);
                    self.updateReportCount(self.cachedReports.length);
                },
                function(xhr) {
                    $('#reports-loader').addClass('d-none');
                    if (xhr.status === 401) {
                        $('#no-reports-message').removeClass('d-none').html(
                            '<i class="fa fa-lock fa-3x text-warning mb-3"></i>' +
                            '<h5 class="text-muted">' + STRINGS.sessionExpired + '</h5>' +
                            '<p class="text-muted">' + STRINGS.refreshPageToContinue + '</p>' +
                            '<button class="btn btn-primary btn-sm" onclick="location.reload()">' +
                            '<i class="fa fa-refresh"></i> ' + STRINGS.refreshPage + '</button>'
                        );
                    } else {
                        $('#no-reports-message').removeClass('d-none').html(
                            '<i class="fa fa-exclamation-circle fa-3x text-danger mb-3"></i>' +
                            '<h5 class="text-muted">' + STRINGS.failedToLoadReports + '</h5>' +
                            '<p class="text-muted">' + STRINGS.tryAgainLater + '</p>' +
                            '<button class="btn btn-outline-primary btn-sm" onclick="location.reload()">' +
                            '<i class="fa fa-refresh"></i> ' + STRINGS.retry + '</button>'
                        );
                    }
                }
            );
        },

        /**
         * Update the report count display.
         * @param {number} count - The number of reports.
         */
        updateReportCount: function(count) {
            var countText = count === 1 ? STRINGS.reportCount : count + ' ' + STRINGS.reportsCount;
            $('.adeptus-page-header-subtitle').text(STRINGS.youHaveReportsSaved.replace('{count}',
                countText.toLowerCase()));
        },

        /**
         * Render the reports list.
         * @param {Array} reports - The reports to render.
         */
        renderReportsList: function(reports) {
            var self = this;

            if (this.reportsDataTable) {
                this.reportsDataTable.destroy();
                this.reportsDataTable = null;
            }

            var tbody = $('#reports-tbody');
            tbody.empty();

            $('#reports-loader').addClass('d-none');

            if (!reports || reports.length === 0) {
                $('#no-reports-message').removeClass('d-none');
                $('#reports-table-wrapper').addClass('d-none');
                return;
            }

            $('#no-reports-message').addClass('d-none');
            $('#reports-table-wrapper').removeClass('d-none');

            reports.forEach(function(report) {
                var date = self.formatDate(report.created_at);
                var rowCount = report.row_count || report.data_count || '';
                var rowCountBadge = rowCount ? '<span class="badge bg-info ms-1" title="' + rowCount +
                    ' rows"><i class="fa fa-table"></i> ' + rowCount + '</span>' : '';
                var categoryBadge = report.category ? '<span class="badge bg-secondary ms-1">' + report.category +
                    '</span>' : '';

                var source = (report.source || 'assistant').toLowerCase();
                var sourceBadge = '';
                if (source === 'wizard') {
                    sourceBadge = '<span class="badge adeptus-badge-wizard"><i class="fa fa-magic"></i> ' +
                        STRINGS.wizard + '</span>';
                } else {
                    sourceBadge = '<span class="badge adeptus-badge-ai"><i class="fa fa-robot"></i> ' +
                        STRINGS.ai + '</span>';
                }

                var row = $('<tr class="adeptus-report-row" data-report-slug="' + report.slug +
                    '" data-report-source="' + source + '"></tr>');
                row.html(
                    '<td>' +
                        '<div class="adeptus-report-name-cell">' +
                            '<span class="adeptus-report-name">' +
                            (report.description || report.name || STRINGS.untitledReport) + '</span>' +
                            '<div class="adeptus-report-badges mt-1">' + categoryBadge + rowCountBadge + '</div>' +
                        '</div>' +
                    '</td>' +
                    '<td><small class="text-muted">' + date + '</small></td>' +
                    '<td>' + sourceBadge + '</td>'
                );

                tbody.append(row);
            });

            $('#reports-table-wrapper').off('click', '.adeptus-report-row').on('click', '.adeptus-report-row',
                function() {
                var $row = $(this);
                var slug = $row.data('report-slug');

                $('.adeptus-report-row').removeClass('table-primary');
                $row.addClass('table-primary');
                self.loadReportDetails(slug);
            });

            setTimeout(function() {
                try {
                    self.reportsDataTable = new window.simpleDatatables.DataTable("#reports-table", {
                        searchable: true,
                        fixedHeight: false,
                        perPage: 15,
                        loading: false,
                        labels: {
                            placeholder: STRINGS.searchReports,
                            noRows: STRINGS.noReportsFound,
                            info: STRINGS.showingReports
                        }
                    });

                    setTimeout(function() {
                        $('.datatable-wrapper').removeClass('datatable-loading');
                    }, 100);

                    setTimeout(function() {
                        var firstRow = $('.adeptus-report-row').first();
                        if (firstRow.length && !self.currentReport) {
                            firstRow.addClass('table-primary');
                            var firstSlug = firstRow.data('report-slug');
                            if (firstSlug) {
                                self.loadReportDetails(firstSlug);
                            }
                        }
                    }, 150);
                } catch (e) {
                    window.console.warn('DataTable initialization failed:', e);
                }
            }, 100);
        },

        /**
         * Load report details.
         * @param {string} slug - The report slug.
         */
        loadReportDetails: function(slug) {
            var self = this;
            var token = this.getAuthToken();

            $('#report-placeholder').addClass('d-none');
            $('#report-content').addClass('d-none');
            $('#report-loading').removeClass('d-none');

            var cachedReport = this.cachedReports.find(function(r) {
                return r.slug === slug;
            });
            var source = cachedReport ? (cachedReport.source || 'assistant') : 'assistant';

            if (cachedReport) {
                $('.adeptus-report-view-title').text(cachedReport.description || cachedReport.name ||
                    STRINGS.reportDetails);
                self.showReportCategorySelector(cachedReport);
            }

            if (source === 'wizard' && cachedReport) {
                self.loadWizardReport(cachedReport);
                return;
            }

            $.ajax({
                url: this.backendUrl + '/ai-reports/' + slug,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                timeout: 30000,
                success: function(response) {
                    var report = response.report || response;
                    var data = response.data || [];

                    report.source = source;

                    var sqlQuery = response.sql || (report && report.sql_query) || (report && report.sql);
                    var needsLocalExecution = response.execution_required || (!data || data.length === 0) && sqlQuery;

                    if (needsLocalExecution && sqlQuery) {
                        self.executeReportLocally(sqlQuery, report.params || {})
                            .then(function(executionResult) {
                                var localData = executionResult.data || [];

                                self.currentReport = report;
                                self.currentReportData = localData;

                                var cacheIndex = self.cachedReports.findIndex(function(r) {
                                    return r.slug === slug;
                                });
                                if (cacheIndex !== -1) {
                                    self.cachedReports[cacheIndex] = Object.assign({}, report,
                                        {data: localData, source: source});
                                }

                                self.renderReportContent(report, localData);
                            })
                            .catch(function(error) {
                                $('#report-loading').addClass('d-none');
                                $('#report-content').html(
                                    '<div class="alert alert-danger">' +
                                        '<i class="fa fa-exclamation-circle"></i> ' + STRINGS.failedToExecuteReport +
                                        ': ' + error.message +
                                        '<button class="btn btn-sm btn-outline-danger ms-3" ' +
                                        'onclick="GeneratedReports.loadReportDetails(\'' + slug + '\')">' +
                                        '<i class="fa fa-refresh"></i> ' + STRINGS.retry + '</button>' +
                                    '</div>'
                                ).removeClass('d-none');
                            });
                        return;
                    }

                    self.currentReport = report;
                    self.currentReportData = data;

                    var cacheIndex = self.cachedReports.findIndex(function(r) {
                        return r.slug === slug;
                    });
                    if (cacheIndex !== -1) {
                        self.cachedReports[cacheIndex] = Object.assign({}, report, {data: data, source: source});
                    }

                    self.renderReportContent(report, data);
                },
                error: function() {
                    $('#report-loading').addClass('d-none');
                    $('#report-content').html(
                        '<div class="alert alert-danger">' +
                            '<i class="fa fa-exclamation-circle"></i> ' + STRINGS.failedToLoadReportDetails +
                            '<button class="btn btn-sm btn-outline-danger ms-3" ' +
                            'onclick="GeneratedReports.loadReportDetails(\'' + slug + '\')">' +
                            '<i class="fa fa-refresh"></i> ' + STRINGS.retry + '</button>' +
                        '</div>'
                    ).removeClass('d-none');
                }
            });
        },

        /**
         * Load and execute a wizard report.
         * @param {Object} cachedReport - The cached report object.
         */
        loadWizardReport: function(cachedReport) {
            var self = this;

            var parameters = cachedReport.parameters || {};
            var reportTemplateId = cachedReport.report_template_id || cachedReport.name;

            $.ajax({
                url: M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey +
                    '&info=report_adeptus_insights_generate_report',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify([{
                    index: 0,
                    methodname: 'report_adeptus_insights_generate_report',
                    args: {
                        reportid: reportTemplateId,
                        parameters: JSON.stringify(parameters),
                        reexecution: true
                    }
                }]),
                timeout: 60000,
                success: function(results) {
                    var result = Array.isArray(results) && results.length > 0 ? results[0] : null;

                    if (result && result.error) {
                        $('#report-loading').addClass('d-none');
                        $('#report-content').html(
                            '<div class="alert alert-danger">' +
                                '<i class="fa fa-exclamation-circle"></i> ' +
                                ((result.exception && result.exception.message) || result.error) +
                                '<button class="btn btn-sm btn-outline-danger ms-3" ' +
                                'onclick="GeneratedReports.loadReportDetails(\'' + cachedReport.slug + '\')">' +
                                '<i class="fa fa-refresh"></i> ' + STRINGS.retry + '</button>' +
                            '</div>'
                        ).removeClass('d-none');
                        return;
                    }

                    var response = (result && result.data) ? result.data : (result || {});
                    if (response.success) {
                        var rawResults = response.results || response.data || [];
                        var data = rawResults.map(function(row) {
                            if (row.cells && Array.isArray(row.cells)) {
                                var flatRow = {};
                                row.cells.forEach(function(cell) {
                                    flatRow[cell.key] = cell.value;
                                });
                                return flatRow;
                            }
                            return row;
                        });
                        var report = {
                            name: cachedReport.name,
                            description: cachedReport.description || cachedReport.name,
                            category: (response.report && response.report.category) || cachedReport.category || 'Wizard Report',
                            created_at: cachedReport.created_at,
                            source: 'wizard',
                            slug: cachedReport.slug
                        };

                        self.currentReport = report;
                        self.currentReportData = data;

                        var cacheIndex = self.cachedReports.findIndex(function(r) {
                            return r.slug === cachedReport.slug;
                        });
                        if (cacheIndex !== -1) {
                            self.cachedReports[cacheIndex].data = data;
                            self.cachedReports[cacheIndex].row_count = data.length;
                        }

                        self.renderReportContent(report, data);
                    } else {
                        $('#report-loading').addClass('d-none');
                        $('#report-content').html(
                            '<div class="alert alert-warning">' +
                                '<i class="fa fa-exclamation-triangle"></i> ' +
                                (response.message || STRINGS.failedToGenerateReport) +
                                '<button class="btn btn-sm btn-outline-warning ms-3" ' +
                                'onclick="GeneratedReports.loadReportDetails(\'' + cachedReport.slug + '\')">' +
                                '<i class="fa fa-refresh"></i> ' + STRINGS.retry + '</button>' +
                            '</div>'
                        ).removeClass('d-none');
                    }
                },
                error: function() {
                    $('#report-loading').addClass('d-none');
                    $('#report-content').html(
                        '<div class="alert alert-danger">' +
                            '<i class="fa fa-exclamation-circle"></i> ' + STRINGS.failedToExecuteWizardReport +
                            '<button class="btn btn-sm btn-outline-danger ms-3" ' +
                            'onclick="GeneratedReports.loadReportDetails(\'' + cachedReport.slug + '\')">' +
                            '<i class="fa fa-refresh"></i> ' + STRINGS.retry + '</button>' +
                        '</div>'
                    ).removeClass('d-none');
                }
            });
        },

        /**
         * Execute SQL locally for SaaS model.
         * @param {string} sql - The SQL query to execute.
         * @param {Object} params - Query parameters.
         * @returns {Promise}
         */
        executeReportLocally: function(sql, params) {
            params = params || {};

            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/execute_ai_report.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        sql: sql,
                        params: params,
                        sesskey: M.cfg.sesskey
                    }),
                    timeout: 60000,
                    success: function(response) {
                        if (response.success) {
                            // Parse JSON strings if returned as strings.
                            var data = response.data;
                            var headers = response.headers;
                            if (typeof data === 'string') {
                                try { data = JSON.parse(data); } catch (e) { data = []; }
                            }
                            if (typeof headers === 'string') {
                                try { headers = JSON.parse(headers); } catch (e) { headers = []; }
                            }
                            resolve({
                                data: data || [],
                                headers: headers || [],
                                row_count: response.row_count || 0,
                                error: null
                            });
                        } else {
                            resolve({
                                data: [],
                                headers: [],
                                row_count: 0,
                                error: response.message || 'Query execution failed'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = 'Failed to execute report';
                        try {
                            var response = JSON.parse(xhr.responseText);
                            errorMessage = response.message || errorMessage;
                        } catch (e) {
                            errorMessage = error || errorMessage;
                        }
                        reject(new Error(errorMessage));
                    }
                });
            });
        },

        /**
         * Render report content.
         * @param {Object} report - The report object.
         * @param {Array} data - The report data.
         */
        renderReportContent: function(report, data) {
            var self = this;
            $('#report-loading').addClass('d-none');

            var contentHtml = '';
            var rowCount = data ? data.length : 0;

            contentHtml += '<div class="adeptus-report-detail-header mb-4">';
            contentHtml += '<h4 class="mb-2">' + (report.description || report.name || STRINGS.report) + '</h4>';
            contentHtml += '<div class="adeptus-report-meta d-flex flex-wrap align-items-center gap-2">';

            var reportSource = (report.source || 'assistant').toLowerCase();
            if (reportSource === 'wizard') {
                contentHtml += '<span class="badge adeptus-badge-wizard"><i class="fa fa-magic"></i> ' +
                    STRINGS.wizard + '</span>';
            } else {
                contentHtml += '<span class="badge adeptus-badge-ai"><i class="fa fa-robot"></i> ' +
                    STRINGS.ai + '</span>';
            }

            contentHtml += '<span class="badge adeptus-badge-rowcount"><i class="fa fa-table"></i> ' + rowCount +
                ' ' + STRINGS.rows + '</span>';
            contentHtml += '</div>';
            contentHtml += '</div>';

            contentHtml += '<div class="adeptus-action-bar d-flex justify-content-between align-items-center mb-3">';

            contentHtml += '<div class="adeptus-view-toggle btn-group" role="group">';
            contentHtml += '<button type="button" class="btn btn-sm adeptus-view-toggle-btn active" ' +
                'data-view="table"><i class="fa fa-table"></i> ' + STRINGS.table + '</button>';
            contentHtml += '<button type="button" class="btn btn-sm adeptus-view-toggle-btn" data-view="chart">' +
                '<i class="fa fa-bar-chart"></i> ' + STRINGS.chart + '</button>';
            contentHtml += '</div>';

            var premiumClass = self.isFreePlan ? ' adeptus-export-premium' : '';
            contentHtml += '<div class="adeptus-export-buttons d-flex gap-2">';
            contentHtml += '<button class="btn btn-outline-danger btn-sm adeptus-export-pdf-btn">' +
                '<i class="fa fa-file-pdf-o"></i> ' + STRINGS.exportPdf + '</button>';
            contentHtml += '<button class="btn btn-outline-primary btn-sm adeptus-export-csv-btn' + premiumClass +
                '"><i class="fa fa-file-excel-o"></i> ' + STRINGS.exportCsv +
                (self.isFreePlan ? ' <i class="fa fa-crown text-warning" style="font-size: 10px;"></i>' : '') +
                '</button>';
            contentHtml += '<button class="btn btn-outline-secondary btn-sm adeptus-export-json-btn' + premiumClass +
                '"><i class="fa fa-code"></i> ' + STRINGS.exportJson +
                (self.isFreePlan ? ' <i class="fa fa-crown text-warning" style="font-size: 10px;"></i>' : '') +
                '</button>';
            contentHtml += '</div>';
            contentHtml += '</div>';

            if (data && data.length > 0) {
                var headers = Object.keys(data[0]);
                var displayLimit = 100;

                contentHtml += '<div id="report-table-view" class="adeptus-report-view">';
                contentHtml += '<div class="adeptus-report-data-wrapper">';
                contentHtml += '<div class="table-responsive report-table-container">';
                contentHtml += '<table class="table table-striped table-hover table-sm report-data-table">';
                contentHtml += '<thead class="table-light sticky-header"><tr>';
                headers.forEach(function(header) {
                    var formattedHeader = header.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
                        return l.toUpperCase();
                    });
                    contentHtml += '<th>' + formattedHeader + '</th>';
                });
                contentHtml += '</tr></thead>';
                contentHtml += '<tbody>';

                var displayData = data.slice(0, displayLimit);
                displayData.forEach(function(row) {
                    contentHtml += '<tr>';
                    headers.forEach(function(header) {
                        var value = row[header];
                        if (value === null || value === undefined) {
                            value = '';
                        }
                        var displayValue = String(value);
                        if (displayValue.length > 100) {
                            displayValue = displayValue.substring(0, 100) + '...';
                        }
                        contentHtml += '<td title="' + String(value).replace(/"/g, '&quot;') + '">' +
                            displayValue + '</td>';
                    });
                    contentHtml += '</tr>';
                });

                contentHtml += '</tbody></table>';
                contentHtml += '</div>';
                contentHtml += '</div>';

                if (data.length > displayLimit) {
                    contentHtml += '<div class="alert alert-info mt-3 d-flex align-items-center ' +
                        'justify-content-between">';
                    contentHtml += '<span><i class="fa fa-info-circle"></i> ' +
                        STRINGS.showingFirstRows.replace('{first}', displayLimit).replace('{total}', data.length) +
                        '</span>';
                    contentHtml += '<span class="text-primary">' + STRINGS.exportToSeeAll + '</span>';
                    contentHtml += '</div>';
                }
                contentHtml += '</div>';

                contentHtml += '<div id="report-chart-view" class="adeptus-report-view d-none">';
                contentHtml += '<div class="adeptus-chart-controls-wrapper mb-3">';
                contentHtml += '<div class="adeptus-chart-controls d-flex flex-wrap align-items-center gap-3">';

                contentHtml += '<div class="adeptus-control-group">';
                contentHtml += '<label for="chart-type-select" class="form-label">' + STRINGS.chartType + '</label>';
                contentHtml += '<select id="chart-type-select" class="form-select form-select-sm">';
                contentHtml += '<option value="bar">' + STRINGS.barChart + '</option>';
                contentHtml += '<option value="line">' + STRINGS.lineChart + '</option>';
                contentHtml += '<option value="pie">' + STRINGS.pieChart + '</option>';
                contentHtml += '<option value="doughnut">' + STRINGS.doughnutChart + '</option>';
                contentHtml += '</select>';
                contentHtml += '</div>';

                contentHtml += '<div class="adeptus-control-group">';
                contentHtml += '<label for="chart-x-axis" class="form-label">' + STRINGS.xAxisLabels + '</label>';
                contentHtml += '<select id="chart-x-axis" class="form-select form-select-sm">';
                headers.forEach(function(header, idx) {
                    var formattedHeader = header.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
                        return l.toUpperCase();
                    });
                    var selected = idx === 0 ? ' selected' : '';
                    contentHtml += '<option value="' + header + '"' + selected + '>' + formattedHeader + '</option>';
                });
                contentHtml += '</select>';
                contentHtml += '</div>';

                var numericCols = self.detectNumericColumns(data, headers);
                contentHtml += '<div class="adeptus-control-group">';
                contentHtml += '<label for="chart-y-axis" class="form-label">' + STRINGS.yAxisValues + '</label>';
                contentHtml += '<select id="chart-y-axis" class="form-select form-select-sm">';
                if (numericCols.length > 0) {
                    numericCols.forEach(function(col, idx) {
                        var formattedHeader = col.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
                            return l.toUpperCase();
                        });
                        var selected = idx === numericCols.length - 1 ? ' selected' : '';
                        contentHtml += '<option value="' + col + '"' + selected + '>' + formattedHeader + '</option>';
                    });
                } else {
                    headers.forEach(function(header, idx) {
                        var formattedHeader = header.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
                            return l.toUpperCase();
                        });
                        var selected = idx === headers.length - 1 ? ' selected' : '';
                        contentHtml += '<option value="' + header + '"' + selected + '>' + formattedHeader + '</option>';
                    });
                }
                contentHtml += '</select>';
                contentHtml += '</div>';

                contentHtml += '</div>';
                contentHtml += '</div>';

                contentHtml += '<div class="adeptus-chart-container" style="position: relative; height: 400px;">';
                contentHtml += '<canvas id="report-chart"></canvas>';
                contentHtml += '</div>';
                contentHtml += '</div>';

            } else {
                contentHtml += '<div class="alert alert-warning text-center py-4">';
                contentHtml += '<i class="fa fa-inbox fa-2x mb-2 d-block"></i>';
                contentHtml += '<strong>' + STRINGS.noDataAvailable + '</strong><br>';
                contentHtml += '<small class="text-muted">' + STRINGS.noDataRows + '</small>';
                contentHtml += '</div>';
            }

            $('#report-content').html(contentHtml).removeClass('d-none');

            self.currentView = 'table';

            $('#report-content .adeptus-export-pdf-btn').on('click', function() {
                self.exportReport(report.slug, 'pdf');
            });
            $('#report-content .adeptus-export-csv-btn').on('click', function() {
                if ($(this).hasClass('adeptus-export-premium')) {
                    self.showExportUpgradePrompt('csv');
                    return;
                }
                self.exportReport(report.slug, 'csv');
            });
            $('#report-content .adeptus-export-json-btn').on('click', function() {
                if ($(this).hasClass('adeptus-export-premium')) {
                    self.showExportUpgradePrompt('json');
                    return;
                }
                self.exportReport(report.slug, 'json');
            });

            $('#delete-report-header-btn').off('click').on('click', function() {
                self.confirmDeleteReport(report);
            });
        },

        /**
         * Render chart.
         */
        renderChart: function() {
            var data = this.currentReportData;

            if (!data || data.length === 0) {
                return;
            }

            var chartType = $('#chart-type-select').val() || 'bar';
            var canvas = document.getElementById('report-chart');
            if (!canvas) {
                return;
            }

            if (this.chartInstance) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }

            var labelKey = $('#chart-x-axis').val();
            var valueKey = $('#chart-y-axis').val();

            if (!labelKey || !valueKey) {
                var headers = Object.keys(data[0]);
                labelKey = labelKey || headers[0];
                valueKey = valueKey || headers[headers.length - 1];
            }

            var valueKeyFormatted = valueKey.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
                return l.toUpperCase();
            });

            var chartData = data.slice(0, 50);
            var labels = chartData.map(function(r) {
                var label = r[labelKey];
                if (label === null || label === undefined) {
                    return STRINGS.unknown;
                }
                var labelStr = String(label);
                return labelStr.length > 30 ? labelStr.substring(0, 30) + '...' : labelStr;
            });
            var values = chartData.map(function(r) {
                return parseFloat(r[valueKey]) || 0;
            });

            var colors = this.generateChartColors(values.length);
            var chartConfig = this.createChartConfig(chartType, labels, values, valueKeyFormatted, colors);

            try {
                this.chartInstance = new this.Chart(canvas.getContext('2d'), chartConfig);
            } catch (error) {
                window.console.error('Error creating chart:', error);
                $('#report-chart-view .adeptus-chart-container').html(
                    '<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ' +
                    STRINGS.errorRenderingChart + ': ' + error.message + '</div>'
                );
            }
        },

        /**
         * Generate chart colors.
         * @param {number} count - Number of colors needed.
         * @returns {Array}
         */
        generateChartColors: function(count) {
            var baseColors = [
                '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1',
                '#14b8a6', '#a855f7', '#eab308', '#22c55e', '#3b82f6'
            ];

            var colors = [];
            for (var i = 0; i < count; i++) {
                colors.push(baseColors[i % baseColors.length]);
            }
            return colors;
        },

        /**
         * Create chart configuration.
         * @param {string} chartType - The chart type.
         * @param {Array} labels - The labels array.
         * @param {Array} values - The values array.
         * @param {string} valueKey - The value key label.
         * @param {Array} colors - The colors array.
         * @returns {Object}
         */
        createChartConfig: function(chartType, labels, values, valueKey, colors) {
            var reportName = (this.currentReport && this.currentReport.description) ||
                (this.currentReport && this.currentReport.name) ||
                STRINGS.report;

            var baseConfig = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: reportName,
                        font: {size: 16, weight: 'bold'},
                        padding: {top: 10, bottom: 20}
                    },
                    legend: {
                        display: chartType === 'pie' || chartType === 'doughnut',
                        position: 'right'
                    }
                }
            };

            if (chartType === 'pie' || chartType === 'doughnut') {
                return {
                    type: chartType,
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: baseConfig
                };
            } else if (chartType === 'line') {
                return {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: valueKey,
                            data: values,
                            borderColor: colors[0],
                            backgroundColor: colors[0] + '40',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: Object.assign({}, baseConfig, {
                        scales: {
                            y: {beginAtZero: true},
                            x: {ticks: {maxRotation: 45, minRotation: 45}}
                        }
                    })
                };
            } else {
                return {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: valueKey,
                            data: values,
                            backgroundColor: colors,
                            borderColor: colors.map(function(c) {
                                return c;
                            }),
                            borderWidth: 1
                        }]
                    },
                    options: Object.assign({}, baseConfig, {
                        scales: {
                            y: {beginAtZero: true},
                            x: {ticks: {maxRotation: 45, minRotation: 45}}
                        }
                    })
                };
            }
        },

        /**
         * Export report.
         * @param {string} reportSlug - The report slug.
         * @param {string} format - The export format.
         */
        exportReport: function(reportSlug, format) {
            var self = this;

            if (!self.currentReportData || !Array.isArray(self.currentReportData) ||
                self.currentReportData.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: STRINGS.noData,
                    text: STRINGS.noDataToExport
                });
                return;
            }

            Swal.fire({
                title: STRINGS.exportingAs.replace('{format}', format.toUpperCase()),
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });

            self.checkExportEligibility(format).then(function(eligibility) {
                if (!eligibility.success || !eligibility.eligible) {
                    Swal.close();
                    if (self.isFreePlan && format !== 'pdf') {
                        self.showExportUpgradePrompt(format);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: STRINGS.exportNotAvailable,
                            text: eligibility.message || STRINGS.exportNotEligible
                        });
                    }
                    return;
                }

                var reportId = reportSlug;
                var isWizardReport = self.currentReport && self.currentReport.source === 'wizard';
                if (isWizardReport && self.currentReport.name) {
                    reportId = self.currentReport.name;
                }

                var body = 'reportid=' + encodeURIComponent(reportId) + '&format=' + format +
                    '&sesskey=' + M.cfg.sesskey;
                body += '&view=' + self.currentView;

                var headers = self.currentReportData.length > 0 ? Object.keys(self.currentReportData[0]) : [];
                var payloadReportName = (self.currentReport && self.currentReport.description) ||
                    (self.currentReport && self.currentReport.name) || 'report';
                var reportDataPayload = {
                    results: self.currentReportData,
                    headers: headers,
                    report_name: payloadReportName,
                    report_category: (self.currentReport && self.currentReport.category) || ''
                };
                body += '&report_data=' + encodeURIComponent(JSON.stringify(reportDataPayload));

                var chartImagePromise = Promise.resolve(null);
                if (format === 'pdf' && self.currentView === 'chart') {
                    chartImagePromise = self.captureChartImage().catch(function() {
                        return null;
                    });
                }

                chartImagePromise.then(function(chartImage) {
                    if (chartImage && chartImage.length > 100) {
                        body += '&chart_image=' + encodeURIComponent(chartImage);
                        window.console.log('[GeneratedReports] Chart image included in export');
                    }

                    return fetch(M.cfg.wwwroot + '/report/adeptus_insights/ajax/export_report.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: body,
                        credentials: 'same-origin'
                    });
                }).then(function(response) {
                    var contentType = response.headers.get('content-type');
                    var contentDisposition = response.headers.get('content-disposition');

                    if (contentType && contentType.includes('application/json') && !contentDisposition) {
                        return response.json().then(function(errorData) {
                            throw new Error(errorData.message || 'Export failed');
                        });
                    }

                    if (!response.ok) {
                        throw new Error('Export failed with status: ' + response.status);
                    }

                    return response.blob();
                }).then(function(blob) {
                    var reportName = (self.currentReport && self.currentReport.description) ||
                        (self.currentReport && self.currentReport.name) || 'report';
                    var sanitizedName = reportName.replace(/[^a-zA-Z0-9\s-]/g, '')
                        .replace(/\s+/g, '_').toLowerCase();
                    var dateSuffix = new Date().toISOString().split('T')[0];

                    var url = window.URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = sanitizedName + '_' + dateSuffix + '.' + format;
                    document.body.appendChild(link);
                    link.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(link);

                    Swal.close();

                    self.trackExport(format, reportName);
                    self.showToast(format.toUpperCase() + ' ' + STRINGS.fileDownloadedSuccess, 'success');

                }).catch(function(error) {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: STRINGS.exportFailed,
                        text: error.message || STRINGS.exportFailedMessage
                    });
                });
            });
        }
    };

    // Make available globally for retry buttons
    window.GeneratedReports = GeneratedReports;

    return {
        /**
         * Initialize the module.
         * @param {Object} config - Configuration from PHP.
         */
        init: function(config) {
            // Load strings first, then initialize
            loadStrings().then(function() {
                // Wait for auth data to be available
                var checkAuth = setInterval(function() {
                    if (window.adeptusAuthData) {
                        clearInterval(checkAuth);
                        GeneratedReports.init(config);
                    }
                }, 100);

                // Timeout after 5 seconds
                setTimeout(function() {
                    clearInterval(checkAuth);
                    if (!window.adeptusAuthData) {
                        $('#reports-loader').addClass('d-none');
                        $('#no-reports-message').removeClass('d-none').html(
                            '<i class="fa fa-exclamation-triangle fa-3x text-warning mb-3"></i>' +
                            '<h5 class="text-muted">' + STRINGS.authenticationRequired + '</h5>' +
                            '<p class="text-muted">' + STRINGS.authenticationRequiredMessage + '</p>'
                        );
                    }
                }, 5000);
            });
        }
    };
});

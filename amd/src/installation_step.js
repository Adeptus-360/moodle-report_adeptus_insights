/**
 * Subscription Installation Step JavaScript Module
 *
 * Handles billing toggle, plan selection, and Stripe billing portal integration
 * during the plugin installation wizard.
 *
 * @module     report_adeptus_insights/installation_step
 * @package    report_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {
    'use strict';

    /**
     * Localized strings loaded via core/str API.
     * @type {Object}
     */
    var STRINGS = {};

    /**
     * Plans data passed from PHP.
     * @type {Object}
     */
    var plansData = {};

    /**
     * Current billing interval selection.
     * @type {string}
     */
    var currentInterval = 'monthly';

    /**
     * Load all required localized strings.
     * @returns {Promise} Promise resolved when strings are loaded
     */
    var loadStrings = function() {
        return Str.get_strings([
            {key: 'annual_plans_coming_soon', component: 'report_adeptus_insights'},
            {key: 'current_plan', component: 'report_adeptus_insights'},
            {key: 'most_popular', component: 'report_adeptus_insights'},
            {key: 'free', component: 'report_adeptus_insights'},
            {key: 'forever', component: 'report_adeptus_insights'},
            {key: 'per_year', component: 'report_adeptus_insights'},
            {key: 'per_month', component: 'report_adeptus_insights'},
            {key: 'save', component: 'report_adeptus_insights'},
            {key: 'select_free_plan', component: 'report_adeptus_insights'},
            {key: 'upgrade_to', component: 'report_adeptus_insights'},
            {key: 'ai_tokens', component: 'report_adeptus_insights'},
            {key: 'exports_mo', component: 'report_adeptus_insights'},
            {key: 'saved_reports', component: 'report_adeptus_insights'},
            {key: 'formats', component: 'report_adeptus_insights'},
            {key: 'redirecting_to_billing', component: 'report_adeptus_insights'},
            {key: 'failed_billing_session', component: 'report_adeptus_insights'},
            {key: 'connection_error', component: 'report_adeptus_insights'}
        ]).then(function(results) {
            STRINGS = {
                annualPlansSoon: results[0],
                currentPlan: results[1],
                mostPopular: results[2],
                free: results[3],
                forever: results[4],
                perYear: results[5],
                perMonth: results[6],
                save: results[7],
                selectFreePlan: results[8],
                upgradeTo: results[9],
                aiTokens: results[10],
                exportsMo: results[11],
                savedReports: results[12],
                formats: results[13],
                redirectingToBilling: results[14],
                failedBillingSession: results[15],
                connectionError: results[16]
            };
            return STRINGS;
        }).catch(function() {
            // Fallback strings if loading fails
            STRINGS = {
                annualPlansSoon: 'Annual plans coming soon',
                currentPlan: 'Current Plan',
                mostPopular: 'Most Popular',
                free: 'Free',
                forever: 'Forever',
                perYear: 'per year',
                perMonth: 'per month',
                save: 'Save',
                selectFreePlan: 'Select Free Plan',
                upgradeTo: 'Upgrade to',
                aiTokens: 'AI Tokens',
                exportsMo: 'Exports/mo',
                savedReports: 'Saved Reports',
                formats: 'Formats',
                redirectingToBilling: 'Redirecting to billing portal...',
                failedBillingSession: 'Failed to create billing session',
                connectionError: 'Connection error. Please try again.'
            };
            return STRINGS;
        });
    };

    /**
     * Update the plans display based on billing interval.
     * @param {string} interval - 'monthly' or 'yearly'
     */
    var updatePlansDisplay = function(interval) {
        var plans = plansData[interval] || plansData.monthly;
        var container = $('#plans-container');

        if (!plans || plans.length === 0) {
            // No plans for this interval, show message
            container.html('<div class="text-center p-4"><p class="text-muted">' +
                STRINGS.annualPlansSoon + '</p></div>');
            return;
        }

        // Rebuild cards with new interval data
        var html = '';
        plans.forEach(function(plan) {
            html += buildPlanCard(plan, interval);
        });
        container.html(html);
    };

    /**
     * Build HTML for a single plan card.
     * @param {Object} plan - Plan data object
     * @param {string} interval - Billing interval
     * @returns {string} HTML string for the plan card
     */
    var buildPlanCard = function(plan, interval) {
        var badgeHtml = '';
        if (plan.is_current) {
            badgeHtml = '<div class="adeptus-plan-badge current">' + STRINGS.currentPlan + '</div>';
        } else if (plan.is_popular) {
            badgeHtml = '<div class="adeptus-plan-badge popular">' + STRINGS.mostPopular + '</div>';
        }

        var priceHtml = '';
        if (plan.is_free) {
            priceHtml = '<div class="adeptus-price-free">' + STRINGS.free + '</div>' +
                '<div class="adeptus-price-period">' + STRINGS.forever + '</div>';
        } else {
            var periodText = interval === 'yearly' ? STRINGS.perYear : STRINGS.perMonth;
            priceHtml = '<div class="adeptus-price-amount">' + plan.price_formatted + '</div>' +
                '<div class="adeptus-price-period">' + periodText + '</div>';
            if (plan.has_savings) {
                priceHtml += '<div class="adeptus-savings-badge">' + STRINGS.save + ' ' +
                    plan.savings_percent + '%</div>';
            }
        }

        var featuresHtml = '';
        if (plan.features && plan.features.length > 0) {
            plan.features.forEach(function(feature) {
                featuresHtml += '<li><i class="fa fa-check"></i> ' + feature + '</li>';
            });
        }

        var actionHtml = '';
        if (plan.is_current) {
            actionHtml = '<button class="adeptus-plan-btn adeptus-plan-btn-current" disabled>' +
                '<i class="fa fa-check-circle"></i> ' + STRINGS.currentPlan + '</button>';
        } else if (plan.is_free) {
            actionHtml = '<button class="adeptus-plan-btn adeptus-plan-btn-outline select-adeptus-plan-btn" ' +
                'data-plan-id="' + plan.id + '" data-adeptus-plan-name="' + plan.name + '">' +
                STRINGS.selectFreePlan + '</button>';
        } else {
            actionHtml = '<button class="adeptus-plan-btn adeptus-plan-btn-primary select-adeptus-plan-btn" ' +
                'data-plan-id="' + plan.id + '" data-adeptus-plan-name="' + plan.name + '" ' +
                'data-stripe-product="' + (plan.stripe_product_id || '') + '">' +
                '<i class="fa fa-arrow-up"></i> ' + STRINGS.upgradeTo + ' ' + plan.short_name + '</button>';
        }

        var cardClasses = 'adeptus-plan-card';
        if (plan.is_popular) {
            cardClasses += ' popular';
        }
        if (plan.is_current) {
            cardClasses += ' current';
        }

        return '<div class="' + cardClasses + '" data-tier="' + plan.tier + '" data-interval="' + interval + '">' +
            badgeHtml +
            '<div class="adeptus-plan-header">' +
                '<div class="adeptus-plan-name">' + plan.short_name + '</div>' +
                '<div class="adeptus-plan-description">' + plan.description + '</div>' +
            '</div>' +
            '<div class="adeptus-plan-price">' + priceHtml + '</div>' +
            '<div class="adeptus-plan-limits">' +
                '<div class="adeptus-limit-item">' +
                    '<div class="adeptus-limit-value">' + plan.tokens_limit + '</div>' +
                    '<div class="adeptus-limit-label">' + STRINGS.aiTokens + '</div>' +
                '</div>' +
                '<div class="adeptus-limit-item">' +
                    '<div class="adeptus-limit-value">' + plan.exports_limit + '</div>' +
                    '<div class="adeptus-limit-label">' + STRINGS.exportsMo + '</div>' +
                '</div>' +
                '<div class="adeptus-limit-item">' +
                    '<div class="adeptus-limit-value">' + plan.reports_limit + '</div>' +
                    '<div class="adeptus-limit-label">' + STRINGS.savedReports + '</div>' +
                '</div>' +
                '<div class="adeptus-limit-item">' +
                    '<div class="adeptus-limit-value">' + plan.export_formats + '</div>' +
                    '<div class="adeptus-limit-label">' + STRINGS.formats + '</div>' +
                '</div>' +
            '</div>' +
            '<div class="adeptus-plan-features"><ul>' + featuresHtml + '</ul></div>' +
            '<div class="adeptus-plan-action">' + actionHtml + '</div>' +
        '</div>';
    };

    /**
     * Create a Stripe billing portal session and redirect.
     * @param {string} stripeProductId - Stripe product ID
     * @param {string} planName - Plan name for display
     */
    var createBillingPortalSession = function(stripeProductId, planName) {
        var returnUrl = window.location.href;

        Ajax.call([{
            methodname: 'report_adeptus_insights_create_billing_portal_session',
            args: {
                return_url: returnUrl,
                sesskey: M.cfg.sesskey
            },
            done: function(response) {
                if (response && response.success && response.portal_url) {
                    Notification.addNotification({
                        message: STRINGS.redirectingToBilling,
                        type: 'success'
                    });
                    setTimeout(function() {
                        window.location.href = response.portal_url;
                    }, 1000);
                } else {
                    Notification.addNotification({
                        message: (response && response.message) ? response.message : STRINGS.failedBillingSession,
                        type: 'error'
                    });
                }
            },
            fail: function(error) {
                window.console.error('Billing portal error:', error);
                Notification.addNotification({
                    message: STRINGS.connectionError,
                    type: 'error'
                });
            }
        }]);
    };

    /**
     * Initialize event handlers.
     */
    var initEventHandlers = function() {
        // Billing toggle handler
        $('.adeptus-billing-toggle-btn').on('click', function() {
            var interval = $(this).data('interval');
            if (interval === currentInterval) {
                return;
            }

            // Update toggle UI
            $('.adeptus-billing-toggle-btn').removeClass('active');
            $(this).addClass('active');
            currentInterval = interval;

            // Update plans display
            updatePlansDisplay(interval);
        });

        // Plan selection handler
        $(document).on('click', '.select-adeptus-plan-btn', function() {
            var planId = $(this).data('plan-id');
            var planName = $(this).data('adeptus-plan-name');
            var stripeProduct = $(this).data('stripe-product');

            if (stripeProduct) {
                // Paid plan - redirect to billing portal
                createBillingPortalSession(stripeProduct, planName);
            } else {
                // Free plan - complete installation
                $('#complete-installation-form').submit();
            }
        });
    };

    return {
        /**
         * Initialize the installation step module.
         * @param {Object} config - Configuration object from PHP
         * @param {Object} config.plansData - Plans data organized by interval
         */
        init: function(config) {
            plansData = config.plansData || {};
            currentInterval = 'monthly';

            loadStrings().then(function() {
                initEventHandlers();
            });
        }
    };
});

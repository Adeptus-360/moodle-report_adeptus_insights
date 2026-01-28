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
 * Subscription management for Adeptus Insights plugin.
 *
 * Handles subscription plan selection, upgrades, downgrades, cancellations,
 * Stripe checkout integration, and billing portal access.
 *
 * @module     report_adeptus_insights/subscription
 * @package    report_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// jshint ignore:start
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {
    'use strict';

    var Swal = window.Swal;

    // Language strings storage
    var strings = {};

    /**
     * Load all language strings needed for the module
     * @returns {Promise} Promise that resolves when strings are loaded
     */
    var loadStrings = function() {
        return Str.get_strings([
            {key: 'js_verifying_payment', component: 'report_adeptus_insights'},
            {key: 'js_verifying_payment_text', component: 'report_adeptus_insights'},
            {key: 'js_upgrade_successful', component: 'report_adeptus_insights'},
            {key: 'js_verification_issue', component: 'report_adeptus_insights'},
            {key: 'js_verification_issue_text', component: 'report_adeptus_insights'},
            {key: 'js_processing_payment', component: 'report_adeptus_insights'},
            {key: 'js_processing_payment_text', component: 'report_adeptus_insights'},
            {key: 'js_checkout_cancelled', component: 'report_adeptus_insights'},
            {key: 'js_checkout_cancelled_text', component: 'report_adeptus_insights'},
            {key: 'js_cancel_subscription_title', component: 'report_adeptus_insights'},
            {key: 'js_cancel_subscription_text', component: 'report_adeptus_insights'},
            {key: 'js_yes_cancel', component: 'report_adeptus_insights'},
            {key: 'js_upgrade_to', component: 'report_adeptus_insights'},
            {key: 'js_downgrade_to', component: 'report_adeptus_insights'},
            {key: 'js_choose_your_plan', component: 'report_adeptus_insights'},
            {key: 'js_redirecting', component: 'report_adeptus_insights'},
            {key: 'js_popup_blocked', component: 'report_adeptus_insights'},
            {key: 'js_popup_blocked_text', component: 'report_adeptus_insights'},
            {key: 'js_portal_creation_failed', component: 'report_adeptus_insights'},
            {key: 'js_connection_error', component: 'report_adeptus_insights'},
            {key: 'js_preparing_checkout', component: 'report_adeptus_insights'},
            {key: 'js_redirecting_checkout', component: 'report_adeptus_insights'},
            {key: 'js_upgrade_coming_soon', component: 'report_adeptus_insights'},
            {key: 'js_billing_portal_error', component: 'report_adeptus_insights'},
            {key: 'js_opening_billing_portal', component: 'report_adeptus_insights'},
            {key: 'js_loading_plans', component: 'report_adeptus_insights'},
            {key: 'js_continue', component: 'report_adeptus_insights'},
            {key: 'error', component: 'report_adeptus_insights'},
            {key: 'cancel', component: 'report_adeptus_insights'},
            {key: 'js_ok', component: 'report_adeptus_insights'},
            {key: 'js_plan_id_not_available', component: 'report_adeptus_insights'},
            {key: 'js_failed_billing_portal', component: 'report_adeptus_insights'},
            {key: 'js_failed_load_plans', component: 'report_adeptus_insights'},
            {key: 'js_failed_open_billing', component: 'report_adeptus_insights'},
            {key: 'js_failed_checkout', component: 'report_adeptus_insights'},
            {key: 'js_payment_not_available', component: 'report_adeptus_insights'},
            {key: 'js_checkout_error', component: 'report_adeptus_insights'},
            {key: 'js_subscribe_to', component: 'report_adeptus_insights'},
            {key: 'js_renew_subscription', component: 'report_adeptus_insights'},
            {key: 'js_keep_subscription', component: 'report_adeptus_insights'},
            {key: 'js_yes_cancel_subscription', component: 'report_adeptus_insights'},
            {key: 'js_monthly', component: 'report_adeptus_insights'},
            {key: 'js_annual', component: 'report_adeptus_insights'},
            {key: 'js_current_plan', component: 'report_adeptus_insights'},
            {key: 'js_most_popular', component: 'report_adeptus_insights'},
            {key: 'js_free', component: 'report_adeptus_insights'},
            {key: 'js_forever', component: 'report_adeptus_insights'},
            {key: 'js_per_year', component: 'report_adeptus_insights'},
            {key: 'js_per_month', component: 'report_adeptus_insights'},
            {key: 'js_save_percent', component: 'report_adeptus_insights'},
            {key: 'js_ai_tokens', component: 'report_adeptus_insights'},
            {key: 'js_exports_mo', component: 'report_adeptus_insights'},
            {key: 'js_saved_reports', component: 'report_adeptus_insights'},
            {key: 'js_formats', component: 'report_adeptus_insights'},
            {key: 'js_free_plan', component: 'report_adeptus_insights'},
            {key: 'js_annual_plans_soon', component: 'report_adeptus_insights'},
            {key: 'js_upgrade', component: 'report_adeptus_insights'},
            {key: 'js_downgrade', component: 'report_adeptus_insights'},
            {key: 'js_upgrade_redirect_text', component: 'report_adeptus_insights'},
            {key: 'js_downgrade_redirect_text', component: 'report_adeptus_insights'},
            {key: 'js_downgrade_features_text', component: 'report_adeptus_insights'},
            {key: 'js_action_undone', component: 'report_adeptus_insights'},
            {key: 'js_opening_billing_portal_new_window', component: 'report_adeptus_insights'},
            {key: 'js_open_portal_manually', component: 'report_adeptus_insights'},
            {key: 'js_current_subscription', component: 'report_adeptus_insights'},
            {key: 'js_plan_label', component: 'report_adeptus_insights'},
            {key: 'js_status_label', component: 'report_adeptus_insights'},
            {key: 'js_cancelled_access_ends', component: 'report_adeptus_insights'},
            {key: 'js_payment_failed_attempts', component: 'report_adeptus_insights'},
            {key: 'js_next_billing', component: 'report_adeptus_insights'},
            {key: 'js_api_access_disabled', component: 'report_adeptus_insights'},
            {key: 'js_update_payment_restore', component: 'report_adeptus_insights'},
            {key: 'js_subscription_cancelled_reactivate', component: 'report_adeptus_insights'},
            {key: 'js_preparing_upgrade', component: 'report_adeptus_insights'},
            {key: 'js_opening_billing', component: 'report_adeptus_insights'},
            {key: 'js_online_payments_not_configured', component: 'report_adeptus_insights'},
            {key: 'js_setting_up_payment', component: 'report_adeptus_insights'},
            {key: 'js_redirect_complete_payment', component: 'report_adeptus_insights'},
            {key: 'js_stripe_not_configured', component: 'report_adeptus_insights'},
            {key: 'js_contact_admin', component: 'report_adeptus_insights'},
            {key: 'js_redirect_complete_subscription', component: 'report_adeptus_insights'},
            {key: 'js_welcome_back_renew', component: 'report_adeptus_insights'},
            {key: 'js_not_now', component: 'report_adeptus_insights'},
            {key: 'js_renew_now', component: 'report_adeptus_insights'}
        ]).then(function(results) {
            strings = {
                verifying_payment: results[0],
                verifying_payment_text: results[1],
                upgrade_successful: results[2],
                verification_issue: results[3],
                verification_issue_text: results[4],
                processing_payment: results[5],
                processing_payment_text: results[6],
                checkout_cancelled: results[7],
                checkout_cancelled_text: results[8],
                cancel_subscription_title: results[9],
                cancel_subscription_text: results[10],
                yes_cancel: results[11],
                upgrade_to: results[12],
                downgrade_to: results[13],
                choose_your_plan: results[14],
                redirecting: results[15],
                popup_blocked: results[16],
                popup_blocked_text: results[17],
                portal_creation_failed: results[18],
                connection_error: results[19],
                preparing_checkout: results[20],
                redirecting_checkout: results[21],
                upgrade_coming_soon: results[22],
                billing_portal_error: results[23],
                opening_billing_portal: results[24],
                loading_plans: results[25],
                continue_text: results[26],
                error: results[27],
                cancel: results[28],
                ok: results[29],
                plan_id_not_available: results[30],
                failed_billing_portal: results[31],
                failed_load_plans: results[32],
                failed_open_billing: results[33],
                failed_checkout: results[34],
                payment_not_available: results[35],
                checkout_error: results[36],
                subscribe_to: results[37],
                renew_subscription: results[38],
                keep_subscription: results[39],
                yes_cancel_subscription: results[40],
                monthly: results[41],
                annual: results[42],
                current_plan: results[43],
                most_popular: results[44],
                free: results[45],
                forever: results[46],
                per_year: results[47],
                per_month: results[48],
                save_percent: results[49],
                ai_tokens: results[50],
                exports_mo: results[51],
                saved_reports: results[52],
                formats: results[53],
                free_plan: results[54],
                annual_plans_soon: results[55],
                upgrade: results[56],
                downgrade: results[57],
                upgrade_redirect_text: results[58],
                downgrade_redirect_text: results[59],
                downgrade_features_text: results[60],
                action_undone: results[61],
                opening_billing_portal_new_window: results[62],
                open_portal_manually: results[63],
                current_subscription: results[64],
                plan_label: results[65],
                status_label: results[66],
                cancelled_access_ends: results[67],
                payment_failed_attempts: results[68],
                next_billing: results[69],
                api_access_disabled: results[70],
                update_payment_restore: results[71],
                subscription_cancelled_reactivate: results[72],
                preparing_upgrade: results[73],
                opening_billing: results[74],
                online_payments_not_configured: results[75],
                setting_up_payment: results[76],
                redirect_complete_payment: results[77],
                stripe_not_configured: results[78],
                contact_admin: results[79],
                redirect_complete_subscription: results[80],
                welcome_back_renew: results[81],
                not_now: results[82],
                renew_now: results[83]
            };
            return strings;
        });
    };

    /**
     * Get a loaded string, with fallback
     * @param {string} key - The string key
     * @param {string} fallback - Fallback value if string not loaded
     * @returns {string} The string value
     */
    var getString = function(key, fallback) {
        return strings[key] || fallback || key;
    };

    /**
     * Subscription management for Adeptus Insights plugin
     */
    var Subscription = {
        planId: 0,
        subscriptionData: {},
        /**
         * Initialize subscription functionality
         */
        init: function() {
            var self = this;
            // Load strings first, then initialize
            loadStrings().then(function() {
                self._initAfterStrings();
            }).catch(function() {
                // Continue even if strings fail to load (fallbacks will be used)
                self._initAfterStrings();
            });
        },

        /**
         * Continue initialization after strings are loaded
         */
        _initAfterStrings: function() {
            // Initialize event handlers
            this.initEventHandlers();

            // Check for checkout success in URL
            this.checkForCheckoutSuccess();

            // Initialize subscription display
            this.updateSubscriptionDisplay();
        },

        /**
         * Check if returning from Stripe Checkout and verify the session
         */
        checkForCheckoutSuccess: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var checkoutStatus = urlParams.get('checkout');
            var sessionId = urlParams.get('session_id');

            if (checkoutStatus === 'success' && sessionId) {
                // Show loading
                Swal.fire({
                    title: getString('verifying_payment', 'Verifying Payment...'),
                    html: '<p>' + getString('verifying_payment_text', 'Please wait while we confirm your subscription upgrade.') + '</p>',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });

                // Call verification endpoint
                Ajax.call([{
                    methodname: 'report_adeptus_insights_verify_checkout_session',
                    args: {
                        session_id: sessionId,
                        sesskey: M.cfg.sesskey
                    },
                    done: function(response) {
                        if (response && response.success) {
                            var planName = response.plan_name || 'Pro';
                            Swal.fire({
                                icon: 'success',
                                title: getString('upgrade_successful', 'Upgrade Successful!'),
                                html: '<p>' + getString('upgrade_successful_text', 'Your subscription has been upgraded to <strong>' + planName + '</strong>. Your new features are now available.').replace('{$a}', '<strong>' + planName + '</strong>') + '</p>',
                                confirmButtonColor: '#2563eb',
                                confirmButtonText: getString('continue_text', 'Continue')
                            }).then(function() {
                                // Clean URL and refresh to show new plan
                                var cleanUrl = window.location.href.split('?')[0];
                                window.location.href = cleanUrl;
                            });
                        } else {
                            Swal.fire({
                                icon: 'warning',
                                title: getString('verification_issue', 'Verification Issue'),
                                html: '<p>' + (response.message || 'Could not verify payment.') + '</p>' +
                                      '<p>' + getString('verification_issue_text', 'If you completed payment, your subscription will be updated shortly.') + '</p>',
                                confirmButtonColor: '#2563eb'
                            });
                        }
                    },
                    fail: function(error) {
                        Swal.fire({
                            icon: 'info',
                            title: getString('processing_payment', 'Processing Payment'),
                            html: '<p>' + getString('processing_payment_text', 'Your payment is being processed. Your subscription will be updated within a few minutes.') + '</p>',
                            confirmButtonColor: '#2563eb'
                        });
                    }
                }]);

                // Clean URL to remove checkout params (without refresh)
                var cleanUrl = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, cleanUrl);

            } else if (checkoutStatus === 'cancelled') {
                Swal.fire({
                    icon: 'info',
                    title: getString('checkout_cancelled', 'Checkout Cancelled'),
                    text: getString('checkout_cancelled_text', 'Your checkout was cancelled. No charges were made.'),
                    confirmButtonColor: '#2563eb'
                });

                // Clean URL
                var cancelUrl = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, cancelUrl);
            }
        },

        /**
         * Initialize event handlers
         */
        initEventHandlers: function() {
            // Handle upgrade plan button - redirects to wizard for plan selection
            $(document).on('click', '.btn-upgrade-plan, #upgrade-plan', function(e) {
                e.preventDefault();
                Subscription.handleUpgradeFromFree();
            });

            // Handle downgrade plan buttons (for paid plan users via billing portal)
            $(document).on('click', '.btn-downgrade-plan', function(e) {
                e.preventDefault();
                Subscription.handleDowngradePlan($(this));
            });

            // Handle cancel subscription buttons
            $(document).on('click', '.btn-cancel-subscription, #cancel-subscription', function(e) {
                e.preventDefault();
                Subscription.handleCancelSubscription($(this));
            });

            // Handle billing portal access
            $(document).on('click', '.btn-billing-portal', function(e) {
                e.preventDefault();
                Subscription.openBillingPortal();
            });

            // Handle modify subscription button
            $(document).on('click', '.btn-modify-subscription, #modify-subscription', function(e) {
                e.preventDefault();
                Subscription.openBillingPortal();
            });

            // Handle view plans button (open billing portal)
            $(document).on('click', '.btn-view-plans, #view-plans', function(e) {
                e.preventDefault();
                Subscription.openBillingPortal();
            });

            // Handle select plan button (new subscriptions)
            $(document).on('click', '.select-plan-btn', function(e) {
                e.preventDefault();
                var planId = $(this).data('plan-id');
                var planName = $(this).data('plan-name');
                Subscription.handleSelectPlan($(this), planId, planName);
            });

            // Handle renew subscription button
            $(document).on('click', '#renew-subscription', function(e) {
                e.preventDefault();
                Subscription.handleRenewSubscription();
            });

            // Handle update payment button
            $(document).on('click', '#update-payment', function(e) {
                e.preventDefault();
                Subscription.openBillingPortal();
            });
        },

        /**
         * Handle upgrade plan
         */
        handleUpgradePlan: function($button) {
            var planId = $button.data('plan-id');
            var planName = $button.data('plan-name');

            if (!planId) {
                Swal.fire({
                    icon: 'error',
                    title: getString('error', 'Error'),
                    text: getString('plan_id_not_available', 'Plan ID not available. Please try again.'),
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            // Show confirmation dialog
            Swal.fire({
                icon: 'question',
                title: getString('upgrade_to', 'Upgrade to {$a}?').replace('{$a}', planName),
                html: '<p>' + getString('upgrade_redirect_text', 'You will be redirected to the billing portal to complete the upgrade.') + '</p>',
                showCancelButton: true,
                confirmButtonColor: '#f39c12',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-arrow-up"></i> ' + getString('upgrade', 'Upgrade'),
                cancelButtonText: getString('cancel', 'Cancel')
            }).then(function(result) {
                if (result.isConfirmed) {
                    Subscription.createBillingPortalSession(planId, 'upgrade');
                }
            });
        },

        /**
         * Handle downgrade plan
         */
        handleDowngradePlan: function($button) {
            var planId = $button.data('plan-id');
            var planName = $button.data('plan-name');

            if (!planId) {
                Swal.fire({
                    icon: 'error',
                    title: getString('error', 'Error'),
                    text: getString('plan_id_not_available', 'Plan ID not available. Please try again.'),
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            // Show confirmation dialog
            Swal.fire({
                icon: 'warning',
                title: getString('downgrade_to', 'Downgrade to {$a}?').replace('{$a}', planName),
                html: '<p>' + getString('downgrade_redirect_text', 'You will be redirected to the billing portal to complete the downgrade.') + '</p>' +
                      '<p class="text-muted"><small>' + getString('downgrade_features_text', 'Your current plan features will remain active until the end of the billing period.') + '</small></p>',
                showCancelButton: true,
                confirmButtonColor: '#17a2b8',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-arrow-down"></i> ' + getString('downgrade', 'Downgrade'),
                cancelButtonText: getString('cancel', 'Cancel')
            }).then(function(result) {
                if (result.isConfirmed) {
                    Subscription.createBillingPortalSession(planId, 'downgrade');
                }
            });
        },

        /**
         * Handle cancel subscription
         */
        handleCancelSubscription: function($button) {
            Swal.fire({
                icon: 'warning',
                title: getString('cancel_subscription_title', 'Cancel Subscription?'),
                html: '<p><strong>' + getString('cancel_subscription_text', 'Are you sure you want to cancel your subscription? You will lose access to premium features at the end of your billing period.') + '</strong></p>' +
                      '<p class="text-danger"><small>' + getString('action_undone', 'This action cannot be undone.') + '</small></p>',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-times"></i> ' + getString('yes_cancel', 'Yes, cancel it'),
                cancelButtonText: getString('keep_subscription', 'Keep Subscription'),
                focusCancel: true
            }).then(function(result) {
                if (result.isConfirmed) {
                    Subscription.createBillingPortalSession(null, 'cancel');
                }
            });
        },

        /**
         * Open billing portal
         */
        openBillingPortal: function() {
            Subscription.createBillingPortalSession(Subscription.subscriptionData.plan_id, 'modify');
        },

        /**
         * Create billing portal session
         */
        createBillingPortalSession: function(planId, action) {
            var returnUrl = window.location.href;
            var data = {
                return_url: returnUrl,
                sesskey: M.cfg.sesskey
            };

            // Only add plan_id and action if they are defined and not null
            if (planId && planId !== 'undefined' && planId !== null) {
                data.plan_id = planId;
            }

            if (action && action !== 'undefined' && action !== null) {
                data.action = action;
            }

            Ajax.call([{
                methodname: 'report_adeptus_insights_create_billing_portal_session',
                args: data,
                done: function(response) {
                    if (response && response.success && response.portal_url) {
                        Swal.fire({
                            icon: 'success',
                            title: getString('redirecting', 'Redirecting...'),
                            html: '<p>' + getString('opening_billing_portal_new_window', 'Opening billing portal in a new window...') + '</p>',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            didOpen: function() {
                                Swal.showLoading();
                            }
                        });

                        // Open in new tab
                        setTimeout(function() {
                            var newWindow = window.open(response.portal_url, '_blank');
                            if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
                                Swal.fire({
                                    icon: 'warning',
                                    title: getString('popup_blocked', 'Popup Blocked'),
                                    html: '<p>' + getString('popup_blocked_text', 'Please allow popups for this site to access the billing portal.') + '</p>' +
                                          '<p><a href="' + response.portal_url + '" target="_blank" class="btn btn-primary">' + getString('open_portal_manually', 'Open Portal Manually') + '</a></p>',
                                    showConfirmButton: true,
                                    confirmButtonText: getString('ok', 'OK')
                                });
                            }
                        }, 1000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: getString('portal_creation_failed', 'Portal Creation Failed'),
                            text: (response && response.message) || getString('failed_billing_portal', 'Failed to create billing portal session. Please try again.'),
                            confirmButtonColor: '#3085d6'
                        });
                    }
                },
                fail: function(error) {
                    Swal.fire({
                        icon: 'error',
                        title: getString('connection_error', 'Connection Error'),
                        text: getString('failed_billing_portal', 'Failed to create billing portal session. Please check your connection and try again.'),
                        confirmButtonColor: '#3085d6'
                    });
                }
            }]);
        },

        /**
         * Update subscription display
         */
        updateSubscriptionDisplay: function() {
            // Get current subscription data
            Ajax.call([{
                methodname: 'report_adeptus_insights_get_subscription_details',
                args: {},
                done: function(response) {
                    if (response.success && response.data) {
                        Subscription.subscriptionData = response.data;
                        Subscription.renderSubscriptionInfo(response.data);
                    }
                },
                fail: function(error) {
                    // Subscription details fetch failed silently.
                }
            }]);
        },

        /**
         * Render subscription information
         */
        renderSubscriptionInfo: function(subscriptionData) {
            var $container = $('.subscription-info');
            if (!$container.length) return;

            var html = '<div class="current-subscription">';
            html += '<h3>' + getString('current_subscription', 'Current Subscription') + '</h3>';
            html += '<div class="adeptus-subscription-details">';
            html += '<p><strong>' + getString('plan_label', 'Plan:') + '</strong> ' + (subscriptionData.plan_name || 'Unknown') + '</p>';

            // Display status with appropriate styling and messaging
            var statusClass = 'text-success';
            var statusText = subscriptionData.status || 'Unknown';

            if (subscriptionData.should_disable_api_access) {
                statusClass = 'text-danger';
            } else if (subscriptionData.is_cancelled) {
                statusClass = 'text-warning';
            } else if (subscriptionData.has_payment_issues) {
                statusClass = 'text-warning';
            }

            html += '<p><strong>' + getString('status_label', 'Status:') + '</strong> <span class="' + statusClass + '">' + statusText + '</span></p>';

            // Display status message if available
            if (subscriptionData.status_message) {
                var messageClass = 'text-muted';
                if (subscriptionData.should_disable_api_access) {
                    messageClass = 'text-danger';
                } else if (subscriptionData.is_cancelled || subscriptionData.has_payment_issues) {
                    messageClass = 'text-warning';
                }
                html += '<p class="' + messageClass + '"><small>' + subscriptionData.status_message + '</small></p>';
            }

            // Show cancellation info
            if (subscriptionData.cancel_at_period_end) {
                var cancelDate = new Date(subscriptionData.current_period_end * 1000);
                html += '<p class="text-warning"><strong>' + getString('cancelled_access_ends', 'Cancelled: Access ends on {$a}').replace('{$a}', cancelDate.toLocaleDateString()) + '</strong></p>';
            }

            // Show payment failure info
            if (subscriptionData.failed_payment_attempts > 0) {
                html += '<p class="text-danger"><strong>' + getString('payment_failed_attempts', 'Payment Failed: {$a} failed attempts').replace('{$a}', subscriptionData.failed_payment_attempts) + '</strong></p>';
            }

            if (subscriptionData.current_period_end) {
                var endDate = new Date(subscriptionData.current_period_end * 1000);
                html += '<p><strong>' + getString('next_billing', 'Next billing:') + '</strong> ' + endDate.toLocaleDateString() + '</p>';
            }

            html += '</div>';
            html += '</div>';

            $container.html(html);

            // Update API access status globally
            Subscription.updateApiAccessStatus(subscriptionData);
        },

        /**
         * Update API access status globally
         */
        updateApiAccessStatus: function(subscriptionData) {
            var apiAccessDisabled = subscriptionData.should_disable_api_access || false;

            // Store the status globally for other modules to check
            if (typeof window !== 'undefined') {
                window.adeptusApiAccessDisabled = apiAccessDisabled;
                window.adeptusSubscriptionStatus = subscriptionData;
            }

            // Disable/enable API-dependent elements
            if (apiAccessDisabled) {
                $('.btn-send-message, .btn-generate-report, .btn-ai-assistant').prop('disabled', true)
                    .attr('title', getString('api_access_disabled', 'API access disabled') + ': ' + (subscriptionData.status_message || ''));

                // Show warning message
                this.showApiAccessWarning(subscriptionData);
            } else {
                $('.btn-send-message, .btn-generate-report, .btn-ai-assistant').prop('disabled', false)
                    .removeAttr('title');

                // Hide warning message
                this.hideApiAccessWarning();
            }
        },

        /**
         * Show API access warning
         */
        showApiAccessWarning: function(subscriptionData) {
            var $existingWarning = $('.api-access-warning');
            if ($existingWarning.length) return;

            var warningHtml = '<div class="api-access-warning alert alert-danger alert-dismissible fade show" role="alert">';
            warningHtml += '<strong>' + getString('api_access_disabled', 'API Access Disabled') + '</strong><br>';
            warningHtml += subscriptionData.status_message || '';

            if (subscriptionData.has_payment_issues) {
                warningHtml += '<br><small>' + getString('update_payment_restore', 'Please update your payment method to restore access.') + '</small>';
            } else if (subscriptionData.is_cancelled) {
                warningHtml += '<br><small>' + getString('subscription_cancelled_reactivate', 'Your subscription has been cancelled. Please reactivate to restore access.') + '</small>';
            }

            warningHtml += '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            warningHtml += '</div>';

            $('body').prepend(warningHtml);
        },

        /**
         * Hide API access warning
         */
        hideApiAccessWarning: function() {
            $('.api-access-warning').remove();
        },

        /**
         * Refresh subscription data
         */
        refresh: function() {
            this.updateSubscriptionDisplay();
        },

        /**
         * Stored plans data for toggle switching
         */
        plansData: null,
        currentInterval: 'monthly',

        /**
         * Handle upgrade from free plan
         * Shows a modal with available plans
         */
        handleUpgradeFromFree: function() {
            // Show loading state
            Swal.fire({
                title: getString('loading_plans', 'Loading Plans...'),
                html: '<div style="padding: 20px;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>',
                showConfirmButton: false,
                allowOutsideClick: false
            });

            // Fetch available plans
            fetch(M.cfg.wwwroot + '/report/adeptus_insights/ajax/get_available_plans.php?sesskey=' + M.cfg.sesskey)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success && (data.monthly_plans.length > 0 || data.yearly_plans.length > 0)) {
                        // Store plans data for toggle switching
                        Subscription.plansData = {
                            monthly: data.monthly_plans || [],
                            yearly: data.yearly_plans || []
                        };
                        Subscription.currentInterval = 'monthly';
                        Subscription.showPlansModal(data);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: getString('error', 'Error'),
                            text: data.message || getString('failed_load_plans', 'Failed to load plans. Please try again.'),
                            confirmButtonColor: '#3085d6'
                        });
                    }
                })
                .catch(function(error) {
                    Swal.fire({
                        icon: 'error',
                        title: getString('connection_error', 'Connection Error'),
                        text: getString('failed_load_plans', 'Failed to load plans. Please check your connection and try again.'),
                        confirmButtonColor: '#3085d6'
                    });
                });
        },

        /**
         * Show plans selection modal with monthly/yearly toggle
         */
        showPlansModal: function(data) {
            var hasYearlyPlans = data.has_yearly_plans || false;
            var maxYearlySavings = data.max_yearly_savings || 0;

            // Build modal HTML with toggle
            var modalHtml = '<div class="adeptus-plans-modal-container" style="text-align: left;">';

            // Billing toggle (only if yearly plans exist)
            if (hasYearlyPlans) {
                modalHtml += '<div class="adeptus-billing-toggle-container" style="display: flex; justify-content: center; margin-bottom: 24px;">';
                modalHtml += '<div class="adeptus-billing-toggle" style="display: inline-flex; align-items: center; background: #f3f4f6; border-radius: 50px; padding: 6px; gap: 4px;">';
                modalHtml += '<button type="button" class="adeptus-billing-toggle-btn active" data-interval="monthly" style="' +
                    'padding: 10px 24px; border: none; background: white; border-radius: 50px; ' +
                    'font-weight: 600; font-size: 14px; cursor: pointer; color: #1f2937; ' +
                    'box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s ease;">' + getString('monthly', 'Monthly') + '</button>';
                modalHtml += '<button type="button" class="adeptus-billing-toggle-btn" data-interval="yearly" style="' +
                    'padding: 10px 24px; border: none; background: transparent; border-radius: 50px; ' +
                    'font-weight: 600; font-size: 14px; cursor: pointer; color: #6b7280; transition: all 0.3s ease;">' +
                    getString('annual', 'Annual');
                if (maxYearlySavings > 0) {
                    modalHtml += '<span style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); ' +
                        'color: white; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 20px; ' +
                        'margin-left: 6px; text-transform: uppercase;">' + getString('save_percent', 'Save {$a}%').replace('{$a}', maxYearlySavings) + '</span>';
                }
                modalHtml += '</button>';
                modalHtml += '</div></div>';
            }

            // Plans grid container
            modalHtml += '<div id="plans-grid-container">';
            modalHtml += Subscription.buildPlansGrid(data.monthly_plans || [], 'monthly');
            modalHtml += '</div>';

            modalHtml += '</div>';

            Swal.fire({
                title: '<i class="fa fa-rocket" style="color: #2563eb;"></i> ' + getString('choose_your_plan', 'Choose Your Plan'),
                html: modalHtml,
                width: '95%',
                showCancelButton: true,
                showConfirmButton: false,
                cancelButtonText: getString('cancel', 'Cancel'),
                cancelButtonColor: '#95a5a6',
                customClass: {
                    popup: 'plans-modal-popup'
                },
                didOpen: function() {
                    Subscription.initPlansModalHandlers();
                }
            });
        },

        /**
         * Build plans grid HTML
         */
        buildPlansGrid: function(plans, interval) {
            if (!plans || plans.length === 0) {
                return '<div style="text-align: center; padding: 40px; color: #6b7280;">' +
                    '<i class="fa fa-calendar-times-o fa-2x" style="margin-bottom: 10px;"></i>' +
                    '<p>' + getString('annual_plans_soon', 'Annual plans coming soon!') + '</p></div>';
            }

            var html = '<div class="plans-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; max-width: 1000px; margin: 0 auto;">';

            plans.forEach(function(plan) {
                var borderColor = plan.is_current ? '#10b981' : (plan.is_popular ? '#2563eb' : '#e5e7eb');
                var bgColor = plan.is_current ? 'linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%)' : 'white';

                html += '<div class="adeptus-plan-card" style="' +
                    'background: ' + bgColor + '; ' +
                    'border: 2px solid ' + borderColor + '; ' +
                    'border-radius: 16px; ' +
                    'padding: 28px 24px; ' +
                    'position: relative; ' +
                    'transition: all 0.3s ease; ' +
                    'display: flex; ' +
                    'flex-direction: column;">';

                // Badge
                if (plan.is_current) {
                    html += '<div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); ' +
                        'background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; ' +
                        'font-size: 11px; font-weight: 700; padding: 5px 14px; border-radius: 20px; ' +
                        'text-transform: uppercase; letter-spacing: 0.5px;">' + getString('current_plan', 'Current Plan') + '</div>';
                } else if (plan.is_popular) {
                    html += '<div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); ' +
                        'background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; ' +
                        'font-size: 11px; font-weight: 700; padding: 5px 14px; border-radius: 20px; ' +
                        'text-transform: uppercase; letter-spacing: 0.5px;">' + getString('most_popular', 'Most Popular') + '</div>';
                }

                // Header
                html += '<div style="text-align: center; margin-bottom: 20px; padding-top: 8px;">';
                html += '<div style="font-size: 22px; font-weight: 700; color: #1f2937; margin-bottom: 8px;">' +
                    plan.short_name + '</div>';
                if (plan.description) {
                    html += '<div style="color: #6b7280; font-size: 13px; line-height: 1.5;">' +
                        plan.description + '</div>';
                }
                html += '</div>';

                // Price
                html += '<div style="text-align: center; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #e5e7eb;">';
                if (plan.is_free) {
                    html += '<div style="font-size: 42px; font-weight: 800; color: #10b981;">' + getString('free', 'Free') + '</div>';
                    html += '<div style="color: #6b7280; font-size: 13px;">' + getString('forever', 'Forever') + '</div>';
                } else {
                    html += '<div style="font-size: 42px; font-weight: 800; color: #1f2937;">' +
                        plan.price_formatted + '</div>';
                    var periodText = interval === 'yearly' ? getString('per_year', 'per year') : getString('per_month', 'per month');
                    html += '<div style="color: #6b7280; font-size: 13px;">' + periodText + '</div>';
                    if (plan.has_savings) {
                        html += '<div style="margin-top: 6px;"><span style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); ' +
                            'color: white; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px;">' +
                            getString('save_percent', 'Save {$a}%').replace('{$a}', plan.savings_percent) + '</span></div>';
                    }
                }
                html += '</div>';

                // Limits - 4 items in 2x2 grid
                html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb;">';
                html += '<div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 10px;">' +
                    '<div style="font-size: 16px; font-weight: 700; color: #1f2937;">' + plan.tokens_limit + '</div>' +
                    '<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">' + getString('ai_tokens', 'AI Tokens') + '</div></div>';
                html += '<div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 10px;">' +
                    '<div style="font-size: 16px; font-weight: 700; color: #1f2937;">' + plan.exports_limit + '</div>' +
                    '<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">' + getString('exports_mo', 'Exports/mo') + '</div></div>';
                html += '<div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 10px;">' +
                    '<div style="font-size: 16px; font-weight: 700; color: #1f2937;">' + plan.reports_limit + '</div>' +
                    '<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">' + getString('saved_reports', 'Saved Reports') + '</div></div>';
                html += '<div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 10px;">' +
                    '<div style="font-size: 16px; font-weight: 700; color: #1f2937;">' + plan.export_formats + '</div>' +
                    '<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">' + getString('formats', 'Formats') + '</div></div>';
                html += '</div>';

                // Features list
                if (plan.features && plan.features.length > 0) {
                    html += '<div style="flex: 1; margin-bottom: 20px;">';
                    html += '<ul style="list-style: none; padding: 0; margin: 0;">';
                    plan.features.forEach(function(feature) {
                        html += '<li style="display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; ' +
                            'color: #374151; font-size: 13px; line-height: 1.4;">' +
                            '<i class="fa fa-check" style="color: #10b981; font-size: 12px; margin-top: 3px; flex-shrink: 0;"></i> ' +
                            feature + '</li>';
                    });
                    html += '</ul></div>';
                }

                // Action button
                html += '<div style="margin-top: auto;">';
                if (plan.is_current) {
                    html += '<button class="adeptus-plan-select-btn" disabled style="' +
                        'display: block; width: 100%; padding: 14px; border: none; border-radius: 10px; ' +
                        'background: #e5e7eb; color: #6b7280; font-weight: 600; font-size: 14px; cursor: default;">' +
                        '<i class="fa fa-check-circle"></i> ' + getString('current_plan', 'Current Plan') + '</button>';
                } else if (plan.is_free) {
                    html += '<button class="adeptus-plan-select-btn" disabled style="' +
                        'display: block; width: 100%; padding: 14px; border: 2px solid #e5e7eb; border-radius: 10px; ' +
                        'background: white; color: #374151; font-weight: 600; font-size: 14px; cursor: default;">' +
                        getString('free_plan', 'Free Plan') + '</button>';
                } else {
                    // Include stripe_price_id if available for checkout
                    var stripePriceId = plan.stripe_price_id || '';
                    var stripeConfigured = plan.stripe_configured || false;
                    html += '<button class="adeptus-plan-select-btn" data-plan-id="' + (plan.id || '') + '" ' +
                        'data-plan-name="' + plan.short_name + '" ' +
                        'data-stripe-price-id="' + stripePriceId + '" ' +
                        'data-stripe-configured="' + stripeConfigured + '" style="' +
                        'display: block; width: 100%; padding: 14px; border: none; border-radius: 10px; ' +
                        'background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; ' +
                        'font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s ease;">' +
                        '<i class="fa fa-arrow-up"></i> ' + getString('upgrade_to', 'Upgrade to') + ' ' + plan.short_name + '</button>';
                }
                html += '</div>';

                html += '</div>'; // end adeptus-plan-card
            });

            html += '</div>'; // end plans-grid
            return html;
        },

        /**
         * Initialize plans modal event handlers
         */
        initPlansModalHandlers: function() {
            // Billing toggle handler
            document.querySelectorAll('.adeptus-billing-toggle-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var interval = this.getAttribute('data-interval');
                    if (interval === Subscription.currentInterval) return;

                    // Update toggle UI
                    document.querySelectorAll('.adeptus-billing-toggle-btn').forEach(function(b) {
                        b.classList.remove('active');
                        b.style.background = 'transparent';
                        b.style.color = '#6b7280';
                        b.style.boxShadow = 'none';
                    });
                    this.classList.add('active');
                    this.style.background = 'white';
                    this.style.color = '#1f2937';
                    this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';

                    Subscription.currentInterval = interval;

                    // Update plans display
                    var plans = Subscription.plansData[interval] || [];
                    var container = document.getElementById('plans-grid-container');
                    if (container) {
                        container.innerHTML = Subscription.buildPlansGrid(plans, interval);
                        Subscription.initPlanButtonHandlers();
                    }
                });
            });

            // Initialize plan button handlers
            Subscription.initPlanButtonHandlers();
        },

        /**
         * Initialize plan button click handlers
         */
        initPlanButtonHandlers: function() {
            document.querySelectorAll('.adeptus-plan-select-btn:not([disabled])').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var planName = this.getAttribute('data-plan-name');
                    var planId = this.getAttribute('data-plan-id');
                    var stripePriceId = this.getAttribute('data-stripe-price-id');
                    var stripeConfigured = this.getAttribute('data-stripe-configured') === 'true';

                    // If Stripe is configured for this plan, use checkout session
                    if (stripeConfigured && stripePriceId) {
                        Subscription.createCheckoutSession(planId, stripePriceId, planName);
                    } else {
                        // Fallback to billing portal or show "coming soon" message
                        Subscription.openBillingPortalForUpgrade(planName, planId);
                    }
                });

                // Hover effects
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 8px 20px rgba(37, 99, 235, 0.3)';
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });
        },

        /**
         * Open billing portal for upgrade
         * Uses the same approach as Step 2 - opens billing portal where user can select plan
         */
        openBillingPortalForUpgrade: function(planName, planId) {
            Swal.fire({
                title: getString('opening_billing_portal', 'Opening Billing Portal...'),
                html: '<p>' + getString('preparing_upgrade', 'Preparing upgrade to {$a}...').replace('{$a}', planName) + '</p>',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });

            var returnUrl = window.location.href;
            var args = {
                return_url: returnUrl,
                sesskey: M.cfg.sesskey
            };

            // Pass plan_id if available (needed for customer creation)
            if (planId) {
                args.plan_id = planId;
            }

            Ajax.call([{
                methodname: 'report_adeptus_insights_create_billing_portal_session',
                args: args,
                done: function(response) {
                    if (response && response.success && response.portal_url) {
                        Swal.fire({
                            icon: 'success',
                            title: getString('redirecting', 'Redirecting...'),
                            html: '<p>' + getString('opening_billing', 'Opening billing portal...') + '</p>',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            allowOutsideClick: false
                        });

                        setTimeout(function() {
                            // Redirect in same window (like Step 2 does)
                            window.location.href = response.portal_url;
                        }, 1000);
                    } else {
                        var errorMessage = (response && response.message) || getString('failed_open_billing', 'Failed to open billing portal.');

                        // Check for specific error codes and provide helpful messages
                        if (errorMessage.indexOf('NO_STRIPE_CUSTOMER') !== -1 ||
                            errorMessage.indexOf('payment_enabled') !== -1 ||
                            errorMessage.indexOf('Stripe') !== -1) {
                            Swal.fire({
                                icon: 'info',
                                title: getString('upgrade_coming_soon', 'Upgrade Coming Soon'),
                                html: '<p>' + getString('online_payments_not_configured', 'Online payments are not yet configured for this installation.') + '</p>' +
                                      '<p>' + getString('contact_admin', 'To upgrade to <strong>{$a}</strong>, please contact your administrator or email:').replace('{$a}', planName) + '</p>' +
                                      '<p><a href="mailto:support@adeptus360.com?subject=Upgrade%20to%20' + planName + '">support@adeptus360.com</a></p>',
                                confirmButtonColor: '#2563eb',
                                confirmButtonText: getString('ok', 'OK')
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: getString('billing_portal_error', 'Billing Portal Error'),
                                text: errorMessage,
                                confirmButtonColor: '#3085d6'
                            });
                        }
                    }
                },
                fail: function(error) {
                    Swal.fire({
                        icon: 'error',
                        title: getString('connection_error', 'Connection Error'),
                        text: getString('failed_open_billing', 'Failed to open billing portal. Please try again.'),
                        confirmButtonColor: '#3085d6'
                    });
                }
            }]);
        },

        /**
         * Create Stripe Checkout session for new subscriptions
         * @param {string} planId - The plan ID
         * @param {string} stripePriceId - The Stripe price ID
         * @param {string} planName - The plan name for display
         */
        createCheckoutSession: function(planId, stripePriceId, planName) {
            Swal.fire({
                title: getString('preparing_checkout', 'Preparing Checkout...'),
                html: '<p>' + getString('setting_up_payment', 'Setting up payment for {$a}...').replace('{$a}', planName) + '</p>',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });

            var returnUrl = window.location.href;
            var args = {
                plan_id: parseInt(planId),
                stripe_price_id: stripePriceId,
                return_url: returnUrl,
                sesskey: M.cfg.sesskey
            };

            Ajax.call([{
                methodname: 'report_adeptus_insights_create_checkout_session',
                args: args,
                done: function(response) {
                    if (response && response.success && response.checkout_url) {
                        Swal.fire({
                            icon: 'success',
                            title: getString('redirecting_checkout', 'Redirecting to Checkout...'),
                            html: '<p>' + getString('redirect_complete_payment', 'You will be redirected to complete your payment.') + '</p>',
                            timer: 2000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            allowOutsideClick: false
                        });

                        setTimeout(function() {
                            window.location.href = response.checkout_url;
                        }, 1000);
                    } else {
                        var errorMessage = (response && response.message) || getString('failed_checkout', 'Failed to create checkout session.');
                        var errorCode = (response && response.error_code) || '';

                        // Handle specific error codes
                        if (errorCode === 'STRIPE_NOT_CONFIGURED') {
                            Swal.fire({
                                icon: 'info',
                                title: getString('payment_not_available', 'Payment Not Available'),
                                html: '<p>' + getString('stripe_not_configured', 'Stripe is not yet configured for the <strong>{$a}</strong> plan.').replace('{$a}', planName) + '</p>' +
                                      '<p>' + getString('contact_admin', 'Please contact your administrator or email:') + '</p>' +
                                      '<p><a href="mailto:support@adeptus360.com?subject=Upgrade%20to%20' + planName + '">support@adeptus360.com</a></p>',
                                confirmButtonColor: '#2563eb',
                                confirmButtonText: getString('ok', 'OK')
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: getString('checkout_error', 'Checkout Error'),
                                text: errorMessage,
                                confirmButtonColor: '#3085d6'
                            });
                        }
                    }
                },
                fail: function(error) {
                    Swal.fire({
                        icon: 'error',
                        title: getString('connection_error', 'Connection Error'),
                        text: getString('failed_checkout', 'Failed to create checkout session. Please try again.'),
                        confirmButtonColor: '#3085d6'
                    });
                }
            }]);
        },

        /**
         * Handle select plan (for new subscriptions)
         */
        handleSelectPlan: function($button, planId, planName) {
            if (!planId) {
                Swal.fire({
                    icon: 'error',
                    title: getString('error', 'Error'),
                    text: getString('plan_id_not_available', 'Plan ID not available. Please try again.'),
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            // Show confirmation dialog
            Swal.fire({
                icon: 'info',
                title: getString('subscribe_to', 'Subscribe to {$a}?').replace('{$a}', planName),
                html: '<p>' + getString('redirect_complete_subscription', 'You will be redirected to complete your subscription.') + '</p>',
                showCancelButton: true,
                confirmButtonColor: '#3498db',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-credit-card"></i> ' + getString('subscribe_to', 'Subscribe Now'),
                cancelButtonText: getString('cancel', 'Cancel')
            }).then(function(result) {
                if (result.isConfirmed) {
                    Subscription.createBillingPortalSession(planId, 'subscribe');
                }
            });
        },

        /**
         * Handle renew subscription
         */
        handleRenewSubscription: function() {
            Swal.fire({
                icon: 'success',
                title: getString('renew_subscription', 'Renew Your Subscription?'),
                html: '<p>' + getString('welcome_back_renew', 'Welcome back! Renew your subscription to regain access to all premium features.') + '</p>',
                showCancelButton: true,
                confirmButtonColor: '#27ae60',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-refresh"></i> ' + getString('renew_now', 'Renew Subscription'),
                cancelButtonText: getString('not_now', 'Not Now')
            }).then(function(result) {
                if (result.isConfirmed) {
                    // Open billing portal for renewal
                    Subscription.createBillingPortalSession(null, 'renew');
                }
            });
        }
    };

    return Subscription;
});

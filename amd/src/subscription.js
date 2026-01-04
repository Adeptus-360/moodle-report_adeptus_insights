// jshint ignore:start
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    'use strict';

    var Swal = window.Swal;

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
            // Initialize event handlers
            this.initEventHandlers();

            // Initialize subscription display
            this.updateSubscriptionDisplay();
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
                console.error('[Subscription] No plan ID available');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Plan ID not available. Please try again.',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            // Show confirmation dialog
            Swal.fire({
                icon: 'question',
                title: 'Upgrade to ' + planName + '?',
                html: '<p>You will be redirected to the billing portal to complete the upgrade.</p>',
                showCancelButton: true,
                confirmButtonColor: '#f39c12',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-arrow-up"></i> Upgrade',
                cancelButtonText: 'Cancel'
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
                console.error('[Subscription] No plan ID available');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Plan ID not available. Please try again.',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            // Show confirmation dialog
            Swal.fire({
                icon: 'warning',
                title: 'Downgrade to ' + planName + '?',
                html: '<p>You will be redirected to the billing portal to complete the downgrade.</p>' +
                      '<p class="text-muted"><small>Your current plan features will remain active until the end of the billing period.</small></p>',
                showCancelButton: true,
                confirmButtonColor: '#17a2b8',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-arrow-down"></i> Downgrade',
                cancelButtonText: 'Cancel'
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
                title: 'Cancel Subscription?',
                html: '<p><strong>Are you sure you want to cancel your subscription?</strong></p>' +
                      '<p>You will lose access to premium features at the end of your billing period.</p>' +
                      '<p class="text-danger"><small>This action cannot be undone.</small></p>',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-times"></i> Yes, Cancel Subscription',
                cancelButtonText: 'Keep Subscription',
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
                            title: 'Redirecting...',
                            html: '<p>Opening billing portal in a new window...</p>',
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
                                    title: 'Popup Blocked',
                                    html: '<p>Please allow popups for this site and try again.</p>' +
                                          '<p><a href="' + response.portal_url + '" target="_blank" class="btn btn-primary">Open Portal Manually</a></p>',
                                    showConfirmButton: true,
                                    confirmButtonText: 'OK'
                                });
                            }
                        }, 1000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Portal Creation Failed',
                            text: (response && response.message) || 'Failed to create billing portal session. Please try again.',
                            confirmButtonColor: '#3085d6'
                        });
                    }
                },
                fail: function(error) {
                    console.error('[Subscription] AJAX failed:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to create billing portal session. Please check your connection and try again.',
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
                    console.error('[Subscription] Failed to get subscription details:', error);
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
            html += '<h3>Current Subscription</h3>';
            html += '<div class="subscription-details">';
            html += '<p><strong>Plan:</strong> ' + (subscriptionData.plan_name || 'Unknown') + '</p>';

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

            html += '<p><strong>Status:</strong> <span class="' + statusClass + '">' + statusText + '</span></p>';

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
                html += '<p class="text-warning"><strong>⚠️ Cancelled:</strong> Access ends on ' + cancelDate.toLocaleDateString() + '</p>';
            }

            // Show payment failure info
            if (subscriptionData.failed_payment_attempts > 0) {
                html += '<p class="text-danger"><strong>❌ Payment Failed:</strong> ' + subscriptionData.failed_payment_attempts + ' failed attempts</p>';
            }

            if (subscriptionData.current_period_end) {
                var endDate = new Date(subscriptionData.current_period_end * 1000);
                html += '<p><strong>Next billing:</strong> ' + endDate.toLocaleDateString() + '</p>';
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
                    .attr('title', 'API access disabled: ' + (subscriptionData.status_message || 'Subscription issue'));

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
            warningHtml += '<strong>⚠️ API Access Disabled</strong><br>';
            warningHtml += subscriptionData.status_message || 'Your subscription has an issue that prevents API access.';

            if (subscriptionData.has_payment_issues) {
                warningHtml += '<br><small>Please update your payment method to restore access.</small>';
            } else if (subscriptionData.is_cancelled) {
                warningHtml += '<br><small>Your subscription has been cancelled. Please reactivate to restore access.</small>';
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
                title: 'Loading Plans...',
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
                            title: 'Error',
                            text: data.message || 'Failed to load plans. Please try again.',
                            confirmButtonColor: '#3085d6'
                        });
                    }
                })
                .catch(function(error) {
                    console.error('[Subscription] Error fetching plans:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to load plans. Please check your connection and try again.',
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
            var modalHtml = '<div class="plans-modal-container" style="text-align: left;">';

            // Billing toggle (only if yearly plans exist)
            if (hasYearlyPlans) {
                modalHtml += '<div class="billing-toggle-container" style="display: flex; justify-content: center; margin-bottom: 24px;">';
                modalHtml += '<div class="billing-toggle" style="display: inline-flex; align-items: center; background: #f3f4f6; border-radius: 50px; padding: 6px; gap: 4px;">';
                modalHtml += '<button type="button" class="billing-toggle-btn active" data-interval="monthly" style="' +
                    'padding: 10px 24px; border: none; background: white; border-radius: 50px; ' +
                    'font-weight: 600; font-size: 14px; cursor: pointer; color: #1f2937; ' +
                    'box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s ease;">Monthly</button>';
                modalHtml += '<button type="button" class="billing-toggle-btn" data-interval="yearly" style="' +
                    'padding: 10px 24px; border: none; background: transparent; border-radius: 50px; ' +
                    'font-weight: 600; font-size: 14px; cursor: pointer; color: #6b7280; transition: all 0.3s ease;">' +
                    'Annual';
                if (maxYearlySavings > 0) {
                    modalHtml += '<span style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); ' +
                        'color: white; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 20px; ' +
                        'margin-left: 6px; text-transform: uppercase;">Save ' + maxYearlySavings + '%</span>';
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
                title: '<i class="fa fa-rocket" style="color: #2563eb;"></i> Choose Your Plan',
                html: modalHtml,
                width: '95%',
                showCancelButton: true,
                showConfirmButton: false,
                cancelButtonText: 'Cancel',
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
                    '<p>Annual plans coming soon!</p></div>';
            }

            var html = '<div class="plans-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; max-width: 1000px; margin: 0 auto;">';

            plans.forEach(function(plan) {
                var borderColor = plan.is_current ? '#10b981' : (plan.is_popular ? '#2563eb' : '#e5e7eb');
                var bgColor = plan.is_current ? 'linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%)' : 'white';

                html += '<div class="plan-card" style="' +
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
                        'text-transform: uppercase; letter-spacing: 0.5px;">Current Plan</div>';
                } else if (plan.is_popular) {
                    html += '<div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); ' +
                        'background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; ' +
                        'font-size: 11px; font-weight: 700; padding: 5px 14px; border-radius: 20px; ' +
                        'text-transform: uppercase; letter-spacing: 0.5px;">Most Popular</div>';
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
                    html += '<div style="font-size: 42px; font-weight: 800; color: #10b981;">Free</div>';
                    html += '<div style="color: #6b7280; font-size: 13px;">Forever</div>';
                } else {
                    html += '<div style="font-size: 42px; font-weight: 800; color: #1f2937;">' +
                        plan.price_formatted + '</div>';
                    var periodText = interval === 'yearly' ? 'per year' : 'per month';
                    html += '<div style="color: #6b7280; font-size: 13px;">' + periodText + '</div>';
                    if (plan.has_savings) {
                        html += '<div style="margin-top: 6px;"><span style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); ' +
                            'color: white; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px;">' +
                            'Save ' + plan.savings_percent + '%</span></div>';
                    }
                }
                html += '</div>';

                // Limits - 4 items in 2x2 grid
                html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb;">';
                html += '<div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 10px;">' +
                    '<div style="font-size: 16px; font-weight: 700; color: #1f2937;">' + plan.tokens_limit + '</div>' +
                    '<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">AI Tokens</div></div>';
                html += '<div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 10px;">' +
                    '<div style="font-size: 16px; font-weight: 700; color: #1f2937;">' + plan.exports_limit + '</div>' +
                    '<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Exports/mo</div></div>';
                html += '<div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 10px;">' +
                    '<div style="font-size: 16px; font-weight: 700; color: #1f2937;">' + plan.reports_limit + '</div>' +
                    '<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Saved Reports</div></div>';
                html += '<div style="text-align: center; padding: 12px; background: #f9fafb; border-radius: 10px;">' +
                    '<div style="font-size: 16px; font-weight: 700; color: #1f2937;">' + plan.export_formats + '</div>' +
                    '<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Formats</div></div>';
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
                    html += '<button class="plan-select-btn" disabled style="' +
                        'display: block; width: 100%; padding: 14px; border: none; border-radius: 10px; ' +
                        'background: #e5e7eb; color: #6b7280; font-weight: 600; font-size: 14px; cursor: default;">' +
                        '<i class="fa fa-check-circle"></i> Current Plan</button>';
                } else if (plan.is_free) {
                    html += '<button class="plan-select-btn" disabled style="' +
                        'display: block; width: 100%; padding: 14px; border: 2px solid #e5e7eb; border-radius: 10px; ' +
                        'background: white; color: #374151; font-weight: 600; font-size: 14px; cursor: default;">' +
                        'Free Plan</button>';
                } else {
                    html += '<button class="plan-select-btn" data-plan-id="' + (plan.id || '') + '" ' +
                        'data-plan-name="' + plan.short_name + '" style="' +
                        'display: block; width: 100%; padding: 14px; border: none; border-radius: 10px; ' +
                        'background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; ' +
                        'font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s ease;">' +
                        '<i class="fa fa-arrow-up"></i> Upgrade to ' + plan.short_name + '</button>';
                }
                html += '</div>';

                html += '</div>'; // end plan-card
            });

            html += '</div>'; // end plans-grid
            return html;
        },

        /**
         * Initialize plans modal event handlers
         */
        initPlansModalHandlers: function() {
            // Billing toggle handler
            document.querySelectorAll('.billing-toggle-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var interval = this.getAttribute('data-interval');
                    if (interval === Subscription.currentInterval) return;

                    // Update toggle UI
                    document.querySelectorAll('.billing-toggle-btn').forEach(function(b) {
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
            document.querySelectorAll('.plan-select-btn:not([disabled])').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var planName = this.getAttribute('data-plan-name');
                    var planId = this.getAttribute('data-plan-id');
                    console.log('[Subscription] Plan button clicked:', {planName: planName, planId: planId});

                    // Open billing portal for upgrade - pass plan_id for customer creation
                    Subscription.openBillingPortalForUpgrade(planName, planId);
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
                title: 'Opening Billing Portal...',
                html: '<p>Preparing upgrade to ' + planName + '...</p>',
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
                            title: 'Redirecting...',
                            html: '<p>Opening billing portal...</p>',
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
                        Swal.fire({
                            icon: 'error',
                            title: 'Billing Portal Error',
                            text: (response && response.message) || 'Failed to open billing portal. Please try again.',
                            confirmButtonColor: '#3085d6'
                        });
                    }
                },
                fail: function(error) {
                    console.error('[Subscription] Billing portal failed:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Failed to open billing portal. Please try again.',
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
                console.error('[Subscription] No plan ID available');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Plan ID not available. Please try again.',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            // Show confirmation dialog
            Swal.fire({
                icon: 'info',
                title: 'Subscribe to ' + planName + '?',
                html: '<p>You will be redirected to complete your subscription.</p>',
                showCancelButton: true,
                confirmButtonColor: '#3498db',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-credit-card"></i> Subscribe Now',
                cancelButtonText: 'Cancel'
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
                title: 'Renew Your Subscription?',
                html: '<p>Welcome back! Renew your subscription to regain access to all premium features.</p>',
                showCancelButton: true,
                confirmButtonColor: '#27ae60',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-refresh"></i> Renew Subscription',
                cancelButtonText: 'Not Now'
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

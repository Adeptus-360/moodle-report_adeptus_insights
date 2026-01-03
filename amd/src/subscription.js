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
            console.log('[Subscription] Initializing...');
            
            // Initialize event handlers
            this.initEventHandlers();
            
            // Initialize subscription display
            this.updateSubscriptionDisplay();
        },
        
        /**
         * Initialize event handlers
         */
        initEventHandlers: function() {
            console.log('[Subscription] Setting up event handlers...');
            
            // Handle upgrade plan buttons
            $(document).on('click', '.btn-upgrade-plan', function(e) {
                e.preventDefault();
                console.log('[Subscription] Upgrade button clicked');
                Subscription.handleUpgradePlan($(this));
            });
            
            // Handle downgrade plan buttons
            $(document).on('click', '.btn-downgrade-plan', function(e) {
                e.preventDefault();
                console.log('[Subscription] Downgrade button clicked');
                Subscription.handleDowngradePlan($(this));
            });
            
            // Handle cancel subscription buttons
            $(document).on('click', '.btn-cancel-subscription, #cancel-subscription', function(e) {
                e.preventDefault();
                console.log('[Subscription] Cancel subscription button clicked');
                Subscription.handleCancelSubscription($(this));
            });
            
            // Handle billing portal access
            $(document).on('click', '.btn-billing-portal', function(e) {
                e.preventDefault();
                console.log('[Subscription] Billing portal button clicked');
                Subscription.openBillingPortal();
            });
            
            // Handle modify subscription button
            $(document).on('click', '.btn-modify-subscription, #modify-subscription', function(e) {
                e.preventDefault();
                console.log('[Subscription] Modify subscription button clicked');
                Subscription.openBillingPortal();
            });
            
            // Handle view plans button (open billing portal)
            $(document).on('click', '.btn-view-plans, #view-plans', function(e) {
                e.preventDefault();
                console.log('[Subscription] View plans button clicked');
                Subscription.openBillingPortal();
            });

            // Handle upgrade plan button (for free plan users)
            $(document).on('click', '.btn-upgrade-plan, #upgrade-plan', function(e) {
                e.preventDefault();
                console.log('[Subscription] Upgrade plan button clicked');
                Subscription.handleUpgradeFromFree();
            });
            
            // Handle select plan button (new subscriptions)
            $(document).on('click', '.select-plan-btn', function(e) {
                e.preventDefault();
                console.log('[Subscription] Select plan button clicked');
                var planId = $(this).data('plan-id');
                var planName = $(this).data('plan-name');
                Subscription.handleSelectPlan($(this), planId, planName);
            });
            
            // Handle renew subscription button
            $(document).on('click', '#renew-subscription', function(e) {
                e.preventDefault();
                console.log('[Subscription] Renew subscription button clicked');
                Subscription.handleRenewSubscription();
            });
            
            // Handle update payment button
            $(document).on('click', '#update-payment', function(e) {
                e.preventDefault();
                console.log('[Subscription] Update payment button clicked');
                Subscription.openBillingPortal();
            });
            
            console.log('[Subscription] Event handlers setup complete');
        },
        
        /**
         * Handle upgrade plan
         */
        handleUpgradePlan: function($button) {
            var planId = $button.data('plan-id');
            var planName = $button.data('plan-name');
            
            console.log('[Subscription] handleUpgradePlan called with planId:', planId, 'planName:', planName);
            
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
                    console.log('[Subscription] User confirmed upgrade to plan:', planId);
                    Subscription.createBillingPortalSession(planId, 'upgrade');
                } else {
                    console.log('[Subscription] User cancelled upgrade');
                }
            });
        },
        
        /**
         * Handle downgrade plan
         */
        handleDowngradePlan: function($button) {
            var planId = $button.data('plan-id');
            var planName = $button.data('plan-name');
            
            console.log('[Subscription] handleDowngradePlan called with planId:', planId, 'planName:', planName);
            
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
                    console.log('[Subscription] User confirmed downgrade to plan:', planId);
                    Subscription.createBillingPortalSession(planId, 'downgrade');
                } else {
                    console.log('[Subscription] User cancelled downgrade');
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
                    console.log('[Subscription] User confirmed cancellation');
                    Subscription.createBillingPortalSession(null, 'cancel');
                } else {
                    console.log('[Subscription] User kept subscription');
                }
            });
        },
        
        /**
         * Open billing portal
         */
        openBillingPortal: function() {
            console.log('[Subscription] Opening billing portal...');
            Subscription.createBillingPortalSession(Subscription.subscriptionData.plan_id, 'modify');
        },
        
        /**
         * Create billing portal session
         */
        createBillingPortalSession: function(planId, action) {
            console.log('[Subscription] Creating billing portal session...', {planId, action});
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
            
            console.log('[Subscription] Sending data:', data);
            
            Ajax.call([{
                methodname: 'report_adeptus_insights_create_billing_portal_session',
                args: data,
                done: function(response) {
                    console.log('[Subscription] AJAX response:', response);
                    console.log('[Subscription] Response success:', response?.success);
                    console.log('[Subscription] Response portal_url:', response?.portal_url);
                    
                    if (response && response.success && response.portal_url) {
                        console.log('[Subscription] Redirecting to portal URL:', response.portal_url);
                        
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
                        console.log('[Subscription] Portal creation failed:', response);
                        Swal.fire({
                            icon: 'error',
                            title: 'Portal Creation Failed',
                            text: response?.message || 'Failed to create billing portal session. Please try again.',
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
                        console.log('[Subscription] Full AJAX response:', response);
                        console.log('[Subscription] Response data keys:', Object.keys(response.data || {}));
                        console.log('[Subscription] Plan ID in data:', response.data.plan_id);
                        console.log('[Subscription] Subscription data:', response.data);
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
         * Handle upgrade from free plan
         */
        handleUpgradeFromFree: function() {
            console.log('[Subscription] handleUpgradeFromFree called');

            Swal.fire({
                icon: 'info',
                title: 'Upgrade Your Plan',
                html: '<p>You will be redirected to our billing portal where you can choose a premium plan with more features.</p>',
                showCancelButton: true,
                confirmButtonColor: '#27ae60',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: '<i class="fa fa-arrow-up"></i> View Plans & Upgrade',
                cancelButtonText: 'Not Now'
            }).then(function(result) {
                if (result.isConfirmed) {
                    console.log('[Subscription] User confirmed upgrade from free');
                    Subscription.createBillingPortalSession(null, 'upgrade');
                } else {
                    console.log('[Subscription] User cancelled upgrade');
                }
            });
        },
        
        /**
         * Handle select plan (for new subscriptions)
         */
        handleSelectPlan: function($button, planId, planName) {
            console.log('[Subscription] handleSelectPlan called with planId:', planId, 'planName:', planName);
            
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
                    console.log('[Subscription] User confirmed subscription to plan:', planId);
                    Subscription.createBillingPortalSession(planId, 'subscribe');
                } else {
                    console.log('[Subscription] User cancelled subscription');
                }
            });
        },
        
        /**
         * Handle renew subscription
         */
        handleRenewSubscription: function() {
            console.log('[Subscription] handleRenewSubscription called');
            
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
                    console.log('[Subscription] User confirmed renewal');
                    // Open billing portal for renewal
                    Subscription.createBillingPortalSession(null, 'renew');
                } else {
                    console.log('[Subscription] User cancelled renewal');
                }
            });
        }
    };

    return Subscription;
});

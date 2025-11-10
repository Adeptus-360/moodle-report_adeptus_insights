// jshint ignore:start
define(['jquery', 'core/notification'], function($, Notification) {
    'use strict';

    /**
     * Read-only mode functionality for Adeptus Insights plugin
     */
    var ReadonlyMode = {
        
        /**
         * Initialize read-only mode
         */
        init: function() {
            console.log('[Readonly Mode] Initializing...');
            
            // Listen for read-only mode enable event
            $(document).on('adeptus:enableReadOnly', function() {
                ReadonlyMode.enable();
            });
            
            // Listen for read-only mode disable event
            $(document).on('adeptus:disableReadOnly', function() {
                ReadonlyMode.disable();
            });
            
            // Check if we should start in read-only mode
            if (window.adeptusAuthData) {
                ReadonlyMode.checkInitialState();
            }
        },
        
        /**
         * Check initial state and enable read-only if needed
         */
        checkInitialState: function() {
            var authData = window.adeptusAuthData;
            var shouldEnableReadOnly = !authData || 
                                     !authData.user_authorized || 
                                     !authData.has_api_key || 
                                     (authData.auth_errors && authData.auth_errors > 0);
            
            if (shouldEnableReadOnly) {
                console.log('[Readonly Mode] Enabling readonly mode');
                this.enable();
            }
        },
        
        /**
         * Enable read-only mode
         */
        enable: function() {
            console.log('[Readonly Mode] Enabling readonly mode');
            
            // Add read-only class to body
            $('body').addClass('adeptus-readonly-mode');
            
            // Disable all interactive elements
            this.disableInteractiveElements();
            
            // Show read-only notification
            this.showReadOnlyNotification();
            
            // Disable forms
            this.disableForms();
            
            // Disable buttons
            this.disableButtons();
            
            // Trigger custom event
            $(document).trigger('adeptus:readOnlyEnabled');
        },
        
        /**
         * Disable read-only mode
         */
        disable: function() {
            console.log('[Readonly Mode] Disabling readonly mode');
            
            // Remove read-only class from body
            $('body').removeClass('adeptus-readonly-mode');
            
            // Re-enable all interactive elements
            this.enableInteractiveElements();
            
            // Hide read-only notification
            this.hideReadOnlyNotification();
            
            // Re-enable forms
            this.enableForms();
            
            // Re-enable buttons
            this.enableButtons();
            
            // Trigger custom event
            $(document).trigger('adeptus:readOnlyDisabled');
        },
        
        /**
         * Disable interactive elements
         */
        disableInteractiveElements: function() {
            // Disable inputs, textareas, selects
            $('input, textarea, select').prop('disabled', true);
            
            // Disable links that are not navigation
            $('a:not(.nav-link):not(.breadcrumb-item a)').addClass('disabled').css('pointer-events', 'none');
            
            // Disable buttons
            $('button:not(.nav-toggle)').prop('disabled', true);
            
            // Disable form submissions
            $('form').on('submit.adeptus-readonly', function(e) {
                e.preventDefault();
                return false;
            });
        },
        
        /**
         * Enable interactive elements
         */
        enableInteractiveElements: function() {
            // Re-enable inputs, textareas, selects
            $('input, textarea, select').prop('disabled', false);
            
            // Re-enable links
            $('a.disabled').removeClass('disabled').css('pointer-events', '');
            
            // Re-enable buttons
            $('button').prop('disabled', false);
            
            // Re-enable form submissions
            $('form').off('submit.adeptus-readonly');
        },
        
        /**
         * Disable forms
         */
        disableForms: function() {
            $('form').each(function() {
                var $form = $(this);
                if (!$form.data('adeptus-original-action')) {
                    $form.data('adeptus-original-action', $form.attr('action'));
                    $form.attr('action', 'javascript:void(0)');
                }
            });
        },
        
        /**
         * Enable forms
         */
        enableForms: function() {
            $('form').each(function() {
                var $form = $(this);
                var originalAction = $form.data('adeptus-original-action');
                if (originalAction) {
                    $form.attr('action', originalAction);
                    $form.removeData('adeptus-original-action');
                }
            });
        },
        
        /**
         * Disable buttons
         */
        disableButtons: function() {
            $('button:not(.nav-toggle)').each(function() {
                var $btn = $(this);
                if (!$btn.data('adeptus-original-text')) {
                    $btn.data('adeptus-original-text', $btn.text());
                    $btn.text('Read-Only Mode');
                    $btn.addClass('btn-secondary').removeClass('btn-primary btn-success btn-danger btn-warning btn-info');
                }
            });
        },
        
        /**
         * Enable buttons
         */
        enableButtons: function() {
            $('button').each(function() {
                var $btn = $(this);
                var originalText = $btn.data('adeptus-original-text');
                if (originalText) {
                    $btn.text(originalText);
                    $btn.removeData('adeptus-original-text');
                    $btn.removeClass('btn-secondary');
                }
            });
        },
        
        /**
         * Show read-only notification
         */
        showReadOnlyNotification: function() {
            if (Notification) {
                this.readOnlyNotification = Notification.addNotification({
                    message: 'Plugin is in read-only mode due to authentication issues',
                    type: 'warning',
                    closebutton: false
                });
            } else {
                // Fallback to alert if Notification is not available
                if (!this.readOnlyAlertShown) {
                    alert('Plugin is in read-only mode due to authentication issues');
                    this.readOnlyAlertShown = true;
                }
            }
        },
        
        /**
         * Hide read-only notification
         */
        hideReadOnlyNotification: function() {
            if (this.readOnlyNotification && Notification) {
                Notification.closeNotification(this.readOnlyNotification);
                this.readOnlyNotification = null;
            }
            this.readOnlyAlertShown = false;
        },
        
        /**
         * Check if read-only mode is active
         */
        isEnabled: function() {
            return $('body').hasClass('adeptus-readonly-mode');
        }
    };

    return ReadonlyMode;
});

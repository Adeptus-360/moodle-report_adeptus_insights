define(['jquery', 'core/ajax', 'core/notification', 'core/chartjs', 'core/templates', 'report_adeptus_insights/auth_utils'], function ($, Ajax, Notification, Chart, Templates, AuthUtils) {
    var Swal = window.Swal;

    var assistant = {
        currentChatId: 0,
        mcqQueue: [],
        reportsDataTable: null,
        cachedReports: [],
        _initCalled: false,
        isCreditLimitExceeded: false,
        init: function (authenticated) {
            if (this._initCalled) return;
            this._initCalled = true;
            console.log('[AI Assistant] init called, authenticated:', authenticated);
            this.currentChatId = 0;
            console.log('[AI Assistant] currentChatId set to', this.currentChatId);
            $('.container-fluid').hide();
            console.log('[AI Assistant] .container-fluid hidden');

            // Initialize authentication system
            try {
                // Check if user is authenticated
                if (AuthUtils.isAuthenticated()) {
                    console.log('[AI Assistant] User authenticated, proceeding with initialization');
                    this.setUserName(AuthUtils.getAuthStatus()?.user?.name || "User");
                    this.updateSubscriptionInfo();
                    this.setupEventListeners();
                    this.loadChatHistory();
                    this.loadReportsHistory();
                    $('.container-fluid').fadeIn(200);
                } else {
                    console.log('[AI Assistant] User not authenticated, checking auth status');
                    // Try to refresh authentication
                    AuthUtils.refreshAuthStatus();
                    
                    // Check authentication status after refresh
                    setTimeout(() => {
                        if (AuthUtils.isAuthenticated()) {
                            this.setUserName(AuthUtils.getAuthStatus()?.user?.name || "User");
                            this.updateSubscriptionInfo();
                            this.setupEventListeners();
                            this.loadChatHistory();
                            this.loadReportsHistory();
                            $('.container-fluid').fadeIn(200);
                        } else {
                            // Show read-only mode or error message
                            this.showAuthenticationError();
                        }
                    }, 500);
                }
            } catch (error) {
                console.error('[AI Assistant] Failed to initialize authentication:', error);
                // Fallback to basic initialization
                this.setupEventListeners();
                $('.container-fluid').fadeIn(200);
            }
        },

        showAuthenticationError: function() {
            // Show authentication error message
            console.log('[AI Assistant] Showing authentication error');
            $('.container-fluid').html(`
                <div class="alert alert-danger">
                    <h4>Authentication Required</h4>
                    <p>You need to be authenticated to use the AI Assistant. Please contact your administrator for assistance.</p>
                    <button class="btn btn-primary" onclick="location.reload()">Refresh Page</button>
                </div>
            `);
        },

        setupEventListeners: function () {
            const self = this;

            // Send message
            $('#send-button').on('click', () => this.sendMessage());
            $('#message-input').on('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            // Chart type change
            $('#chart-type').on('change', () => this.updateChart());

            // Auto-resize textarea
            $('#message-input').on('input', function () {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            $('#create-new-chat').on('click', () => this.createNewChat());
        },

        // Flag to prevent double message sending
        isSending: false,

        renderMCQ: function(mcq) {
            this.currentMCQ = mcq;
            var c = $('#mcq-container').empty();
            c.append(`<p><strong>${mcq.question}</strong></p>`);
            mcq.options.forEach(opt => {
                c.append(`
                  <div class="form-check">
                    <input class="form-check-input" type="radio"
                           name="mcq-option" id="mcq-${opt.key}"
                           value="${opt.key}">
                    <label class="form-check-label" for="mcq-${opt.key}">
                      ${opt.key}. ${opt.label}
                    </label>
                  </div>`);
            });
            c.append(`<button id="mcq-cancel" class="btn btn-link">Cancel</button>`);
        },

        clearMCQ: function() {
            this.currentMCQ = null;
            $('#mcq-container').empty().hide();
            // re-enable text input and send button
            $('#message-input').prop('disabled', false);
            $('#send-button').prop('disabled', false);
        },

        /**
         * Extract MCQ data from message text (handles both JSON array and individual JSON objects)
         */
        extractMCQFromText: function(text) {
            if (!text || typeof text !== 'string') return null;
            
            try {
                // Try to parse as JSON array first (e.g., ```json [...] ```)
                const jsonArrayMatch = text.match(/```json\s*(\[[\s\S]*?\])\s*```/);
                if (jsonArrayMatch) {
                    const questions = JSON.parse(jsonArrayMatch[1]);
                    if (Array.isArray(questions) && questions.length > 0 && questions[0].type === 'mcq') {
                        // Check if there's a selected answer in the next message (if this is history)
                        return { questions: questions, selectedAnswer: null };
                    }
                }
                
                // Try to extract individual JSON objects (fallback for older format)
                const jsonMatches = text.match(/\{[\s\S]*?\}/g) || [];
                const questions = [];
                jsonMatches.forEach(jsonStr => {
                    try {
                        const obj = JSON.parse(jsonStr);
                        if (obj.type === 'mcq' && Array.isArray(obj.options)) {
                            questions.push(obj);
                        }
                    } catch (e) {
                        // Skip invalid JSON
                    }
                });
                
                if (questions.length > 0) {
                    return { questions: questions, selectedAnswer: null };
                }
            } catch (e) {
                console.log('Error extracting MCQ from text:', e);
            }
            
            return null;
        },

        /**
         * Render MCQ history view (disabled, showing selected answer if available)
         */
        renderMCQHistory: function(questions, selectedAnswer = null) {
            let html = '<div class="mcq-history-container" style="display:flex; flex-direction:column; gap:15px;">';
            
            questions.forEach((mcq, idx) => {
                // Each MCQ gets its own separate container with border and background
                html += `
                    <div class="mcq-history-item" style="background:#f8f9fa; padding:15px; border-radius:8px; border:1px solid #dee2e6; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                        <p style="font-weight:600; margin-bottom:12px; color:#333; font-size:14px;">
                            <i class="fa fa-question-circle" style="color:#007bff; margin-right:8px;"></i>${mcq.question}
                        </p>
                        <div class="mcq-options" style="margin-left:24px;">
                `;
                
                mcq.options.forEach((option, optIdx) => {
                    const optionText = typeof option === 'string' ? option : option.label || option;
                    const isSelected = selectedAnswer && (selectedAnswer === option || selectedAnswer === optionText || selectedAnswer.includes(optionText));
                    const selectedStyle = isSelected ? 'background:#e3f0ff; border:2px solid #007bff; font-weight:600;' : 'border:1px solid #ced4da;';
                    const selectedIcon = isSelected ? '<i class="fa fa-check-circle" style="color:#28a745; margin-right:5px;"></i>' : '';
                    
                    html += `
                        <div class="form-check" style="padding:10px 14px; border-radius:6px; margin-bottom:8px; ${selectedStyle} transition:all 0.2s;">
                            <input class="form-check-input" type="radio" name="mcq-${idx}" disabled ${isSelected ? 'checked' : ''} style="margin-top:0.3em;">
                            <label class="form-check-label" style="color:#555; cursor:not-allowed; margin-left:5px;">
                                ${selectedIcon}${optionText}
                            </label>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                        <p style="margin-top:12px; margin-bottom:0; font-size:11px; color:#6c757d; font-style:italic;">
                            <i class="fa fa-info-circle" style="margin-right:4px;"></i>Previous question from chat history
                        </p>
                    </div>
                `;
            });
            
            html += '</div>';
            
            return html;
        },

        checkAuth: function () {
            if (!AuthUtils.isAuthenticated()) {
                console.log('[AI Assistant] User not authenticated');
                this.showAuthenticationError();
                return false;
            } else {
                this.loadChatHistory();
                return true;
            }
        },

        handleResponse: function (response) {
            // Route responses based on type
            if (response.error) {
                // Handle credit limit errors specifically
                if (response.error_type === 'credit_limit') {
                    this.handleCreditLimitError(response.message);
                    return;
                }
                // error bubble from backend
                this.addMessage(response.message, 'error');
                // Show resend icon on the last user message
                this.showResendIconOnLastUserMessage();
            } else if (response.type === 'mcq') {
                // Add MCQ as AI message first
                if (response.questions && response.questions.length > 0) {
                    const firstQuestion = response.questions[0];
                    const mcqText = `Question: ${firstQuestion.question}\nOptions: ${firstQuestion.options.join(', ')}`;
                    this.addMessage(mcqText, 'ai', false, null, null, null, response.credit_info);
                }
                // show multiple-choice question
                this.enqueueMCQs(response.questions);
                return;
            } else if (response.type === 'sql') {
                // Add SQL as AI message first with report link
                this.addMessage(response.report.description, 'ai', true, response.report, null, null, response.credit_info);
                this.updateChatHistoryBadge(this.currentChatId);
                // Update cache and UI directly
                this.cachedReports.unshift(response.report);
                this.updateReportsHistory(this.cachedReports);
                // Reinitialize DataTable for updated history
                if (this.reportsDataTable) {
                    this.reportsDataTable.destroy();
                    this.reportsDataTable = null;
                }
                
                // Wait for DOM to be updated before reinitializing DataTable
                setTimeout(() => {
                    try {
                        const tableElement = document.getElementById('reports-history-table');
                        if (tableElement) {
                            const thead = tableElement.querySelector('thead');
                            const tbody = tableElement.querySelector('tbody');
                            
                            if (thead && tbody && thead.rows.length > 0 && thead.rows[0].cells.length > 0) {
                                const headerCells = thead.rows[0].cells.length;
                                const dataRows = tbody.rows;
                                let dataCells = 0;
                                
                                if (dataRows.length > 0) {
                                    dataCells = dataRows[0].cells.length;
                                }
                                
                                if (headerCells === dataCells && headerCells > 0) {
                                    // Only initialize DataTable if we have actual data rows (not just empty state)
                                    if (dataRows.length > 0 && !dataRows[0].cells[0].textContent.includes('No reports')) {
                this.reportsDataTable = new simpleDatatables.DataTable("#reports-history-table", {
                    searchable: true,
                    fixedHeight: false,
                    perPage: 10
                });
                                    }
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Error reinitializing DataTable:', error);
                    }
                }, 100);
                // Show SweetAlert loader for report generation
                Swal.fire({
                    title: 'Generating Report',
                    html: `
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status">
                            </div>
                            <p>Please wait while we generate your report...</p>
                        </div>
                    `,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    timer: 2000,
                    timerProgressBar: true
                }).then(() => {
                    this.switchToReportsTab();
                    this.displayCurrentReport(response.report);
                    // Automatically execute the report
                    this.executeReport(response.report.slug);
                });
                return;
            } else {
                // normal AI or SQL response
                this.addMessage(response.message, 'ai', false, null, null, null, response.credit_info);
            }

            // Update report data if available
            if (response.data) {
                this.updateReportData(response.data);
            }

            // Update visualizations if available
            if (response.visualizations) {
                this.updateVisualizations(response.visualizations);
            }
            
            // Show immediate credit usage feedback if available
            if (response.credit_info) {
                this.showCreditUsageFeedback(response.credit_info);
            }
            
            this.scrollToBottom();
        },

        /**
         * Show immediate credit usage feedback
         */
        showCreditUsageFeedback: function(creditInfo) {
            if (!creditInfo) return;
            
            // Create a temporary notification for credit usage
            const notification = $(`
                <div class="credit-usage-notification" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #28a745;
                    color: white;
                    padding: 12px 20px;
                    border-radius: 6px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 9999;
                    font-size: 14px;
                    font-weight: 500;
                    opacity: 0;
                    transform: translateX(100%);
                    transition: all 0.3s ease;
                ">
                    <i class="fa fa-coins me-2"></i>
                    <span class="credit-text">Credits used: ${creditInfo.credits_charged || 0} (${creditInfo.credit_type || 'basic'})</span>
                </div>
            `);
            
            // Add to body
            $('body').append(notification);
            
            // Animate in
            setTimeout(() => {
                notification.css({
                    'opacity': '1',
                    'transform': 'translateX(0)'
                });
            }, 100);
            
            // Animate out and remove after 3 seconds
            setTimeout(() => {
                notification.css({
                    'opacity': '0',
                    'transform': 'translateX(100%)'
                });
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
            
            // Also update the subscription info immediately
            this.updateSubscriptionInfoWithCreditUsage(creditInfo);
        },

        /**
         * Update subscription info with immediate credit usage
         */
        updateSubscriptionInfoWithCreditUsage: function(creditInfo) {
            const authStatus = AuthUtils.getAuthStatus();
            if (!authStatus || !authStatus.subscription) return;
            
            const subscription = authStatus.subscription;
            const creditsUsed = creditInfo.credits_charged || 0;
            
            // Update the credit counters immediately
            if (creditInfo.credit_type === 'premium') {
                subscription.premium_credits_used_this_month = (subscription.premium_credits_used_this_month || 0) + creditsUsed;
            } else {
                subscription.basic_credits_used_this_month = (subscription.basic_credits_used_this_month || 0) + creditsUsed;
            }
            
            // Update total AI credits
            subscription.ai_credits_used_this_month = (subscription.ai_credits_used_this_month || 0) + creditsUsed;
            
            // Animate the counter updates
            this.animateCreditCounterUpdate(creditInfo.credit_type, creditsUsed);
            
            // Update the display
            this.updateSubscriptionInfo();
        },

        /**
         * Animate credit counter updates
         */
        animateCreditCounterUpdate: function(creditType, creditsUsed) {
            const counterClass = creditType === 'premium' ? '.premium-credits-counter' : '.basic-credits-counter';
            const $counter = $(counterClass);
            
            if ($counter.length) {
                // Add highlight animation
                $counter.addClass('credit-update-highlight');
                
                // Remove highlight after animation
                setTimeout(() => {
                    $counter.removeClass('credit-update-highlight');
                }, 1000);
            }
            
            // Also animate total credits counter
            const $totalCounter = $('.total-credits-counter');
            if ($totalCounter.length) {
                $totalCounter.addClass('credit-update-highlight');
                setTimeout(() => {
                    $totalCounter.removeClass('credit-update-highlight');
                }, 1000);
            }
        },

        addMessage: function (text, type, isReportLink = false, reportData = null, messageId = null, timestamp = null, creditInfo = null) {
            const template = document.getElementById('message-template');
            const frag = template.content.cloneNode(true);
            const messageEl = frag.querySelector('.message');
            messageEl.classList.add(type + '-message');
            if (messageId) {
                messageEl.setAttribute('data-message-id', messageId);
            }
            
            // Check if message contains MCQ JSON
            const mcqData = this.extractMCQFromText(text);
            
            if (mcqData && mcqData.questions.length > 0) {
                // Render as disabled MCQ view
                const mcqHtml = this.renderMCQHistory(mcqData.questions, mcqData.selectedAnswer);
                frag.querySelector('.message-text').innerHTML = mcqHtml;
            } else if (isReportLink && reportData) {
                // Create a clickable report link
                frag.querySelector('.message-text').innerHTML = `
                    <a href="javascript:void(0)" 
                       class="report-link" 
                       data-report-slug="${reportData.slug}"
                       style="color: #007bff; text-decoration: none; cursor:pointer; padding:4px 8px; border:1px solid #dee2e6; border-radius:4px; background:#f8f9fa; display:inline-block; transition:all 0.2s;">
                        📊 ${text}
                    </a>
                `;
                const self = this;
                setTimeout(() => {
                    const link = messageEl.querySelector('.report-link');
                    link.onclick = function() { self.openReportFromLink(link.getAttribute('data-report-slug')); };
                    link.onmouseenter = function() { $(this).css({'background-color':'#e9ecef','border-color':'#adb5bd','transform':'translateY(-1px)'}); };
                    link.onmouseleave = function() { $(this).css({'background-color':'#f8f9fa','border-color':'#dee2e6','transform':'translateY(0)'}); };
                }, 0);
            } else {
            frag.querySelector('.message-text').textContent = text;
            }
            
            // Add credit information for AI messages
            if (type === 'ai' && creditInfo) {
                this.addCreditInfoToMessage(frag, creditInfo);
            }
            
            const timeEl = frag.querySelector('.message-time');
            timeEl.textContent = this.formatTimestamp(timestamp || new Date().toISOString());
            $('#chat-container').append(frag);
            this.scrollToBottom();
            return $(messageEl);
        },

        addCreditInfoToMessage: function (frag, creditInfo) {
            const messageEl = frag.querySelector('.message');
            const creditBadge = document.createElement('div');
            creditBadge.className = 'credit-info-badge';
            creditBadge.style.cssText = `
                display: inline-block;
                margin-left: 8px;
                padding: 2px 6px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            `;
            
            // Set badge color and text based on credit type
            if (creditInfo.credit_type === 'premium') {
                creditBadge.style.backgroundColor = '#6f42c1';
                creditBadge.style.color = 'white';
                creditBadge.textContent = 'Premium';
            } else {
                creditBadge.style.backgroundColor = '#28a745';
                creditBadge.style.color = 'white';
                creditBadge.textContent = 'Basic';
            }
            
            // Add tooltip with detailed information
            creditBadge.title = `${creditInfo.credit_type.toUpperCase()} Response\nTokens: ${creditInfo.tokens_used}\nCredits: ${creditInfo.credits_charged}\nProvider: ${creditInfo.provider}`;
            
            // Insert after message text
            const messageText = messageEl.querySelector('.message-text');
            messageText.appendChild(creditBadge);
        },

        handleCreditLimitError: function (message, creditData = null) {
            // Update subscription data with latest credit information from API response
            if (creditData && creditData.summary) {
                this.updateSubscriptionDataFromCreditResponse(creditData);
            }
            
            // Show persistent credit limit message at the top
            this.showPersistentCreditLimitMessage(message);
            
            // Show user-friendly credit limit error in chat
            this.addMessage(message, 'error');
            
            // Show upgrade prompt
            Swal.fire({
                icon: 'warning',
                title: 'Credit Limit Reached',
                html: `
                    <div class="text-center">
                        <p>${message}</p>
                        <p class="text-muted">Your monthly credit allowance has been used up.</p>
                        <p class="text-muted">Credits reset on the 1st of each month.</p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'View Usage',
                cancelButtonText: 'Close',
                confirmButtonColor: '#007bff'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.showCreditUsageModal();
                }
            });
        },

        /**
         * Update subscription data from credit limit error response
         */
        updateSubscriptionDataFromCreditResponse: function(creditData) {
            const authStatus = AuthUtils.getAuthStatus();
            if (!authStatus || !authStatus.subscription) return;
            
            const subscription = authStatus.subscription;
            const summary = creditData.summary;
            
            // Update credit usage with actual data from API
            if (summary.basic) {
                subscription.basic_credits_used_this_month = summary.basic.used;
                subscription.plan_basic_credits_limit = summary.basic.available;
            }
            
            if (summary.premium) {
                subscription.premium_credits_used_this_month = summary.premium.used;
                subscription.plan_premium_credits_limit = summary.premium.available;
            }
            
            if (summary.total) {
                subscription.ai_credits_used_this_month = summary.total.used;
                subscription.plan_ai_credits_limit = summary.total.available;
            }
            
            // Force refresh of auth status to persist the changes
            AuthUtils.setAuthStatus(authStatus);
            
            // Update the display immediately
            this.updateSubscriptionInfo();
        },

        handleTimeoutError: function (message) {
            // Show user-friendly timeout error
            this.addMessage(message, 'error');
            
            // Show retry prompt
            Swal.fire({
                icon: 'info',
                title: 'AI Service Busy',
                html: `
                    <div class="text-center">
                        <p>${message}</p>
                        <p class="text-muted">The AI service is experiencing high demand. Please try again in a moment.</p>                                                                                       
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Try Again',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Focus on the input field for retry
                    $('#message-input').focus();
                }
            });
        },

        showPersistentCreditLimitMessage: function (message) {
            // Remove any existing credit limit message
            $('.credit-limit-alert').remove();
            
            // Create persistent credit limit message
            const alertHtml = `
                <div class="alert alert-danger credit-limit-alert" role="alert" style="margin: 10px 0; border-left: 4px solid #dc3545; background-color: #f8d7da; border-color: #f5c6cb;">
                    <div class="d-flex align-items-center">
                        
                        <div class="flex-grow-1">
                            <strong class="text-danger">Credit Limit Reached</strong><br>
                            <small class="text-muted">${message}</small>
                        </div>
                        <div class="ms-3">
                            <button type="button" class="btn btn-sm btn-outline-danger me-2" onclick="window.assistant.showCreditUsageModal()">
                                <i class="fas fa-chart-bar me-1"></i> View Usage
                            </button>
                           
                        </div>
                    </div>
                </div>
            `;
            
            // Insert at the top of the subscription header area
            // Insert before the second occurrence of class 'main-inner'
            var $mainInners = $('.main-inner');
            if ($mainInners.length >= 2) {
                $mainInners.eq(1).before(alertHtml);
            } else if ($mainInners.length === 1) {
                $mainInners.eq(0).before(alertHtml);
            } else {
                // Fallback: append to body if .main-inner not found
                $('body').prepend(alertHtml);
            }

        },

        hidePersistentCreditLimitMessage: function () {
            $('.credit-limit-alert').fadeOut(300, function() {
                $(this).remove();
            });
        },

        showCreditUsageModal: function () {
            // Show loading modal first
            Swal.fire({
                title: 'Loading Usage Data...',
                text: 'Please wait while we fetch your usage information',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Fetch detailed usage data
            this.ajaxWithAuth({
                url: 'https://ai-backend.stagingwithswift.com/api/chat/credits/detailed-usage',
                method: 'GET',
                success: (response) => {
                    if (response.success) {
                        this.displayDetailedUsageModal(response.data);
                    } else {
                        Swal.fire('Error', 'Failed to load usage information', 'error');
                    }
                },
                error: () => {
                    Swal.fire('Error', 'Failed to load usage information', 'error');
                }
            });
        },

        displayDetailedUsageModal: function (data) {
            const summary = data.summary;
            const dailyUsage = data.daily_usage;
            const userBreakdown = data.user_breakdown;
            const usage = data.usage;
            const pagination = data.pagination;
            const filters = data.filters;

            console.log('displayDetailedUsageModal', data);

            // Create the comprehensive modal HTML with optimized styling
            const modalHtml = `
                <div class="detailed-usage-modal" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                    <!-- Compact Header with improved filters -->
                    <div class="usage-header mb-3 assistant-header" style=" padding: 16px; border-radius: 10px; color: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0" style="color: white; font-weight: 600;">
                            <i class="fas fa-chart-line me-2"></i> Usage Analytics Dashboard
                            </h5>
                        </div>
                        </div>
                        
                    <!-- Compact Summary Cards -->
                    <div class="row mb-3 g-2" style="height: 109px;">
                        <div class="col-md-3" style="height: 109px;">
                            <div class="card border-0" style="height: 109px;background:linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); color: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);">
                                <div class="card-body text-center p-3">
                                
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        
                                        <div>
                                            <h6 class="card-title mb-0 small" style="font-weight: 600; opacity: 0.9;">Total Credits</h6>
                                            <div class="row" style="display: inline-flexalign-content: space-between;align-items: center;justify-content: center;">
                                                <i class="fas fa-coins fa-lg me-2" style="opacity: 0.8;"></i>&nbsp;<h4 class="mb-0" style="font-weight: 700; font-size: 1.8rem;">${summary.total_credits_used}</h4>
                                        </div>
                                            
                                    </div>
                                        
                                </div>
                                    <small style="opacity: 0.8; font-size: 0.75rem;">Used This Period</small>
                            </div>
                                        </div>
                                        </div>
                        <div class="col-md-3" style="height: 109px;">
                            <div class="card border-0" style="height: 109px;background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(17, 153, 142, 0.2);">
                                <div class="card-body text-center p-3">
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        
                                        <div>
                                            <h6 class="card-title mb-0 small" style="font-weight: 600; opacity: 0.9;">Total Tokens</h6>
                                            <div class="row" style="display: inline-flexalign-content: space-between;align-items: center;justify-content: center;">
                                                <i class="fas fa-code fa-lg me-2" style="opacity: 0.8;"></i>&nbsp;<h4 class="mb-0" style="font-weight: 700; font-size: 1.8rem;">${summary.total_tokens_used.toLocaleString()}</h4>
                                        </div>
                                    </div>
                                </div>
                                    <small style="opacity: 0.8; font-size: 0.75rem;">AI Tokens Processed</small>
                            </div>
                        </div>
                        </div>
                        <div class="col-md-3" style="height: 109px;">
                            <div class="card border-0" style="height: 109px;background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(240, 147, 251, 0.2);">
                                <div class="card-body text-center p-3">
                                    <div class="d-flex align-items-center justify-content-center mb-2">
                                        
                                        <div>
                                             <h6 class="card-title mb-0 small" style="font-weight: 600; opacity: 0.9;">Total Requests</h6>
                                            <div class="row" style="display: inline-flexalign-content: space-between;align-items: center;justify-content: center;">
                                                <i class="fas fa-paper-plane fa-lg me-2" style="opacity: 0.8;"></i>&nbsp;<h4 class="mb-0" style="font-weight: 700; font-size: 1.8rem;">${summary.total_requests}</h4>
                                        </div>
                                    </div>
                                    </div>
                                    <small style="opacity: 0.8; font-size: 0.75rem;">Messages Processed</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" style="height: 109px;">
                            <div class="card border-0" style="height: 109px;background: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%); color: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(255, 107, 107, 0.2);">
                                <div class="card-body text-center p-3">
                                    <div class="d-flex align-items-center justify-content-center mb-2">

                                        <div>
                                            <h6 class="card-title mb-0 small" style="font-weight: 600; opacity: 0.9;">Today's Credits</h6>
                                            <div class="row" style="display: inline-flexalign-content: space-between;align-items: center;justify-content: center;">
                                                <i class="fas fa-calendar-day fa-lg me-2" style="opacity: 0.8;"></i>&nbsp;<h4 class="mb-0" style="font-weight: 700; font-size: 1.8rem;">${summary.today_credits_used || 0}</h4>
                                        </div>
                                        </div>
                                    </div>
                                    <small style="opacity: 0.8; font-size: 0.75rem;">Credits Used Today</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Compact Data Table -->
                    <div class="card border-0" style="border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                        <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px 12px 0 0; border: none; padding: 12px 16px;">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0" style="font-weight: 600; color: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);">
                                    Detailed Usage History
                                </h6>
                                <span class="badge bg-primary" style="font-size: 0.7rem; padding: 4px 8px; border-radius: 12px;color: white; background:linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);">
                                    ${usage.length} of ${pagination.total} records
                                </span>
                        </div>
                    </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="usage-datatable" class="table table-hover mb-0" style="font-size: 0.9rem;">
                                    <thead style="background:linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); color: white;">
                                        <tr>
                                            <th style="border: none; padding: 15px 12px; font-weight: 600; text-align: center;">Date</th>
                                            <th style="border: none; padding: 15px 12px; font-weight: 600; text-align: center;">User</th>
                                            <th style="border: none; padding: 15px 12px; font-weight: 600; text-align: center;">Type</th>
                                            <th style="border: none; padding: 15px 12px; font-weight: 600; text-align: center;">Credits</th>
                                            <th style="border: none; padding: 15px 12px; font-weight: 600; text-align: center;">Tokens</th>
                                            <th style="border: none; padding: 15px 12px; font-weight: 600; text-align: center;">Message Preview</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${usage.map(item => `
                                            <tr style="border-bottom: 1px solid #f8f9fa;">
                                                <td style="padding: 12px; vertical-align: middle; font-weight: 500;">
                                                    ${(() => {
                                                        const d = new Date(item.created_at);
                                                        const day = d.getDate();
                                                        const month = d.toLocaleString('en-US', { month: 'short' });
                                                        const year = d.getFullYear();
                                                        const hours = d.getHours().toString().padStart(2, '0');
                                                        const minutes = d.getMinutes().toString().padStart(2, '0');
                                                        return `${day} ${month} ${year} - ${hours}:${minutes}`;
                                                    })()}
                                                </td>
                                                <td style="padding: 12px; vertical-align: middle;">
                                                    <span class="badge bg-light text-dark" style="border-radius: 20px; padding: 4px 8px;">
                                                        ${item.username || 'N/A'}
                                                    </span>
                                                </td>
                                                <td style="padding: 12px; vertical-align: middle;">
                                                    <span class="badge ${item.credit_type === 'premium' ? 'bg-primary' : 'bg-success'}" style="border-radius: 20px; padding: 6px 12px; font-weight: 500;">
                                                        <i class="fas ${item.credit_type === 'premium' ? 'fa-crown' : 'fa-layer-group'} me-1"></i>
                                                        ${item.credit_type}
                                                    </span>
                                                </td>
                                                <td style="padding: 12px; vertical-align: middle; font-weight: 600; color: #667eea;">${item.credits_charged}</td>
                                                <td style="padding: 12px; vertical-align: middle; font-weight: 500;">${item.tokens_used.toLocaleString()}</td>
                                                
                                                <td style="padding: 12px; vertical-align: middle; max-width: 200px;">
                                                    <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${item.message_preview || 'No message'}">
                                                        ${item.message_preview ? item.message_preview.substring(0, 80) + '...' : 'N/A'}
                                                    </div>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            Swal.fire({
                title: 'Detailed Usage Report',
                html: modalHtml,
                width: '80%',
                height: '80%',
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'Close',
                allowOutsideClick: true,
                didOpen: () => {
                    this.setupUsageModalEventListeners(data);
                }
            });
        },

        setupUsageModalEventListeners: function (data) {
            const self = this;
            let currentFilters = data.filters;
            self.usageDataTable = null;

            // Initialize Simple DataTable using the same library as reports table
            setTimeout(() => {
                // Check if simpleDatatables is available
                if (typeof simpleDatatables === 'undefined' || !simpleDatatables.DataTable) {
                    console.error('simpleDatatables library not available');
                    return;
                }
                
                // Check if table exists
                if (!$('#usage-datatable').length) {
                    console.error('Usage datatable element not found');
                    return;
                }
                
                try {
                    console.log('Initializing Simple DataTable for usage analytics...');
                    
                    // Check if table has data before initializing
                    const tableElement = document.getElementById('usage-datatable');
                    if (tableElement) {
                        const tbody = tableElement.querySelector('tbody');
                        const hasData = tbody && tbody.rows.length > 0;
                        
                        if (hasData) {
                            self.usageDataTable = new simpleDatatables.DataTable("#usage-datatable", {
                                searchable: true,
                                fixedHeight: false,
                                perPage: 25,
                                perPageSelect: [10, 25, 50, 100],
                                sortable: true,
                                labels: {
                                    placeholder: "Search usage history...",
                                    noRows: "No usage data found",
                                    info: "Showing {start} to {end} of {rows} entries"
                                }
                            });
                            
                            console.log('Simple DataTable initialized successfully:', self.usageDataTable);
                        } else {
                            console.log('No data in table, skipping DataTable initialization');
                        }
                    }
                } catch (error) {
                    console.error('Error initializing Simple DataTable:', error);
                    console.error('Error details:', error.message, error.stack);
                }
            }, 500);

            // Apply filters button
            $('#apply-filters').on('click', function() {
                const userId = $('#user-filter').val();
                const creditType = $('#credit-type-filter').val();
                const startDate = $('#start-date-filter').val();
                const endDate = $('#end-date-filter').val();

                currentFilters = {
                    user_id: userId,
                    credit_type: creditType,
                    start_date: startDate,
                    end_date: endDate
                };

                self.loadUsageDataWithFilters(currentFilters, 1);
            });

            // Clear filters button
            $('#clear-filters').on('click', function() {
                $('#user-filter').val('');
                $('#credit-type-filter').val('');
                $('#start-date-filter').val('');
                $('#end-date-filter').val('');
                
                currentFilters = {};
                self.loadUsageDataWithFilters({}, 1);
            });

            // Quick filter buttons
            $('.quick-filter').on('click', function() {
                const period = $(this).data('period');
                const today = new Date();
                let startDate = '';
                let endDate = today.toISOString().split('T')[0];
                
                switch(period) {
                    case 'today':
                        startDate = endDate;
                        break;
                    case 'week':
                        const weekAgo = new Date(today);
                        weekAgo.setDate(today.getDate() - 7);
                        startDate = weekAgo.toISOString().split('T')[0];
                        break;
                    case 'month':
                        const monthAgo = new Date(today);
                        monthAgo.setMonth(today.getMonth() - 1);
                        startDate = monthAgo.toISOString().split('T')[0];
                        break;
                }
                
                // Update date inputs
                $('#start-date-filter').val(startDate);
                $('#end-date-filter').val(endDate);
                
                // Apply filters automatically
                currentFilters = {
                    user_id: $('#user-filter').val(),
                    credit_type: $('#credit-type-filter').val(),
                    start_date: startDate,
                    end_date: endDate
                };
                
                self.loadUsageDataWithFilters(currentFilters, 1);
            });
        },

        loadUsageDataWithFilters: function(filters, page = 1) {
            // Show loading
            Swal.fire({
                title: 'Loading...',
                text: 'Applying filters and loading data',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Build query string
            const params = new URLSearchParams();
            if (filters.user_id) params.append('user_id', filters.user_id);
            if (filters.credit_type) params.append('credit_type', filters.credit_type);
            if (filters.start_date) params.append('start_date', filters.start_date);
            if (filters.end_date) params.append('end_date', filters.end_date);
            params.append('page', page);
            params.append('per_page', 1000); // Get more data for DataTable

            this.ajaxWithAuth({
                url: `https://ai-backend.stagingwithswift.com/api/chat/credits/detailed-usage?${params.toString()}`,
                method: 'GET',
                success: (response) => {
                    if (response.success) {
                        console.log('usage data', response.data);
                        this.updateUsageModalData(response.data);
                    } else {
                        Swal.fire('Error', 'Failed to load filtered data', 'error');
                    }
                },
                error: () => {
                    Swal.fire('Error', 'Failed to load filtered data', 'error');
                }
            });
        },

        updateUsageModalData: function(data) {
            const summary = data.summary;
            const usage = data.usage;

            // Update summary cards
            $('.detailed-usage-modal .card h2').eq(0).text(summary.total_credits_used);
            $('.detailed-usage-modal .card h2').eq(1).text(summary.total_tokens_used.toLocaleString());
            $('.detailed-usage-modal .card h2').eq(2).text(summary.total_requests);
            $('.detailed-usage-modal .card h2').eq(3).text(summary.unique_users);
            $('.detailed-usage-modal .card h3').eq(0).text(summary.premium_credits);
            $('.detailed-usage-modal .card h3').eq(1).text(summary.basic_credits);

            // Update record count badge
            $('.detailed-usage-modal .badge.bg-primary').text(`${usage.length} of ${data.pagination.total} records`);

            // Clear and rebuild table data
            const tableBody = $('#usage-datatable tbody');
            tableBody.empty();

            usage.forEach(item => {
                const row = `
                    <tr style="border-bottom: 1px solid #f8f9fa;">
                        <td style="padding: 12px; vertical-align: middle; font-weight: 500;">${new Date(item.created_at).toLocaleDateString()}</td>
                        <td style="padding: 12px; vertical-align: middle;">
                            <span class="badge bg-light text-dark" style="border-radius: 20px; padding: 4px 8px;">
                                ${item.user_id || 'N/A'}
                            </span>
                        </td>
                        <td style="padding: 12px; vertical-align: middle;">
                            <span class="badge ${item.credit_type === 'premium' ? 'bg-primary' : 'bg-success'}" style="border-radius: 20px; padding: 6px 12px; font-weight: 500;">
                                <i class="fas ${item.credit_type === 'premium' ? 'fa-crown' : 'fa-layer-group'} me-1"></i>
                                ${item.credit_type}
                            </span>
                        </td>
                        <td style="padding: 12px; vertical-align: middle; font-weight: 600; color: #667eea;">${item.credits_charged}</td>
                        <td style="padding: 12px; vertical-align: middle; font-weight: 500;">${item.tokens_used.toLocaleString()}</td>
                        <td style="padding: 12px; vertical-align: middle;">
                            <span class="badge bg-info text-white" style="border-radius: 20px; padding: 4px 8px;">
                                ${item.llm_provider}
                            </span>
                        </td>
                        <td style="padding: 12px; vertical-align: middle; max-width: 200px;">
                            <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${item.message_preview || 'No message'}">
                                ${item.message_preview ? item.message_preview.substring(0, 50) + '...' : 'N/A'}
                            </div>
                        </td>
                    </tr>
                `;
                tableBody.append(row);
            });

        // Update Simple DataTable if it exists
        if (this.usageDataTable && typeof this.usageDataTable.refresh === 'function') {
            try {
                this.usageDataTable.refresh();
                console.log('Simple DataTable refreshed with new data');
            } catch (error) {
                console.error('Error refreshing Simple DataTable:', error);
            }
        } else {
            console.log('Simple DataTable not found, skipping update');
            }
        },

        updateReportData: function (data) {
            const table = $('#report-table');
            table.empty();

            // Add headers
            const thead = $('<thead><tr></tr></thead>');
            data.columns.forEach(column => {
                thead.find('tr').append(`<th>${column}</th>`);
            });
            table.append(thead);

            // Add rows
            const tbody = $('<tbody></tbody>');
            data.rows.forEach(row => {
                const tr = $('<tr></tr>');
                row.forEach(cell => {
                    tr.append(`<td>${cell}</td>`);
                });
                tbody.append(tr);
            });
            table.append(tbody);
        },

        updateVisualizations: function (visualizations) {
            const container = $('#chart-container');
            container.empty();

            // Create canvas for chart
            const canvas = $('<canvas></canvas>');
            container.append(canvas);

            // Get chart type
            const type = $('#chart-type').val();

            // Create chart
            new Chart(canvas[0], {
                type: type,
                data: {
                    labels: visualizations.labels,
                    datasets: visualizations.datasets.map(dataset => ({
                        label: dataset.label,
                        data: dataset.data,
                        backgroundColor: dataset.backgroundColor,
                        borderColor: dataset.borderColor,
                        borderWidth: 1
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        title: {
                            display: true,
                            text: visualizations.title
                        }
                    }
                }
            });
        },

        showLoading: function () {
            $('#loading-indicator').removeClass('d-none');
        },

        hideLoading: function () {
            $('#loading-indicator').addClass('d-none');
        },

        showError: function (message) {
            const errorDiv = $('#error-message');
            $('#error-text').text(message);
            errorDiv.removeClass('d-none');

            // Auto-hide after 5 seconds
            setTimeout(() => {
                errorDiv.addClass('d-none');
            }, 5000);
        },

        showSuccess: function (message) {
            // Create success alert if it doesn't exist
            if ($('#success-message').length === 0) {
                const successHtml = `
                    <div class="alert alert-success alert-dismissible fade show d-none" role="alert" id="success-message" style="position:fixed; top:20px; right:20px; z-index:1050;">
                        <i class="fa fa-check-circle me-2"></i>
                        <span id="success-text"></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                $('body').append(successHtml);
            }
            
            const successDiv = $('#success-message');
            $('#success-text').text(message);
            successDiv.removeClass('d-none');

            // Auto-hide after 3 seconds
            setTimeout(() => {
                successDiv.addClass('d-none');
            }, 3000);
        },

        scrollToBottom: function () {
            const container = $('#chat-container');
            container.scrollTop(container[0].scrollHeight);
        },

        /**
         * Fetch chat history, show spinner, then render list or "No chats"
         */
        loadChatHistory: function () {
            const list = $('#chat-history-list');
            list.html(`
                <li class="list-group-item d-flex justify-content-center" id="chat-history-loading">
                    <div class="spinner-border text-secondary" role="status">
                    </div>
                </li>
                `);

            this.ajaxWithAuth({
                url: 'https://ai-backend.stagingwithswift.com/api/chat/history',
                method: 'GET',
                success: (response) => {
                    list.empty();
                    if (!response.chats.length) {
                        // Check if we also have no reports
                        const authStatus = AuthUtils.getAuthStatus();
                        const hasReports = authStatus && authStatus.subscription && 
                            authStatus.subscription.reports_generated_this_month > 0;
                        
                        if (!hasReports) {
                            list.append(`
                                <li class="list-group-item text-center">
                                    <div class="text-muted">
                                        <i class="fa fa-comments fa-2x mb-2"></i>
                                        <p class="mb-1">No chat history yet</p>
                                        <small>Start a conversation to see your chat history here</small>
                                    </div>
                                </li>
                            `);
                        } else {
                        list.append(`<li class="list-group-item text-center">No chats yet</li>`);
                        }
                    } else {
                        response.chats.forEach(chat => {
                            const date = new Date(chat.created_at).toLocaleString();
                            const snippet = chat.snippet;
                            const reportCount = chat.report_count || 0;
                            const badgeHtml = reportCount > 0
                                ? `<span class="badge bg-primary d-flex align-items-center report-count"><i class="fa fa-chart-bar me-1"></i>${reportCount}</span>`
                                : '';
                            const item = $(`
                                <li class="list-group-item d-flex justify-content-between align-items-start chat-history-item" data-chat-id="${chat.id}">
                                    <div>
                                        <small class="text-muted">${date}</small><br>
                                        ${snippet}
                                    </div>
                                    ${badgeHtml}
                                </li>
                            `);
                            list.prepend(item);
                        });
                        $('.chat-history-item').on('click', (e) => {
                            const li = $(e.currentTarget);
                            const chatId = li.data('chat-id');
                            this.loadChatMessages(chatId, li);
                        });
                    }
                },
                error: () => {
                    list.html(`
                        <li class="list-group-item text-danger d-flex flex-column align-items-center">
                            <span>Failed to load chats</span>
                            <button id="reload-chat-history" class="btn btn-outline-primary btn-sm mt-2">Reload</button>
                        </li>
                    `);
                    $('#reload-chat-history').on('click', () => this.loadChatHistory());
                }
            });
        },

        /**
         * Load a specific chat's messages, highlight the selected item
         */
        loadChatMessages: function (chatId, listItem) {
            console.log('loadChatMessages', chatId, listItem);
            this.currentChatId = chatId;
            $('#chat-history-list li').removeClass('active');
            listItem.addClass('active');
            $('#chat-container').empty();
            this.clearMCQ(); // Clear and hide MCQ container when loading different chat
            this.showLoading();

            this.ajaxWithAuth({
                url: `https://ai-backend.stagingwithswift.com/api/chat/${chatId}/messages`,
                method: 'GET',
                success: (response) => {
                    this.hideLoading();
                    console.log(response);
                    
                    // Check for credit limit information in response and update button state
                    if (response.credit_data && response.credit_data.summary) {
                        this.updateSubscriptionDataFromCreditResponse(response.credit_data);
                    }
                    
                    // Always check current subscription status and update button state
                    const authStatus = AuthUtils.getAuthStatus();
                    console.log('[loadChatMessages] Checking credit limit status:', authStatus);
                    if (authStatus && authStatus.subscription) {
                        console.log('[loadChatMessages] Subscription data:', authStatus.subscription);
                        this.checkAndShowCreditLimitWarning(authStatus.subscription);
                    }
                    
                    // Render chat messages with message IDs, filtering out SQL-tagged messages and passing timestamp
                    response.messages.forEach((msg, idx) => {
                        if (msg.tag === 'sql') {
                            return;
                        }
                        
                        // Check if this is an MCQ message followed by a user answer
                        if (msg.sender_type === 'ai' && msg.tag === 'mcq') {
                            // Look for the next user message as the selected answer
                            const nextMsg = response.messages[idx + 1];
                            const selectedAnswer = (nextMsg && nextMsg.sender_type === 'user') ? nextMsg.body : null;
                            
                            // Extract MCQ data and render with selected answer
                            const mcqData = this.extractMCQFromText(msg.body);
                            if (mcqData && mcqData.questions.length > 0) {
                                mcqData.selectedAnswer = selectedAnswer;
                                const mcqHtml = this.renderMCQHistory(mcqData.questions, selectedAnswer);
                                
                                // Add as AI message with MCQ HTML
                                const template = document.getElementById('message-template');
                                const frag = template.content.cloneNode(true);
                                const messageEl = frag.querySelector('.message');
                                messageEl.classList.add('ai-message');
                                messageEl.setAttribute('data-message-id', msg.id);
                                frag.querySelector('.message-text').innerHTML = mcqHtml;
                                frag.querySelector('.message-time').textContent = this.formatTimestamp(msg.timestamp);
                                $('#chat-container').append(frag);
                                return; // Skip the normal addMessage call
                            }
                        }
                        
                        // Skip rendering the user's MCQ answer separately if it was already shown in the MCQ
                        if (msg.sender_type === 'user' && idx > 0) {
                            const prevMsg = response.messages[idx - 1];
                            if (prevMsg && prevMsg.sender_type === 'ai' && prevMsg.tag === 'mcq') {
                                // This is the MCQ answer, already rendered in the MCQ view above
                                return;
                            }
                        }
                        
                        this.addMessage(msg.body, msg.sender_type, false, null, msg.id, msg.timestamp);
                    });
                    this.scrollToBottom();
                    // After messages, insert report links from response
                    if (response.reports && response.reports.length) {
                        response.reports.forEach(report => {
                            this.insertReportLinkInChat(report);
                        });
                    }
                    // If last message was an MCQ, enqueue and render MCQ view
                    if (response.messages.length > 0) {
                        const lastMsg = response.messages[response.messages.length - 1];
                        if (lastMsg.tag === 'mcq') {
                            // Extract JSON objects from the message body
                            const jsonMatches = lastMsg.body.match(/\{[\s\S]*?\}/g) || [];
                            const questions = [];
                            jsonMatches.forEach(jsonStr => {
                                try {
                                    const obj = JSON.parse(jsonStr);
                                    if (obj.type === 'mcq' && Array.isArray(obj.options)) {
                                        questions.push(obj);
                                    }
                                } catch (e) {}
                            });
                            if (questions.length) {
                                this.enqueueMCQs(questions);
                            }
                        }
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showError('Could not load conversation.');
                }
            });
        },

        /**
         * Insert a report link into the chat after the corresponding message
         */
        insertReportLinkInChat: function(report) {
            const $msg = $(`#chat-container .message[data-message-id="${report.message_after_id}"]`);
            if (!$msg.length) return;
            // Build report link message
            const linkHtml = `
                <div class="message ai-message">
                  <div class="message-content">
                    <div class="message-text">
                      <a href="javascript:void(0)" class="report-link" data-report-slug="${report.slug}" 
                         style="color: #007bff; text-decoration: none; padding:4px 8px; border:1px solid #dee2e6; border-radius:4px; background:#f8f9fa; display:inline-block; transition:all 0.2s;">
                        📊 ${report.description}
                      </a>
                    </div>
                    <div class="message-time small text-muted">${new Date(report.created_at).toLocaleTimeString()}</div>
                  </div>
                </div>
            `;
            const $linkMsg = $(linkHtml);
            // Bind click and hover
            const self = this;
            $linkMsg.find('.report-link')
                .on('click', function() {
                    const slug = $(this).data('report-slug');
                    self.openReportFromLink(slug);
                })
                .on('mouseenter', function() {
                    $(this).css({ 'background-color':'#e9ecef', 'border-color':'#adb5bd', 'transform':'translateY(-1px)' });
                })
                .on('mouseleave', function() {
                    $(this).css({ 'background-color':'#f8f9fa', 'border-color':'#dee2e6', 'transform':'translateY(0)' });
                });
            // Insert after message
            $msg.after($linkMsg);
        },

        sendMessage: function () {
            if (this.currentMCQ) {
                // ignore text input while MCQ active
                return;
            }
            
            // Prevent double sending
            if (this.isSending) {
                return;
            }
            
            const input = $('#message-input');
            const rawValue = (input.val() || '').trim();
            console.log('rawValue', rawValue);
            if (!rawValue) {
                Swal.fire({ icon: 'error', title: 'Oops...', text: 'Please enter a message' });
                return;
            }
            const message = rawValue.trim();
            if (!message) {
                return;
            }

            this.isSending = true;
            this.showLoading();
            const $userMsg = this.addMessage(message, 'user');

            input.val('').trigger('input');

            // Get user information from auth status
            const authStatus = AuthUtils.getAuthStatus();
            const userInfo = authStatus?.user || {};

            this.ajaxWithAuth({
                url: 'https://ai-backend.stagingwithswift.com/api/chat/message',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    message: message,
                    chat_id: this.currentChatId || 0,
                    user_id: userInfo.id || null,
                    user_name: userInfo.name || null
                }),
                success: (response) => {
                    this.hideLoading();
                    this.isSending = false;
                    if (response.error) {
                        // Handle specific error types
                        if (response.error_type === 'credit_limit') {
                            this.handleCreditLimitError(response.message, response.credit_data);
                        } else if (response.error_type === 'timeout') {
                            this.handleTimeoutError(response.message);
                        } else {
                        // Display backend error as chat bubble
                        this.addMessage(response.message, 'error');
                        // Show resend icon on the specific user message that failed
                        this.showResendIconOnMessage($userMsg, message);
                        }
                        return;
                    }
                    // Remove any existing resend icons on success (clean up from previous failures)
                    $('.failed-reload-icon').remove();
                    // update chat_id on first message
                    if (!this.currentChatId && response.chat_id) {
                        this.currentChatId = response.chat_id;
                        this.addChatToChatHistory(response.chat_id, message);
                    }
                    
                    // Handle response based on type
                    this.handleResponse(response);
                    
                    // Refresh subscription info to update credit usage (reduced delay for faster updates)
                    // setTimeout(() => {
                        this.refreshSubscriptionInfo();
                    // }, 500);
                },
                error: (err) => {
                    this.hideLoading();
                    this.isSending = false;
                    if (err.status === 401) {
                        AuthUtils.clearAuthData();
                        this.showAuthenticationError();
                    } else {
                        // Show resend icon on the specific user message that failed
                        this.showResendIconOnMessage($userMsg, message);
                        this.addMessage('Network error occurred. Please try again.', 'error');
                        return;
                    }
                }
            });
        },

        updateSendMessageandCreateNewChatButton: function() {
            console.log('[updateSendMessageandCreateNewChatButton] isCreditLimitExceeded:', this.isCreditLimitExceeded);
            if (this.isCreditLimitExceeded) {
                console.log('[updateSendMessageandCreateNewChatButton] Disabling send button and create new chat button');
                $('#send-button').prop('disabled', true);
                $('#create-new-chat').prop('disabled', true);
            } else {
                console.log('[updateSendMessageandCreateNewChatButton] Enabling send button and create new chat button');
                $('#send-button').prop('disabled', false);
                $('#create-new-chat').prop('disabled', false);
            }
        },

        createNewChat: function () {
            // if (this.currentChatId == 0) {
            //     Swal.fire({
            //         icon: 'error',
            //         title: 'Oops...',
            //         text: 'No chat in progress, please start a new chat',
            //     });
            //     return;
            // } else {
            console.log('createNewChat', this.currentChatId);
            this.currentChatId = 0;
            $('#chat-history-list li').removeClass('active');
            $('#chat-container').empty();
            this.clearMCQ(); // Clear and hide MCQ container
            // }
        },

        addChatToChatHistory(chatId, firstMessage) {
            const list = $('#chat-history-list');
            
            // Remove "No chat history yet" placeholder if it exists
            list.find('li:contains("No chat history yet")').remove();
            
            const date = new Date().toLocaleString();
            const item = $(
                `<li class="list-group-item d-flex justify-content-between align-items-start chat-history-item" data-chat-id="${chatId}">
                    <div>
                        <small class="text-muted">${date}</small><br>
                        ${firstMessage}
                    </div>
                </li>`
            );
            list.prepend(item);
            item.on('click', (e) => {
                const li = $(e.currentTarget);
                const chatId = li.data('chat-id');
                this.loadChatMessages(chatId, li);
            });
        },

        initializeCharts: function () {
            // Initialize with empty chart
            const container = $('#chart-container');
            const canvas = $('<canvas></canvas>');
            container.append(canvas);

            new Chart(canvas[0], {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        },

        setUserName: function (user) {
            let userName = "User";
            if (typeof user === 'string') {
                userName = user;
            } else if (user && user.name) {
                userName = user.name;
            } else if (user && user.admin_name) {
                userName = user.admin_name;
            }
            
            // Update the card title with the user's name
            $(".card-title").each(function () {
                if ($(this).text().includes('AI Report Assistant')) {
                    $(this).text(userName + ': AI Report Assistant');
                }
            });
        },

        /**
         * Check if credit usage exceeds the limit
         */
        isCreditExceeded: function(used, limit) {
            if (limit === 0 || limit === '∞' || limit === null || limit === undefined) {
                return false;
            }
            return used >= limit;
        },

        /**
         * Check if user has exceeded credit limits and show persistent warning
         */
        checkAndShowCreditLimitWarning: function(subscription) {
            console.log('[checkAndShowCreditLimitWarning] Checking subscription:', subscription);
            
            // Check if any credit type is exceeded
            const basicExceeded = this.isCreditExceeded(
                subscription.basic_credits_used_this_month || 0, 
                subscription.plan_basic_credits_limit || 0
            );
            const premiumExceeded = this.isCreditExceeded(
                subscription.premium_credits_used_this_month || 0, 
                subscription.plan_premium_credits_limit || 0
            );
            const totalExceeded = this.isCreditExceeded(
                subscription.ai_credits_used_this_month || 0, 
                subscription.plan_ai_credits_limit || 0
            );
            
            console.log('[checkAndShowCreditLimitWarning] Credit status:', {
                basicExceeded,
                premiumExceeded, 
                totalExceeded,
                basicUsed: subscription.basic_credits_used_this_month || 0,
                basicLimit: subscription.plan_basic_credits_limit || 0,
                premiumUsed: subscription.premium_credits_used_this_month || 0,
                premiumLimit: subscription.plan_premium_credits_limit || 0,
                totalUsed: subscription.ai_credits_used_this_month || 0,
                totalLimit: subscription.plan_ai_credits_limit || 0
            });
            
            if (basicExceeded || premiumExceeded || totalExceeded) {
                console.log('[checkAndShowCreditLimitWarning] Credit limit exceeded, disabling send button');
                const message = "You've used all your AI credits for this month. Your credits will reset on the 1st of next month. Consider upgrading your plan for more credits.";
                this.showPersistentCreditLimitMessage(message);
                // set global variable to true
                this.isCreditLimitExceeded = true;
                this.updateSendMessageandCreateNewChatButton();
            }else{
                console.log('[checkAndShowCreditLimitWarning] Credit limit not exceeded, enabling send button');
                this.isCreditLimitExceeded = false;
                this.updateSendMessageandCreateNewChatButton();
            }
        },

        updateSubscriptionInfo: async function() {
            const self = this;
            
            // First, fetch latest subscription data from the server (like wizard does)
            try {
                console.log('[AI Assistant] Fetching latest subscription status...');
                const response = await fetch(`${M.cfg.wwwroot}/report/adeptus_insights/ajax/check_subscription_status.php?t=${Date.now()}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache'
                    }
                });

                const data = await response.json();
                console.log('[AI Assistant] Latest subscription status response:', data);
                
                // Log exactly what fields we're receiving for debugging
                if (data.success && data.data) {
                    console.log('[AI Assistant] ===== SUBSCRIPTION DATA BREAKDOWN =====');
                    console.log('[AI Assistant] Plan Name:', data.data.plan_name);
                    console.log('[AI Assistant] Status:', data.data.status);
                    console.log('[AI Assistant] Credit Type:', data.data.credit_type);
                    console.log('[AI Assistant] Reports Generated:', data.data.reports_generated_this_month);
                    console.log('[AI Assistant] Reports Limit:', data.data.plan_exports_limit);
                    console.log('[AI Assistant] AI Credits Used:', data.data.total_credits_used_this_month);
                    console.log('[AI Assistant] AI Credits Limit:', data.data.plan_total_credits_limit);
                    console.log('[AI Assistant] Exports Used:', data.data.exports_used);
                    console.log('[AI Assistant] Exports Remaining:', data.data.exports_remaining);
                    console.log('[AI Assistant] ========================================');
                    
                    // Update AuthUtils with fresh data
                    const authStatus = AuthUtils.getAuthStatus() || {};
                    authStatus.subscription = data.data;
                    // Store updated auth status
                    if (typeof AuthUtils.updateSubscription === 'function') {
                        AuthUtils.updateSubscription(data.data);
                    }
                }
            } catch (error) {
                console.error('[AI Assistant] Error fetching latest subscription status:', error);
            }
            
            // Get subscription info from auth status (now updated with fresh data)
            const authStatus = AuthUtils.getAuthStatus();
            
            console.log('[AI Assistant] Updating subscription info with auth status:', authStatus);
            
            if (authStatus && authStatus.subscription) {
                const subscription = authStatus.subscription;
                
                // Check if user has exceeded credit limits and show persistent message
                this.checkAndShowCreditLimitWarning(subscription);
                
                // Remove existing assistant header to prevent duplicates
                $('.assistant-header').remove();
                
                // Create or update subscription info display
                let subscriptionHtml = `
                    <div class="assistant-header">
                        <div class="subscription-header-content">
                            <h6 class="subscription-title">
                                <i class="fa fa-chart-line me-1 subscription-icon"></i> Subscription Status <button class="btn btn-outline-secondary btn-sm" id="refresh-subscription-btn" title="Refresh subscription data">
                                <i class="fa fa-refresh"></i></button>
                            </h6>
                            
                            </button>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Plan:</strong> ${subscription.plan_name || 'Unknown'}<br>
                                <strong>Status:</strong> <span class="badge bg-${subscription.status === 'active' ? 'success' : 'warning'}">${subscription.status || 'Unknown'}</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Reports:</strong> ${subscription.reports_generated_this_month || 0}/${subscription.plan_exports_limit || '∞'}<br>
                                <strong>AI Credits (${subscription.credit_type || 'basic'}):</strong> <span class="total-credits-counter ${this.isCreditExceeded(subscription.total_credits_used_this_month || 0, subscription.plan_total_credits_limit || 0) ? 'text-danger' : ''}">${subscription.total_credits_used_this_month || 0}</span>/${subscription.plan_total_credits_limit || '∞'}
                            </div>
                        </div>
                        </div>
                        
                        <div class="row mt-2" style="display: none;">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <small class="text-muted">Premium Credits</small>
                                    <div class="progress mb-1" style="height: 6px;">
                                        <div class="progress-bar bg-primary" role="progressbar" 
                                             style="width: ${this.calculateUsagePercentage(subscription.premium_credits_used_this_month || 0, subscription.plan_premium_credits_limit || 1)}%">
                            </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <small class="text-muted">Basic Credits</small>
                                    <div class="progress mb-1" style="height: 6px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: ${this.calculateUsagePercentage(subscription.basic_credits_used_this_month || 0, subscription.plan_basic_credits_limit || 1)}%">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="assistant-header-actions">
                            <a style="margin: 0 20px 17px 0" href="javascript:void(0)" onclick="window.assistant.showCreditUsageModal()" class="btn btn-primary diagnostics-btn">
                                <i class="fa fa-stethoscope"></i> View Usage
                            </a>
                        </div>
                    </div>
                `;
                
                let assistantHtml = `
                     <div class="assistant-header">
                        <div class="assistant-header-content">
                            <h1 class="assistant-title">
                                <i class="fa fa-bolt wizard-icon"></i>
                                Adeptus Insights: AI Assistant
                            </h1>
                            <p class="wizard-subtitle">Generate the reports you need by speaking to our AI assistant.</p>
                        </div>
                        <div class="wizard-header-section">
                            <a style="margin-bottom: 17px;" href="/report/adeptus_insights/index.php" class="btn btn-primary">
                                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                                </a>
                        </div>
                    </div>
                `;
                

                // Insert header info above the tabs (before the nav-tabs)
                const tabNav = $('.tab-nav');
                if (tabNav.length && !$('.subscription-info').length) {
                    tabNav.before(assistantHtml);
                } else if ($('.subscription-info').length) {
                    $('.subscription-info').replaceWith(assistantHtml);
                } else {
                    // Fallback: insert after the main title if nav-tabs not found
                    const mainTitle = $('h1, h2, .page-title').first();
                    if (mainTitle.length) {
                        mainTitle.after(assistantHtml);
                    }
                }


                // Find the parent container that holds both tab panels
const tabContainer = $('#assistant-panel, #reports-panel').closest('.tab-content, .tab-pane').parent();
// Or if they share a specific parent container:
// const tabContainer = $('#assistant-panel').parent();

// Remove existing subscription info
$('.subscription-info').remove();

if (tabContainer.length) {
    // Insert after the entire tab container
    tabContainer.after(subscriptionHtml);
} else {
    // Fallback
    const mainTitle = $('h1, h2, .page-title').first();
    if (mainTitle.length) {
        mainTitle.after(subscriptionHtml);
    }
}
                
                // Bind refresh button event
                const self = this;
                $('#refresh-subscription-btn').off('click').on('click', function() {
                    const $btn = $(this);
                    const $icon = $btn.find('i');
                    
                    // Show loading state
                    $icon.removeClass('fa-refresh').addClass('fa-spinner fa-spin');
                    $btn.prop('disabled', true);
                    
                    // Refresh subscription info
                    self.refreshSubscriptionInfo();
                    
                    // Reset button state after refresh
                    setTimeout(() => {
                        $icon.removeClass('fa-spinner fa-spin').addClass('fa-refresh');
                        $btn.prop('disabled', false);
                    }, 1000);
                });
            } else {
                console.log('[AI Assistant] No subscription data available in auth status');
                // Remove subscription info if no data available
                $('.subscription-info').remove();
            }
        },

        /**
         * Transform backend auth data to match frontend expectations
         */
        transformBackendAuthData: function(backendData) {
            if (!backendData) return null;
            
            // Get current auth data to preserve existing structure
            const currentAuthData = window.adeptusAuthData || {};
            
            // Transform the data to match the expected frontend structure
            const transformedData = {
                ...currentAuthData, // Preserve existing auth data
                user_authorized: true,
                has_api_key: true,
                installation: backendData.installation,
                subscription: backendData.subscription ? {
                    ...backendData.subscription,
                    // Map the usage data to the expected format
                    premium_credits_used_this_month: backendData.usage ? (backendData.usage.premium_credits_used_this_month || 0) : 0,
                    basic_credits_used_this_month: backendData.usage ? (backendData.usage.basic_credits_used_this_month || 0) : 0,
                    ai_credits_used_this_month: backendData.usage ? (backendData.usage.ai_credits_used_this_month || 0) : 0,
                    reports_generated_this_month: backendData.usage ? (backendData.usage.reports_generated_this_month || 0) : 0,
                    // Map plan data
                    plan_name: backendData.plan ? backendData.plan.name : 'Unknown',
                    plan_premium_credits_limit: backendData.plan ? (backendData.plan.premium_credits_per_month || 0) : 0,
                    plan_basic_credits_limit: backendData.plan ? (backendData.plan.basic_credits_per_month || '∞') : '∞',
                    plan_ai_credits_limit: backendData.plan ? (backendData.plan.ai_credits || 0) : 0,
                    plan_exports_limit: backendData.plan ? (backendData.plan.exports || '∞') : '∞'
                } : null,
                plan: backendData.plan,
                usage: backendData.usage
            };
            
            return transformedData;
        },

        /**
         * Refresh subscription info by getting fresh data from backend
         */
        refreshSubscriptionInfo: async function() {
            console.log('[AI Assistant] Refreshing subscription info...');
            
            // Show loading state on refresh button
            const $refreshBtn = $('#refresh-subscription-btn');
            const $refreshIcon = $refreshBtn.find('i');
            if ($refreshBtn.length && $refreshIcon.length) {
                $refreshIcon.removeClass('fa-refresh').addClass('fa-spinner fa-spin');
                $refreshBtn.prop('disabled', true);
            }
            
            try {
                // First, get fresh local subscription data (like wizard does)
                const localResponse = await fetch(`${M.cfg.wwwroot}/report/adeptus_insights/ajax/check_subscription_status.php?t=${Date.now()}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache'
                    }
                });

                const localData = await localResponse.json();
                console.log('[AI Assistant] Latest local subscription status:', localData);

                if (localData.success && localData.data) {
                    // Update AuthUtils with fresh data
                    const authStatus = AuthUtils.getAuthStatus() || {};
                    authStatus.subscription = localData.data;
                    
                    // Also update the global auth data
                    if (window.adeptusAuthData) {
                        window.adeptusAuthData.subscription = localData.data;
                    }
                }
                
                // Then update the display with fresh data
                await this.updateSubscriptionInfo();
                
                console.log('[AI Assistant] Subscription info refreshed successfully');
                
                // Show success feedback
                this.showRefreshSuccessFeedback();
                
            } catch (error) {
                console.error('[AI Assistant] Failed to refresh subscription info:', error);
                this.showRefreshErrorFeedback();
            } finally {
                // Reset button state
                if ($refreshBtn.length && $refreshIcon.length) {
                    $refreshIcon.removeClass('fa-spinner fa-spin').addClass('fa-refresh');
                    $refreshBtn.prop('disabled', false);
                }
            }
        },

        /**
         * Show success feedback for subscription refresh
         */
        showRefreshSuccessFeedback: function() {
            const notification = $(`
                <div class="refresh-success-notification" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #28a745;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 4px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                    z-index: 9999;
                    font-size: 12px;
                    opacity: 0;
                    transform: translateY(-20px);
                    transition: all 0.3s ease;
                ">
                    <i class="fa fa-check me-1"></i>
                    Usage updated
                </div>
            `);
            
            $('body').append(notification);
            
            // Animate in
            setTimeout(() => {
                notification.css({
                    'opacity': '1',
                    'transform': 'translateY(0)'
                });
            }, 100);
            
            // Remove after 2 seconds
            setTimeout(() => {
                notification.css({
                    'opacity': '0',
                    'transform': 'translateY(-20px)'
                });
                setTimeout(() => notification.remove(), 300);
            }, 2000);
        },

        /**
         * Show error feedback for subscription refresh
         */
        showRefreshErrorFeedback: function() {
            const notification = $(`
                <div class="refresh-error-notification" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #dc3545;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 4px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                    z-index: 9999;
                    font-size: 12px;
                    opacity: 0;
                    transform: translateY(-20px);
                    transition: all 0.3s ease;
                ">
                    <i class="fa fa-exclamation-triangle me-1"></i>
                    Failed to update usage
                </div>
            `);
            
            $('body').append(notification);
            
            // Animate in
            setTimeout(() => {
                notification.css({
                    'opacity': '1',
                    'transform': 'translateY(0)'
                });
            }, 100);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.css({
                    'opacity': '0',
                    'transform': 'translateY(-20px)'
                });
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        },

        /**
         * Calculate usage percentage for progress bar
         */
        calculateUsagePercentage: function(used, limit) {
            if (!limit || limit === '∞' || limit === 0) {
                return 0;
            }
            const percentage = (used / limit) * 100;
            return Math.min(percentage, 100); // Cap at 100%
        },



        ajaxWithAuth: function (options) {
            // Check authentication before making the request
            if (!AuthUtils.isAuthenticated()) {
                this.showAuthenticationError();
                return Promise.reject(new Error('Authentication required'));
            }

            // Add authentication headers to the request
            const authHeaders = AuthUtils.getAuthHeaders();
            options.headers = { ...options.headers, ...authHeaders };

            // Make the authenticated request
            return $.ajax(options);
        },

        validateToken: function () {
            return AuthUtils.isAuthenticated();
        },

        resendMessage: function ($msgElem, message) {
            if (this.isSending) {
                return;
            }
            
            this.isSending = true;
            this.showLoading();
            // Get user information from auth status
            const authStatus = AuthUtils.getAuthStatus();
            const userInfo = authStatus?.user || {};
            
            this.ajaxWithAuth({
                url: 'https://ai-backend.stagingwithswift.com/api/chat/message',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ 
                    message: message, 
                    chat_id: this.currentChatId || 0,
                    user_id: userInfo.id || null,
                    user_name: userInfo.name || null
                }),
                success: (response) => {
                    this.hideLoading();
                    this.isSending = false;
                    if (response.error) {
                        // Handle specific error types
                        if (response.error_type === 'credit_limit') {
                            this.handleCreditLimitError(response.message, response.credit_data);
                        } else {
                        // Display backend error as chat bubble
                        this.addMessage(response.message, 'error');
                        // Show resend icon on the specific message that failed
                        this.showResendIconOnMessage($msgElem, message);
                        }
                        return;
                    }
                    // Remove all resend icons on successful resend
                    $('.failed-reload-icon').remove();
                    
                    if (!this.currentChatId && response.chat_id) {
                        this.currentChatId = response.chat_id;
                        this.addChatToChatHistory(response.chat_id, message);
                    }
                    this.handleResponse(response);
                },
                error: (err) => {
                    this.hideLoading();
                    this.isSending = false;
                    if (err.status === 401) {
                        AuthUtils.clearAuthData();
                        this.showAuthenticationError();
                    } else {
                        // Show resend icon on the specific message that failed
                        this.showResendIconOnMessage($msgElem, message);
                        this.addMessage('Network error occurred. Please try again.', 'error');
                    }
                }
            });
        },

        /**
         * Push an array of MCQs into our queue and display the first one.
         * @param {Array} questions  Array of {question: string, options: []}
         */
        enqueueMCQs: function(questions) {
            if (!Array.isArray(questions) || questions.length === 0) {
                return;
            }
            this.mcqQueue = questions.slice(); // copy
            this.showNextMCQ();
        },

        /**
         * Display the next MCQ in the queue, or end MCQ mode if none left.
         */
        showNextMCQ: function() {
            if (this.mcqQueue.length === 0) {
                return this.clearMCQ();
            }
            const next = this.mcqQueue.shift();
            this.renderMCQ(next.question, next.options);
        },

        /**
         * Render a single MCQ question with radio buttons.
         * @param {string} question
         * @param {string[]} options
         */
        renderMCQ: function(question, options) {
            this.currentMCQ = true;
            $('#message-input, #send-button').prop('disabled', true);
            const c = $('#mcq-container').empty().show();
            c.append(`<p class="mcq-question"><strong>${question}</strong></p>`);
            options.forEach((opt, idx) => {
                const key = String.fromCharCode(65 + idx);
                c.append(`
                <div class="form-check">
                    <input class="form-check-input" type="radio"
                        name="mcq-option" id="mcq-${idx}"
                        value="${opt}">
                    <label class="form-check-label" for="mcq-${idx}">
                    ${key}. ${opt}
                    </label>
                </div>`);
            });
            c.append(`<button class="btn btn-link" id="mcq-cancel">Cancel</button>`);
            // bind selection
            c.find('input[name="mcq-option"]').on('change', () => {
                $('#mcq-submit').remove();
                c.append(`<button id="mcq-submit" class="btn btn-primary mt-2">Submit</button>`);
                $('#mcq-submit').off('click').on('click', () => {
                    const selected = c.find('input[name="mcq-option"]:checked').val();
                    this.sendMCQ(selected);
                });
            });
            // cancel
            c.find('#mcq-cancel').off('click').on('click', () => {
                this.mcqQueue = [];
                this.clearMCQ();
            });
        },

        /**
         * Send the selected MCQ answer to the backend, then show next question if any.
         */
        sendMCQ: function(answer) {
            if (this.isSending) {
                return;
            }

            this.showLoading();
            this.isSending = true;
            this.clearMCQ();                       // clear current UI
            const $userMsg = this.addMessage(answer, 'user');      // echo user choice
            // Get user information from auth status
            const authStatus = AuthUtils.getAuthStatus();
            const userInfo = authStatus?.user || {};
            
            this.ajaxWithAuth({
                url: 'https://ai-backend.stagingwithswift.com/api/chat/message',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    message: answer,
                    chat_id: this.currentChatId || 0,
                    user_id: userInfo.id || null,
                    user_name: userInfo.name || null
                }),
                success: (response) => {
                    this.hideLoading();
                    this.isSending = false;
                    if (response.error) {
                        // Handle specific error types
                        if (response.error_type === 'credit_limit') {
                            this.handleCreditLimitError(response.message);
                        } else {
                        this.addMessage(response.message, 'error');
                        this.showResendIconOnMessage($userMsg, answer);
                        }
                        return;
                    }
                    // Remove any existing resend icons on success
                    $('.failed-reload-icon').remove();
                    // Handle response using the same logic as regular messages
                    this.handleResponse(response);
                },
                error: () => {
                    this.hideLoading();
                    this.isSending = false;
                    this.addMessage('Failed to send choice', 'error');
                    this.showResendIconOnMessage($userMsg, answer);
                }
            });
        },

        /**
         * Switch to the reports tab
         */
        switchToReportsTab: function() {
            $('#reports-tab').tab('show');
            $('#assistant-tab').removeClass('active').attr('aria-selected', 'false');
            $('#reports-tab').addClass('active').attr('aria-selected', 'true');
            $('#assistant-panel').removeClass('show active');
            $('#reports-panel').addClass('show active');

            // Reset view placeholder
            $('.report-view-title').text('Select a Report');
            $('.report-view-body').html(
                '<div class="text-center py-4"><p class="text-muted">Select a report from history to view details here.</p></div>'
            );

            // Ensure report history table is visible
            $('#report-history-loader').addClass('d-none');
            $('#report-history-table-wrapper').removeClass('d-none');
            // No reload here; use cachedReports
        },

        /**
         * Load reports history
         */
        loadReportsHistory: function(callback) {
            $('#report-history-loader').removeClass('d-none');
            $('#report-history-table-wrapper').addClass('d-none');
            
            this.ajaxWithAuth({
                url: 'https://ai-backend.stagingwithswift.com/api/reports',
                method: 'GET',
                success: (response) => {
                    // Cache reports for reuse
                    this.cachedReports = response.reports;
                    this.updateReportsHistory(response.reports);
                    $('#report-history-loader').addClass('d-none');
                    $('#report-history-table-wrapper').removeClass('d-none');

                    // Destroy existing DataTable if it exists
                    if (this.reportsDataTable) {
                        this.reportsDataTable.destroy();
                        this.reportsDataTable = null;
                    }
                    
                    // Only initialize DataTable if we have data and table structure is ready
                    setTimeout(() => {
                        try {
                            const tableElement = document.getElementById('reports-history-table');
                            if (!tableElement) {
                                console.warn('Table element not found');
                                return;
                            }
                            
                            const tbody = tableElement.querySelector('tbody');
                            const thead = tableElement.querySelector('thead');
                            
                            // Verify table structure before initializing DataTable
                            if (thead && tbody && thead.rows.length > 0 && thead.rows[0].cells.length > 0) {
                                // Check if DataTable is already initialized and destroy it first
                                if (this.reportsDataTable) {
                                    this.reportsDataTable.destroy();
                                    this.reportsDataTable = null;
                                }
                                
                                // Count header and data columns to ensure they match
                                const headerCells = thead.rows[0].cells.length;
                                const dataRows = tbody.rows;
                                let dataCells = 0;
                                
                                if (dataRows.length > 0) {
                                    dataCells = dataRows[0].cells.length;
                                }
                                
                                console.log(`Table structure check - Headers: ${headerCells}, Data: ${dataCells}`);
                                
                                if (headerCells === dataCells && headerCells > 0) {
                                    // Only initialize DataTable if we have actual data rows (not just empty state)
                                    if (dataRows.length > 0 && !dataRows[0].cells[0].textContent.includes('No reports')) {
                    this.reportsDataTable = new simpleDatatables.DataTable("#reports-history-table", {
                        searchable: true,
                        fixedHeight: false,
                        perPage: 10
                    });
                                        console.log('DataTable initialized successfully');
                                    } else {
                                        console.log('Skipping DataTable initialization - no data rows or empty state');
                                    }
                                } else {
                                    console.warn(`Column mismatch - Headers: ${headerCells}, Data: ${dataCells}`);
                                }
                            } else {
                                console.warn('Table structure not ready for DataTable initialization');
                            }
                        } catch (error) {
                            console.error('Error initializing DataTable:', error);
                            // Fallback: table will still be functional without DataTable features
                        }

                    if (typeof callback === 'function') {
                        callback();
                    }
                    }, 100); // Small delay to ensure DOM is ready
                },
                error: (xhr, status, error) => {
                    console.error('Failed to load report history:', error);
                    this.showError('Failed to load report history');
                    $('#report-history-loader').addClass('d-none');
                    
                    // Show error message in table
                    const tbody = $('#reports-history-table tbody');
                    tbody.empty();
                    tbody.append(
                        `<tr><td colspan="3" class="text-center text-danger">Failed to load reports</td></tr>`
                    );
                    $('#report-history-table-wrapper').removeClass('d-none');
                }
            });
        },

        /**
         * Update the reports history table
         */
        updateReportsHistory: function(reports) {
            const tbody = $('#reports-history-table tbody');
            const self = this; // capture context
            tbody.empty();
            if (!reports || reports.length === 0) {
                // Check if we also have no chat history
                const authStatus = AuthUtils.getAuthStatus();
                const hasChats = authStatus && authStatus.subscription && 
                    authStatus.subscription.reports_generated_this_month > 0;
                
                if (!hasChats) {
                    tbody.append(`
                        <tr>
                            <td colspan="3" class="text-center text-muted">
                                <div class="py-4">
                                    <i class="fa fa-chart-bar fa-2x mb-2"></i>
                                    <p class="mb-1">No reports generated yet</p>
                                    <small>Ask the AI assistant to create reports for you</small>
                                </div>
                            </td>
                        </tr>
                    `);
                } else {
                tbody.append(
                    `<tr><td colspan="3" class="text-center text-muted">No reports yet</td></tr>`
                );
                }
                return;
            }
            reports.forEach(report => {
                const statusBadge = self.getStatusBadge(report.status);
                const date = new Date(report.created_at).toLocaleDateString();
                const row = $(`
                    <tr class="report-row" data-report-slug="${report.slug}">
                        <td>${report.description}</td>
                        <td>${date}</td>
                        <td>${statusBadge}</td>
                    </tr>`
                );
                row.on('click', function() { // use function() to get row as 'this', use 'self' for assistant
                    // Always show loading spinner in carousel container
                    const reportsView = $('#reports-panel .col-md-8 .card-body');
                    reportsView.find('.report-display-wrapper').html('<div class="w-100 d-flex justify-content-center align-items-center" style="min-height:200px"><div class="spinner-border text-primary" role="status"></div></div>');
                    // Check if cachedReports exists and has the report WITH DATA
                    console.log('cachedReports', self.cachedReports);
                    console.log('report', report);
                    if (Array.isArray(self.cachedReports) && self.cachedReports.length > 0) {
                        const rep = self.cachedReports.find(r => r.slug === report.slug);
                        if (rep) {
                            console.log('Found in cache - rep:', rep);
                            console.log('Has data?', rep.data ? 'YES' : 'NO');
                            console.log('Data length:', rep.data ? rep.data.length : 'N/A');
                            
                            // Check if cached report has data, if not fetch it
                            if (rep.data && Array.isArray(rep.data) && rep.data.length > 0) {
                                console.log('Using cached data');
                                setTimeout(() => { self.updateReportsView(rep, rep.data); }, 300); // simulate async for spinner UX
                            } else {
                                console.log('Cached report has no data, fetching from API');
                                self.fetchAndDisplayReport(report.slug, row);
                            }
                        } else {
                            console.log('Report not in cache, fetching from API');
                            self.fetchAndDisplayReport(report.slug, row);
                        }
                    } else {
                        console.log('No cached reports, fetching from API');
                        self.fetchAndDisplayReport(report.slug, row);
                    }
                    // Highlight the current report in the history
                    $('.report-row').removeClass('table-primary');
                    row.addClass('table-primary');
                });
                tbody.append(row);
            });
        },

        // Helper to fetch report from API, update cache, and display
        fetchAndDisplayReport: function(reportSlug, rowElem) {
            console.log('fetchAndDisplayReport', reportSlug, rowElem);
            const reportsView = $('#reports-panel .col-md-8 .card-body');
            
            // Ensure loading spinner is visible
            reportsView.html('<div class="report-display-wrapper w-100"><div class="w-100 d-flex justify-content-center align-items-center" style="min-height:200px"><div class="spinner-border text-primary" role="status"></div></div></div>');
            
            this.ajaxWithAuth({
                url: `https://ai-backend.stagingwithswift.com/api/reports/${reportSlug}`,
                method: 'GET',
                success: (response) => {
                    console.log('fetchAndDisplayReport success - full response:', response);
                    console.log('fetchAndDisplayReport - report:', response.report);
                    console.log('fetchAndDisplayReport - data:', response.data);
                    console.log('fetchAndDisplayReport - data type:', typeof response.data);
                    console.log('fetchAndDisplayReport - data is array:', Array.isArray(response.data));
                    console.log('fetchAndDisplayReport - data length:', response.data ? response.data.length : 'null/undefined');
                    
                    // Add or update in cache
                    if (!Array.isArray(this.cachedReports)) this.cachedReports = [];
                    let idx = this.cachedReports.findIndex(r => r.slug === reportSlug);
                    if (idx > -1) {
                        this.cachedReports[idx] = Object.assign({}, response.report, { data: response.data });
                    } else {
                        this.cachedReports.push(Object.assign({}, response.report, { data: response.data }));
                    }
                    
                    // Check if data exists before displaying
                    if (!response.data || (Array.isArray(response.data) && response.data.length === 0)) {
                        console.warn('No data returned from backend for report:', reportSlug);
                        reportsView.find('.report-display-wrapper').html('<div class="w-100 text-center text-warning py-4"><i class="fa fa-exclamation-triangle"></i> Report completed but no data is available. The report may need to be re-executed.</div>');
                        return;
                    }
                    
                    console.log('Calling updateReportsView with:', response.report, response.data);
                    this.updateReportsView(response.report, response.data);
                    
                    // Highlight the current report in the history
                    $('.report-row').removeClass('table-primary');
                    if (rowElem) rowElem.addClass('table-primary');
                },
                error: (xhr, status, error) => {
                    console.error('fetchAndDisplayReport error:', reportSlug, status, error);
                    console.error('XHR:', xhr);
                    reportsView.find('.report-display-wrapper').html('<div class="w-100 text-center text-danger py-4">Failed to load report. Please try again.</div>');
                }
            });
        },

        /**
         * Display the current report in the reports view
         */
        displayCurrentReport: function(report) {
            // Update the reports view with the current report
            this.updateReportsView(report);
            
            // Highlight the current report in the history
            $(`.report-row[data-report-slug="${report.slug}"]`).addClass('table-primary');
        },

        /**
         * Display a specific report
         */
        displayReport: function(reportSlug) {
            const rep = this.cachedReports.find(r => r.slug === reportSlug);
            if (rep) {
                this.updateReportsView(rep, rep.data);
                // Highlight the current report in the history
                $('.report-row').removeClass('table-primary');
                $(`.report-row[data-report-slug="${reportSlug}"]`).addClass('table-primary');
            }
        },

        /**
         * Update the reports view with report data
         */
        updateReportsView: function(report, data = null) {
            const self = this;
            // Always destroy any existing chart/graph instances to prevent data mixup
            if (self.reportChartInstance) {
                self.reportChartInstance.destroy();
                self.reportChartInstance = null;
            }
            if (self.reportGraphInstance) {
                self.reportGraphInstance.destroy();
                self.reportGraphInstance = null;
            }
            // Destroy any existing VTE instance
            if (self._vteController && typeof self._vteController.destroy === 'function') {
                try {
                    self._vteController.destroy();
                } catch (e) {
                    console.error('Error destroying VTE controller:', e);
                }
                self._vteController = null;
            }
            const reportsView = $('#reports-panel .col-md-8 .card-body');
            // Inject custom styles for icon button animation, highlight, and fade-in
            if ($('#display-type-icon-style').length === 0) {
                $('head').append(`
                    <style id="display-type-icon-style">
                        .btn-icon {
                            transition: transform 0.15s cubic-bezier(.4,2,.6,1), background 0.2s, border 0.2s;
                            background: #f8f9fa;
                            border: 1.5px solid #e0e0e0;
                            color: #333;
                        }
                        .btn-icon:hover {
                            transform: scale(1.15);
                            background: #e9ecef;
                            border-color: #b3d7ff;
                            color: #007bff;
                            z-index: 2;
                        }
                        .btn-icon.active, .btn-icon:focus {
                            background: #e3f0ff;
                            border-color: #007bff;
                            color: #007bff;
                            box-shadow: 0 0 0 2px #b3d7ff33;
                        }
                        .display-fade {
                            opacity: 0;
                            transition: opacity 0.25s;
                        }
                        .display-fade.display-fade-in {
                            opacity: 1;
                        }
                    </style>
                `);
            }
            // Set the report description as the title
            $('.report-view-title').text(report.description);
            if (!Array.isArray(data) || data.length === 0) {
                reportsView.find('.report-display-wrapper').html('<div class="w-100 text-center text-muted py-4">No data available for this report.</div>');
                return;
            }
            const types = ['table', 'chart', 'graph'];
            const typeLabels = { table: 'Table', chart: 'Chart', graph: 'Graph' };
            const typeIcons = {
                table: '<i class="fa fa-table"></i>',
                chart: '<i class="fa fa-bar-chart"></i>',
                graph: '<i class="fa fa-line-chart"></i>'
            };
            // Controls row: export buttons (left), display icons (right)
            let controlsHtml = `
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <div class="action-buttons d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm export-csv-btn mr-10">
                            <i class="fa fa-download me-1"></i>Export CSV
                        </button>
                        <button class="btn btn-outline-secondary btn-sm export-json-btn">
                            <i class="fa fa-code me-1"></i>Export JSON
                        </button>
                    </div>
                    <div class="display-type-icons d-flex gap-2 align-items-center">
            `;
            types.forEach(type => {
                const active = report.display_type === type ? ' active' : '';
                controlsHtml += `<button class="btn btn-icon mr-10 mb-0${active}" data-type="${type}" title="${typeLabels[type]} View" style="font-size:1.5rem; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; padding:0;">${typeIcons[type]}</button>`;
            });
            controlsHtml += '</div></div>';
            // Display containers
            let displayHtml = '<div class="report-display-area w-100 position-relative" style="min-height:300px;">';
            types.forEach(type => {
                const isActive = report.display_type === type;
                let content = '';
                if (type === 'table') {
                    content = this.renderReportData(data);
                } else if (type === 'chart') {
                    content = '<div class="chart-container"><canvas id="reportChart"></canvas></div>';
                } else if (type === 'graph') {
                    content = '<div class="chart-container"><canvas id="reportGraph"></canvas></div>';
                }
                displayHtml += `<div class="display-fade${isActive ? ' display-fade-in' : ''}" data-type="${type}" style="position:absolute; top:0; left:0; width:100%;${isActive ? 'z-index:1;' : 'z-index:0; pointer-events:none;'}">${content}</div>`;
            });
            displayHtml += '</div>';
            reportsView.html(`
                ${controlsHtml}
                <div class="report-display-wrapper w-100 position-relative">
                    ${displayHtml}
                </div>
            `);
            
            // Initialize Vanilla Table Enhancer if table is active
            if (this._pendingTableId && report.display_type === 'table') {
                setTimeout(() => {
                    const tableElement = document.getElementById(this._pendingTableId);
                    if (tableElement && window.VTE) {
                        console.log('Initializing Vanilla Table Enhancer on table:', this._pendingTableId);
                        try {
                            this._vteController = window.VTE.enhance('#' + this._pendingTableId, {
                                perPage: 10,
                                perPageOptions: [10, 25, 50, 100],
                                labels: {
                                    search: 'Search',
                                    rows: 'Rows per page',
                                    info: (start, end, total) => `Showing ${start}–${end} of ${total} entries`,
                                    noData: 'No matching records found'
                                }
                            })[0];
                        } catch (e) {
                            console.error('Error initializing Vanilla Table Enhancer:', e);
                        }
                    }
                    this._pendingTableId = null;
                }, 100);
            }
            
            // Immediately initialize chart/graph if default is not table
            const initialType = report.display_type;
            if (initialType === 'chart') {
                setTimeout(() => {
                    const chartEl = document.getElementById('reportChart');
                    if (chartEl) {
                        const headers = Object.keys(data[0] || {});
                        // Find first string column for labels, first numeric for values (but skip id columns for values)
                        let labelKey = headers.find(h => typeof data[0][h] === 'string') || headers[0];
                        let valueKey = headers.find(h => typeof data[0][h] === 'number' && !/^([a-zA-Z0-9]*_)?id$/.test(h))
                            || headers.find(h => typeof data[0][h] === 'number' && !h.endsWith('_id'))
                            || headers.find(h => typeof data[0][h] === 'number')
                            || headers[1];
                        const labels = data.map(r => r[labelKey]);
                        const values = data.map(r => r[valueKey]);
                        if (self.reportChartInstance) { self.reportChartInstance.destroy(); }
                        self.reportChartInstance = new Chart(chartEl.getContext('2d'), {
                            type: 'bar',
                            data: { labels: labels, datasets: [{ label: valueKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()), data: values, backgroundColor: 'rgba(0,123,255,0.5)', borderColor: 'rgba(0,123,255,1)', borderWidth: 1 }] },
                            options: { responsive: true, maintainAspectRatio: false }
                        });
                    }
                }, 100);
            } else if (initialType === 'graph') {
                setTimeout(() => {
                    const graphEl = document.getElementById('reportGraph');
                    if (graphEl) {
                        const headers = Object.keys(data[0] || {});
                        // Find first string column for labels, first numeric for values (but skip id columns for values)
                        let labelKey = headers.find(h => typeof data[0][h] === 'string') || headers[0];
                        let valueKey = headers.find(h => typeof data[0][h] === 'number' && !/^([a-zA-Z0-9]*_)?id$/.test(h))
                            || headers.find(h => typeof data[0][h] === 'number' && !h.endsWith('_id'))
                            || headers.find(h => typeof data[0][h] === 'number')
                            || headers[1];
                        const labels = data.map(r => r[labelKey]);
                        const values = data.map(r => r[valueKey]);
                        if (self.reportGraphInstance) { self.reportGraphInstance.destroy(); }
                        self.reportGraphInstance = new Chart(graphEl.getContext('2d'), {
                            type: 'line',
                            data: { labels: labels, datasets: [{ label: valueKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()), data: values, backgroundColor: 'rgba(40,167,69,0.5)', borderColor: 'rgba(40,167,69,1)', borderWidth: 1 }] },
                            options: { responsive: true, maintainAspectRatio: false }
                        });
                    }
                }, 100);
            }
            // Animate display change on icon click
            const iconButtons = reportsView.find('.display-type-icons .btn-icon');
            iconButtons.on('click', function() {
                const type = $(this).data('type');
                iconButtons.removeClass('active');
                $(this).addClass('active');
                // Animate fade out/in
                types.forEach(t => {
                    const $el = reportsView.find(`.display-fade[data-type="${t}"]`);
                    if (t === type) {
                        $el.addClass('display-fade-in').css({ 'z-index': 1, 'pointer-events': 'auto' });
                    } else {
                        $el.removeClass('display-fade-in').css({ 'z-index': 0, 'pointer-events': 'none' });
                    }
                });
                
                // If switching to table view and VTE not initialized, initialize it
                if (type === 'table' && !self._vteController && self._pendingTableId) {
                    setTimeout(() => {
                        const tableElement = document.getElementById(self._pendingTableId);
                        if (tableElement && window.VTE) {
                            console.log('Initializing Vanilla Table Enhancer on table (view switch):', self._pendingTableId);
                            try {
                                self._vteController = window.VTE.enhance('#' + self._pendingTableId, {
                                    perPage: 10,
                                    perPageOptions: [10, 25, 50, 100],
                                    labels: {
                                        search: 'Search',
                                        rows: 'Rows per page',
                                        info: (start, end, total) => `Showing ${start}–${end} of ${total} entries`,
                                        noData: 'No matching records found'
                                    }
                                })[0];
                            } catch (e) {
                                console.error('Error initializing Vanilla Table Enhancer on view switch:', e);
                            }
                        }
                    }, 100);
                }
                
                // If chart or graph, (re)initialize chart
                if (type === 'chart') {
                    setTimeout(() => {
                        const chartEl = document.getElementById('reportChart');
                        if (chartEl) {
                            const headers = Object.keys(data[0] || {});
                            // Find first string column for labels, first numeric for values (but skip id columns for values)
                            let labelKey = headers.find(h => typeof data[0][h] === 'string') || headers[0];
                            let valueKey = headers.find(h => typeof data[0][h] === 'number' && !/^([a-zA-Z0-9]*_)?id$/.test(h))
                                || headers.find(h => typeof data[0][h] === 'number' && !h.endsWith('_id'))
                                || headers.find(h => typeof data[0][h] === 'number')
                                || headers[1];
                            const labels = data.map(r => r[labelKey]);
                            const values = data.map(r => r[valueKey]);
                            if (self.reportChartInstance) { self.reportChartInstance.destroy(); }
                            self.reportChartInstance = new Chart(chartEl.getContext('2d'), {
                                type: 'bar',
                                data: { labels: labels, datasets: [{ label: valueKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()), data: values, backgroundColor: 'rgba(0,123,255,0.5)', borderColor: 'rgba(0,123,255,1)', borderWidth: 1 }] },
                                options: { responsive: true, maintainAspectRatio: false }
                            });
                        }
                    }, 100);
                } else if (type === 'graph') {
                    setTimeout(() => {
                        const graphEl = document.getElementById('reportGraph');
                        if (graphEl) {
                            const headers = Object.keys(data[0] || {});
                            // Find first string column for labels, first numeric for values (but skip id columns for values)
                            let labelKey = headers.find(h => typeof data[0][h] === 'string') || headers[0];
                            let valueKey = headers.find(h => typeof data[0][h] === 'number' && !/^([a-zA-Z0-9]*_)?id$/.test(h))
                                || headers.find(h => typeof data[0][h] === 'number' && !h.endsWith('_id'))
                                || headers.find(h => typeof data[0][h] === 'number')
                                || headers[1];
                            const labels = data.map(r => r[labelKey]);
                            const values = data.map(r => r[valueKey]);
                            if (self.reportGraphInstance) { self.reportGraphInstance.destroy(); }
                            self.reportGraphInstance = new Chart(graphEl.getContext('2d'), {
                                type: 'line',
                                data: { labels: labels, datasets: [{ label: valueKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()), data: values, backgroundColor: 'rgba(40,167,69,0.5)', borderColor: 'rgba(40,167,69,1)', borderWidth: 1 }] },
                                options: { responsive: true, maintainAspectRatio: false }
                            });
                        }
                    }, 100);
                }
                // Save display type if changed
                if (type !== report.display_type) {
                    if (reportsView.find('.save-display-type-btn').length === 0) {
                        reportsView.find('.display-type-icons').append('<button class="btn btn-icon save-display-type-btn" style="font-size:1.5rem; width:40px; height:40px; border-radius:50%;"><i class="fa fa-save"></i></button>');
                        reportsView.find('.save-display-type-btn').on('click', function() { self.saveDisplayType(report.slug, type); });
                    }
                } else {
                    reportsView.find('.save-display-type-btn').remove();
                }
            });
            // Export buttons
            reportsView.find('.export-csv-btn').on('click', () => self.exportReport(report.slug, 'csv'));
            reportsView.find('.export-json-btn').on('click', () => self.exportReport(report.slug, 'json'));
            
            // Compare Data button - Disabled for v2
            // setTimeout(function() {
            //     if ($('.compare-data-btn').length === 0) {
            //         $('.display-type-icons').after('<button class="btn btn-outline-info btn-sm compare-data-btn ms-2"><i class="fa fa-balance-scale"></i> Compare Data</button>');
            //         $('.compare-data-btn').on('click', function() {
            //             self.showCompareDataModal(report);
            //         });
            //     }
            // }, 100);
        },

        /**
         * Render report data as a table
         */
        renderReportData: function(data) {
            if (!data || data.length === 0) {
                return '<p class="text-muted">No data available.</p>';
            }
            
            const headers = Object.keys(data[0]);
            const tableId = 'enhanced-report-table-' + Date.now();
            let tableHtml = `<div class="table-responsive"><table id="${tableId}" class="table table-striped table-hover">`;
            
            // Add headers with data type hints for proper sorting
            tableHtml += '<thead><tr>';
            headers.forEach(header => {
                // Detect if column contains numeric data
                const isNumeric = data.every(row => {
                    const val = row[header];
                    return val === null || val === undefined || val === '' || !isNaN(parseFloat(val));
                });
                
                // Detect if column contains date data
                const isDate = !isNumeric && data.some(row => {
                    const val = row[header];
                    return val && !isNaN(Date.parse(val));
                });
                
                const dataType = isNumeric ? ' data-vte="number"' : (isDate ? ' data-vte="date"' : '');
                tableHtml += `<th${dataType}>${header.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</th>`;
            });
            tableHtml += '</tr></thead>';
            
            // Add data rows
            tableHtml += '<tbody>';
            data.forEach(row => {
                tableHtml += '<tr>';
                headers.forEach(header => {
                    tableHtml += `<td>${row[header] || ''}</td>`;
                });
                tableHtml += '</tr>';
            });
            tableHtml += '</tbody></table></div>';
            
            // Store table ID for enhancement after DOM insertion
            this._pendingTableId = tableId;
            
            return tableHtml;
        },

        /**
         * Get status badge HTML
         */
        getStatusBadge: function(status) {
            const badgeClass = this.getStatusBadgeClass(status);
            return `<span class="badge ${badgeClass}" style="color: white;">${status}</span>`;
        },

        /**
         * Get status badge CSS class
         */
        getStatusBadgeClass: function(status) {
            switch (status) {
                case 'completed': return 'bg-success';
                case 'processing': return 'bg-warning text-dark';
                case 'pending': return 'bg-info';
                case 'failed': return 'bg-danger';
                default: return 'bg-secondary';
            }
        },

        /**
         * Execute a pending report
         */
        executeReport: function(reportSlug) {
            // Show loading indicator
            const reportRow = $(`.report-row[data-report-slug="${reportSlug}"]`);
            const originalContent = reportRow.html();
            reportRow.html('<td colspan="3" class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Executing...</span></div> Executing report...</td>');
            
            this.ajaxWithAuth({
                url: `https://ai-backend.stagingwithswift.com/api/reports/${reportSlug}/execute`,
                method: 'POST',
                success: (response) => {
                    if (response.success) {
                        // Update the cached report status
                        if (Array.isArray(this.cachedReports)) {
                            const reportIndex = this.cachedReports.findIndex(r => r.slug === reportSlug);
                            if (reportIndex !== -1) {
                                this.cachedReports[reportIndex] = Object.assign({}, this.cachedReports[reportIndex], response.report);
                            }
                        }
                        
                        // Refresh the report display
                        setTimeout(() => {
                            this.fetchAndDisplayReport(reportSlug, reportRow);
                        }, 1000); // Small delay to show completion
                        
                        this.showSuccess('Report executed successfully!');
                    } else {
                        reportRow.html(originalContent);
                        this.showError('Failed to execute report: ' + (response.error || 'Unknown error'));
                    }
                },
                error: (xhr, status, error) => {
                    reportRow.html(originalContent);
                    
                    let errorMessage = 'Failed to execute report';
                    
                    // Handle different error scenarios
                    if (xhr.status === 500) {
                        errorMessage = 'Server error occurred while executing the report. Please check the SQL query and try again.';
                    } else if (xhr.status === 403) {
                        errorMessage = 'You do not have permission to execute this report.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Report not found.';
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    } else {
                        errorMessage += ': ' + error;
                    }
                    
                    console.error('Report execution failed:', {
                        status: xhr.status,
                        error: error,
                        response: xhr.responseText
                    });
                    
                    this.showError(errorMessage);
                }
            });
        },

        /**
         * Export report
         */
        exportReport: function(reportSlug, format) {
            const url = `https://ai-backend.stagingwithswift.com/api/reports/${reportSlug}/export/${format}`;
            const token = sessionStorage.getItem('ai_token');
            if (!token) {
                this.showError('You must be logged in to export reports.');
                return;
            }
            // Show loader
            Swal.fire({
                title: 'Exporting report...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            fetch(url, {
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('Export failed');
                return response.blob();
            })
            .then(blob => {
                Swal.close();
                const link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = `report-${reportSlug}.${format}`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            })
            .catch(() => {
                Swal.close();
                this.showError('Failed to export report.');
            });
        },

        /**
         * Open report from chat link
         */
        openReportFromLink: function(reportSlug) {
            console.log('openReportFromLink called with slug:', reportSlug);
            
            // Switch to reports tab
            this.switchToReportsTab();
            
            // Refresh history from cache
            this.updateReportsHistory(this.cachedReports);
            
            // Highlight the report row
            $('.report-row').removeClass('table-primary');
            const reportRow = $(`.report-row[data-report-slug="${reportSlug}"]`);
            reportRow.addClass('table-primary');
            
            // Check if report has full data in cache
            const cached = this.cachedReports.find(r => r.slug === reportSlug);
            console.log('Cached report found:', cached);
            console.log('Has data?', cached && cached.data ? 'YES' : 'NO');
            console.log('Data length:', cached && cached.data ? cached.data.length : 'N/A');
            
            // If cached report has data, use it; otherwise fetch from API
            if (cached && cached.data && Array.isArray(cached.data) && cached.data.length > 0) {
                console.log('Using cached data for report:', reportSlug);
                this.updateReportsView(cached, cached.data);
            } else {
                console.log('Fetching report data from API:', reportSlug);
                this.fetchAndDisplayReport(reportSlug, reportRow);
            }
        },

        /**
         * Format timestamps like WhatsApp (today: HH:mm, yesterday: Weekday - HH:mm, same year: D Month - HH:mm, previous years: D Month YYYY - HH:mm)
         */
        formatTimestamp: function(ts) {
            const date = (ts instanceof Date) ? ts : new Date(ts);
            const now = new Date();
            const pad = (n) => n.toString().padStart(2, '0');
            const time = `${pad(date.getHours())}:${pad(date.getMinutes())}`;
            // Different years
            if (date.getFullYear() !== now.getFullYear()) {
                const month = date.toLocaleString('default', { month: 'long' });
                return `${date.getDate()} ${month} ${date.getFullYear()} - ${time}`;
            }
            // Today
            if (date.toDateString() === now.toDateString()) {
                return time;
            }
            // Yesterday
            const yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
            if (date.toDateString() === yesterday.toDateString()) {
                const weekday = date.toLocaleString('default', { weekday: 'long' });
                return `${weekday} - ${time}`;
            }
            // Earlier this year
            const month = date.toLocaleString('default', { month: 'long' });
            return `${date.getDate()} ${month} - ${time}`;
        },

        /**
         * Increment or add report badge count in chat history list
         */
        updateChatHistoryBadge: function(chatId) {
            const $item = $(`#chat-history-list li.chat-history-item[data-chat-id="${chatId}"]`);
            if (!$item.length) return;
            let $badge = $item.find('.report-count');
            if ($badge.length) {
                const current = parseInt($badge.text(), 10) || 0;
                $badge.text(current + 1);
            } else {
                const $newBadge = $(`<span class="badge bg-primary d-flex align-items-center report-count" style="color: white;"><i class="fa fa-chart-bar me-1"></i>1</span>`);
                $item.append($newBadge);
            }
        },

        /**
         * Persist display type to server
         */
        saveDisplayType: function(reportSlug, displayType) {
            const self = this;
            // Show SweetAlert loader
            Swal.fire({
                title: 'Saving default view...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            this.ajaxWithAuth({
                url: `https://ai-backend.stagingwithswift.com/api/reports/${reportSlug}/display-type`,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ display_type: displayType }),
                success: () => {
                    // Show success toast
                    Swal.fire({ icon: 'success', title: 'Default view saved', showConfirmButton: false, timer: 1500 });
                    // Update cache and hide save button
                    const rep = this.cachedReports.find(r => r.slug === reportSlug);
                    if (rep) { rep.display_type = displayType; }
                    $('.save-display-type-btn').remove();
                },
                error: () => {
                    // Show error toast
                    Swal.fire({ icon: 'error', title: 'Save failed', text: 'Could not save default view. Please try again.' });
                }
            });
        },

        // New method to show resend icon on a specific user message
        showResendIconOnMessage: function($messageElement, messageText) {
            if (!$messageElement || !$messageElement.length) {
                return;
            }
            
            // Remove any existing resend icons on this message
            $messageElement.siblings('.failed-reload-icon').remove();
                
                // Create retry icon
                const $icon = $(`
                    <i class="fa fa-refresh failed-reload-icon text-danger"
                   title="Retry sending this message"
                       style="cursor:pointer; float:left; margin-right:0.5rem;"></i>
                `);
                
                // Insert it to the left of the user-message bubble
            $messageElement.after($icon);
                
                // Bind click event to resend the message
                $icon.on('click', () => {
                    $icon.remove();
                this.resendMessage($messageElement, messageText);
            });
        },

        // Legacy method for backward compatibility - shows resend icon on last user message
        showResendIconOnLastUserMessage: function() {
            const $lastUserMessage = $('#chat-container .user-message:last');
            if ($lastUserMessage.length) {
                // Get the message text from the last user message
                const messageText = $lastUserMessage.find('.message-text').text();
                this.showResendIconOnMessage($lastUserMessage, messageText);
            }
        },

        /**
         * Show the Compare Data modal for the current report
         */
        showCompareDataModal: function(report) {
            const self = this;
            const reportSlug = report.slug;
            let timeline = [];
            let currentData = null;
            let selectedA = null;
            let selectedB = null;
            let loadingA = false;
            let loadingB = false;
            let error = null;
            let displayTypeA = 'table';
            let displayTypeB = 'table';
            let currentSnapshotId = null;
            let compareDataA = null;
            let compareDataB = null;
            let currentDataFetched = false;
            let nextSection = 'a'; // Alternating selection

            // Helper: fetch timeline
            function fetchTimeline(cb) {
                self.ajaxWithAuth({
                    url: `https://ai-backend.stagingwithswift.com/api/reports/${reportSlug}/snapshots`,
                    method: 'GET',
                    success: function(res) {
                        timeline = res.snapshots || [];
                        currentSnapshotId = (timeline.find(s => s.is_current_version) || {}).id || null;
                        console.log('[DEBUG] Timeline fetched:', timeline);
                        if (cb) cb();
                    },
                    error: function() {
                        error = 'Failed to load timeline.';
                        console.error('[DEBUG] Timeline fetch error:', error);
                        if (cb) cb();
                    }
                });
            }

            // Helper: fetch current data for A or B
            function fetchCurrent(section, cb) {
                if (section === 'a') loadingA = true;
                if (section === 'b') loadingB = true;
                renderSections();
                self.ajaxWithAuth({
                    url: `https://ai-backend.stagingwithswift.com/api/reports/${reportSlug}/data/current`,
                    method: 'GET',
                    success: function(res) {
                        currentData = res.data || [];
                        if (section === 'a') {
                        compareDataA = currentData;
                        selectedA = 'current';
                            loadingA = false;
                        } else {
                            compareDataB = currentData;
                            selectedB = 'current';
                            loadingB = false;
                        }
                        currentDataFetched = true;
                        renderSections();
                        if (cb) cb();
                    },
                    error: function() {
                        error = 'Failed to fetch current data.';
                        if (section === 'a') loadingA = false;
                        if (section === 'b') loadingB = false;
                        renderSections();
                        if (cb) cb();
                    }
                });
            }

            // Helper: fetch a single snapshot's data for A or B
            function fetchSnapshotData(snapId, section, cb) {
                if (section === 'a') loadingA = true;
                if (section === 'b') loadingB = true;
                renderSections();
                self.ajaxWithAuth({
                    url: `https://ai-backend.stagingwithswift.com/api/reports/${reportSlug}/snapshots/${snapId}`,
                    method: 'GET',
                    success: function(res) {
                        if (section === 'a') {
                            compareDataA = res.data;
                            selectedA = snapId;
                            loadingA = false;
                        } else {
                            compareDataB = res.data;
                            selectedB = snapId;
                            loadingB = false;
                        }
                        renderSections();
                        if (cb) cb();
                    },
                    error: function() {
                        error = 'Failed to fetch snapshot data.';
                        if (section === 'a') loadingA = false;
                        if (section === 'b') loadingB = false;
                        renderSections();
                        if (cb) cb();
                    }
                });
            }

            // Helper: compare two snapshots (for diff table)
            function fetchCompare(aId, bId, cb) {
                loadingA = true;
                loadingB = true;
                renderSections();
                self.ajaxWithAuth({
                    url: `https://ai-backend.stagingwithswift.com/api/reports/${reportSlug}/snapshots/${aId}/compare/${bId}`,
                    method: 'GET',
                    success: function(res) {
                        compareDataA = res.a.data;
                        compareDataB = res.b.data;
                        loadingA = false;
                        loadingB = false;
                        renderSections();
                        if (cb) cb(res);
                    },
                    error: function() {
                        error = 'Failed to compare snapshots.';
                        loadingA = false;
                        loadingB = false;
                        renderSections();
                        if (cb) cb();
                    }
                });
            }

            // Helper: get color and note
            function getSnapshotColor(snapId) {
                if (!snapId) return { bg: '#f8f9fa', border: '#dee2e6', text: '#6c757d' };
                const snapIndex = timeline.findIndex(s => s.id === snapId);
                if (snapIndex === -1) return { bg: '#f8f9fa', border: '#dee2e6', text: '#6c757d' };
                const colors = [
                    { bg: '#e3f2fd', border: '#2196f3', text: '#1976d2' },
                    { bg: '#f3e5f5', border: '#9c27b0', text: '#7b1fa2' },
                    { bg: '#e8f5e8', border: '#4caf50', text: '#388e3c' },
                    { bg: '#fff3e0', border: '#ff9800', text: '#f57c00' },
                    { bg: '#fce4ec', border: '#e91e63', text: '#c2185b' },
                    { bg: '#e0f2f1', border: '#009688', text: '#00695c' },
                    { bg: '#f1f8e9', border: '#8bc34a', text: '#689f38' },
                    { bg: '#fff8e1', border: '#ffc107', text: '#ffa000' }
                ];
                return colors[snapIndex % colors.length];
            }
            function getSnapshotNote(snapId) {
                if (!snapId) return '';
                if (typeof snapId === 'string' && snapId === 'current') return 'Current Data';
                const snap = timeline.find(s => s.id === snapId);
                return snap && snap.note ? snap.note : '';
            }

            // Render section A
            function renderSectionA() {
                let html = '';
                const colorA = getSnapshotColor(selectedA);
                const noteA = getSnapshotNote(selectedA);
                html += `<div class="w-100 droppable-snapshot compare-section" id="compare-section-a" data-drop-target="a" style="border: 2px solid ${colorA.border}; border-radius: 8px; padding: 12px; background-color: ${colorA.bg}; height: 100%; min-height: 300px; position:relative;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold" style="color: ${colorA.text};">Snapshot A${noteA ? ': ' + noteA : ''}</span>
                        <div class="btn-group display-type-switcher-a" role="group" aria-label="Display type switcher">
                            <button class="btn btn-icon btn-sm${displayTypeA === 'table' ? ' active' : ''}" data-type="table" data-side="a" tabindex="0" aria-label="Table view" title="Table view"><i class="fa fa-table"></i></button>
                            <button class="btn btn-icon btn-sm${displayTypeA === 'chart' ? ' active' : ''}" data-type="chart" data-side="a" tabindex="0" aria-label="Chart view" title="Chart view"><i class="fa fa-bar-chart"></i></button>
                            <button class="btn btn-icon btn-sm${displayTypeA === 'graph' ? ' active' : ''}" data-type="graph" data-side="a" tabindex="0" aria-label="Graph view" title="Graph view"><i class="fa fa-line-chart"></i></button>
                            </div>
                        </div>`;
                if (loadingA) {
                    html += '<div class="w-100 d-flex justify-content-center align-items-center" style="min-height:200px"><div class="spinner-border text-primary" role="status"></div></div>';
                } else if (compareDataA) {
                    html += `<div class="comparison-fade${displayTypeA ? ' comparison-fade-in' : ''}" style="animation:fadeIn .3s; overflow-x: auto; max-height: calc(100% - 50px);">`;
                    if (displayTypeA === 'table') {
                        html += self.renderDiffTable(compareDataA, compareDataB);
                    } else if (displayTypeA === 'chart') {
                        html += `<div class="chart-container" style="min-height:260px;" tabindex="0" aria-label="Bar chart" role="region"><canvas id="compareChartA"></canvas></div>`;
                    } else if (displayTypeA === 'graph') {
                        html += `<div class="chart-container" style="min-height:260px;" tabindex="0" aria-label="Line graph" role="region"><canvas id="compareGraphA"></canvas></div>`;
                    }
                    html += '</div>';
                } else {
                    html += `<div class="text-muted small text-center" style="min-height:200px;">No snapshot selected for A</div>`;
                }
                html += '</div>';
                return html;
            }
            // Render section B
            function renderSectionB() {
                let html = '';
                const colorB = getSnapshotColor(selectedB);
                const noteB = getSnapshotNote(selectedB);
                html += `<div class="w-100 droppable-snapshot compare-section" id="compare-section-b" data-drop-target="b" style="border: 2px solid ${colorB.border}; border-radius: 8px; padding: 12px; background-color: ${colorB.bg}; height: 100%; min-height: 300px; position:relative;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold" style="color: ${colorB.text};">Snapshot B${noteB ? ': ' + noteB : ''}</span>
                        <div class="btn-group display-type-switcher-b" role="group" aria-label="Display type switcher B">
                            <button class="btn btn-icon btn-sm${displayTypeB === 'table' ? ' active' : ''}" data-type="table" data-side="b" tabindex="0" aria-label="Table view for B" title="Table view"><i class="fa fa-table"></i></button>
                            <button class="btn btn-icon btn-sm${displayTypeB === 'chart' ? ' active' : ''}" data-type="chart" data-side="b" tabindex="0" aria-label="Chart view for B" title="Chart view"><i class="fa fa-bar-chart"></i></button>
                            <button class="btn btn-icon btn-sm${displayTypeB === 'graph' ? ' active' : ''}" data-type="graph" data-side="b" tabindex="0" aria-label="Graph view for B" title="Graph view"><i class="fa fa-line-chart"></i></button>
                        </div>
                    </div>`;
                if (loadingB) {
                    html += '<div class="w-100 d-flex justify-content-center align-items-center" style="min-height:200px"><div class="spinner-border text-primary" role="status"></div></div>';
                } else if (compareDataB) {
                    html += `<div class="comparison-fade${displayTypeB ? ' comparison-fade-in' : ''}" style="animation:fadeIn .3s; overflow-x: auto; max-height: calc(100% - 50px);">`;
                    if (displayTypeB === 'table') {
                        html += self.renderDiffTable(compareDataB, compareDataA);
                    } else if (displayTypeB === 'chart') {
                        html += `<div class="chart-container" style="min-height:260px;" tabindex="0" aria-label="Bar chart for B" role="region"><canvas id="compareChartB"></canvas></div>`;
                    } else if (displayTypeB === 'graph') {
                        html += `<div class="chart-container" style="min-height:260px;" tabindex="0" aria-label="Line graph for B" role="region"><canvas id="compareGraphB"></canvas></div>`;
                    }
                    html += '</div>';
                    } else {
                    html += `<div class="text-muted small text-center" style="min-height:200px;">No snapshot selected for B</div>`;
                }
                html += '</div>';
                return html;
            }

            // Render both sections
            function renderSections() {
                $('#compare-section-a').replaceWith(renderSectionA());
                $('#compare-section-b').replaceWith(renderSectionB());
                // Chart.js rendering for A
                if (displayTypeA === 'chart' && Array.isArray(compareDataA) && compareDataA.length > 0) {
                    const ctxA = document.getElementById('compareChartA');
                    if (ctxA) {
                        const headers = Object.keys(compareDataA[0]);
                        let labelKey = headers.find(h => typeof compareDataA[0][h] === 'string') || headers[0];
                        let valueKey = headers.find(h => typeof compareDataA[0][h] === 'number') || headers[1];
                        const labels = compareDataA.map(r => r[labelKey]);
                        const values = compareDataA.map(r => r[valueKey]);
                        if (window.compareChartAInstance) window.compareChartAInstance.destroy();
                        window.compareChartAInstance = new Chart(ctxA.getContext('2d'), {
                            type: 'bar',
                            data: { labels: labels, datasets: [{ label: valueKey, data: values, backgroundColor: 'rgba(0,123,255,0.5)', borderColor: 'rgba(0,123,255,1)', borderWidth: 1 }] },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });
                    }
                }
                if (displayTypeA === 'graph' && Array.isArray(compareDataA) && compareDataA.length > 0) {
                    const ctxA = document.getElementById('compareGraphA');
                    if (ctxA) {
                        const headers = Object.keys(compareDataA[0]);
                        let labelKey = headers.find(h => typeof compareDataA[0][h] === 'string') || headers[0];
                        let valueKey = headers.find(h => typeof compareDataA[0][h] === 'number') || headers[1];
                        const labels = compareDataA.map((r, i) => r[labelKey] || i + 1);
                        const values = compareDataA.map(r => r[valueKey]);
                        if (window.compareGraphAInstance) window.compareGraphAInstance.destroy();
                        window.compareGraphAInstance = new Chart(ctxA.getContext('2d'), {
                            type: 'line',
                            data: { labels: labels, datasets: [{ label: valueKey, data: values, backgroundColor: 'rgba(40,167,69,0.5)', borderColor: 'rgba(40,167,69,1)', borderWidth: 2, fill: false }] },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });
                    }
                }
                // Chart.js rendering for B
                if (displayTypeB === 'chart' && Array.isArray(compareDataB) && compareDataB.length > 0) {
                    const ctxB = document.getElementById('compareChartB');
                    if (ctxB) {
                        const headers = Object.keys(compareDataB[0]);
                        let labelKey = headers.find(h => typeof compareDataB[0][h] === 'string') || headers[0];
                        let valueKey = headers.find(h => typeof compareDataB[0][h] === 'number') || headers[1];
                        const labels = compareDataB.map(r => r[labelKey]);
                        const values = compareDataB.map(r => r[valueKey]);
                        if (window.compareChartBInstance) window.compareChartBInstance.destroy();
                        window.compareChartBInstance = new Chart(ctxB.getContext('2d'), {
                            type: 'bar',
                            data: { labels: labels, datasets: [{ label: valueKey, data: values, backgroundColor: 'rgba(0,123,255,0.5)', borderColor: 'rgba(0,123,255,1)', borderWidth: 1 }] },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });
                    }
                }
                if (displayTypeB === 'graph' && Array.isArray(compareDataB) && compareDataB.length > 0) {
                    const ctxB = document.getElementById('compareGraphB');
                    if (ctxB) {
                        const headers = Object.keys(compareDataB[0]);
                        let labelKey = headers.find(h => typeof compareDataB[0][h] === 'string') || headers[0];
                        let valueKey = headers.find(h => typeof compareDataB[0][h] === 'number') || headers[1];
                        const labels = compareDataB.map((r, i) => r[labelKey] || i + 1);
                        const values = compareDataB.map(r => r[valueKey]);
                        if (window.compareGraphBInstance) window.compareGraphBInstance.destroy();
                        window.compareGraphBInstance = new Chart(ctxB.getContext('2d'), {
                            type: 'line',
                            data: { labels: labels, datasets: [{ label: valueKey, data: values, backgroundColor: 'rgba(40,167,69,0.5)', borderColor: 'rgba(40,167,69,1)', borderWidth: 2, fill: false }] },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });
                    }
                }
            }

            // Helper: render timeline sidebar (unchanged)
            function renderTimeline(selectedAId, selectedBId) {
                console.log('[DEBUG] renderTimeline called. Timeline:', timeline);
                let html = '<div class="timeline-sidebar" style="width:220px;max-width:220px;overflow-y:auto;height:100%;background:#f8f9fa;border-right:1.5px solid #e0e0e0;padding:0.5rem 0.25rem;">';
                html += '<div class="d-flex flex-column gap-2 mb-3">';
                html += `<button class="btn btn-outline-primary btn-sm w-100 mb-1 fetch-current-btn" title="Fetch current data from database" aria-label="Fetch current data"><i class="fa fa-sync"></i> Fetch Current Data</button>`;
                const isCurrentDataInContext = currentDataFetched && selectedA === 'current';
                if (isCurrentDataInContext) {
                    html += `<div class="mb-2">
                        <input type="text" id="add-timeline-note" class="form-control form-control-sm mb-1" placeholder="Enter snapshot name (required)" required aria-label="Snapshot name">
                        <div class="invalid-feedback" style="display:none;">Snapshot name is required.</div>
                        <button class="btn btn-outline-success btn-sm w-100 add-timeline-btn" id="add-timeline-btn" title="Create snapshot from current data" aria-label="Create snapshot from current data" disabled><span class="btn-label"><i class="fa fa-plus"></i> Create Snapshot From Current Data</span><span class="spinner-border spinner-border-sm ms-2" id="snapshot-loading" style="display:none;" role="status" aria-hidden="true"></span></button>
                    </div>`;
                }
                html += '<div class="small text-muted text-center px-2 py-1 mb-2" style="border: 1px dashed #dee2e6; border-radius: 4px;">Click or <b>drag</b> snapshots to select for comparison. Each snapshot gets a unique color. You can also drag and drop from the side menu into the snapshot viewer.</div>';
                html += '</div>';
                html += '<div class="timeline-list">';
                if (timeline.length === 0) {
                    if (error) {
                        html += `<div class="text-danger small text-center py-2">Error: ${error}</div>`;
                    } else {
                        html += '<div class="text-muted small text-center py-2">No timeline snapshots yet. [DEBUG: Timeline is empty]</div>';
                    }
                } else {
                    timeline.forEach((snap, index) => {
                        // Color coding system
                        const colors = [
                            { bg: '#e3f2fd', border: '#2196f3', text: '#1976d2' }, // Light blue
                            { bg: '#f3e5f5', border: '#9c27b0', text: '#7b1fa2' }, // Light purple
                            { bg: '#e8f5e8', border: '#4caf50', text: '#388e3c' }, // Light green
                            { bg: '#fff3e0', border: '#ff9800', text: '#f57c00' }, // Light orange
                            { bg: '#fce4ec', border: '#e91e63', text: '#c2185b' }, // Light pink
                            { bg: '#e0f2f1', border: '#009688', text: '#00695c' }, // Light teal
                            { bg: '#f1f8e9', border: '#8bc34a', text: '#689f38' }, // Light lime
                            { bg: '#fff8e1', border: '#ffc107', text: '#ffa000' }  // Light amber
                        ];
                        const colorIndex = index % colors.length;
                        const color = colors[colorIndex];
                        const isSelected = selectedAId === snap.id || selectedBId === snap.id;
                        const selectedStyle = isSelected ? 
                            `background-color: ${color.bg}; border-color: ${color.border}; border-width: 2px;` : 
                            `background-color: #fff; border-color: #dee2e6; border-width: 1px;`;
                        const isCurrent = snap.is_current_version ? '<span class="badge bg-primary ms-1">Current</span>' : '';
                        // Drag and drop attributes
                        const dragDisabled = isSelected ? 'draggable="false"' : 'draggable="true"';
                        const dragClass = isSelected ? 'drag-disabled' : 'draggable-snapshot';
                        const dragTitle = isSelected ? 'This snapshot is currently selected' : 'Drag to assign to Snapshot A or B';
                        const dragText = isSelected ? '<span class="text-muted small">(Selected)</span>' : '';
                        html += `<div class="timeline-item p-2 mb-1 rounded border ${dragClass}" 
                                      style="cursor:pointer; animation:fadeIn .3s; ${selectedStyle}" 
                                      data-snap-id="${snap.id}" 
                                      data-color-bg="${color.bg}" 
                                      data-color-border="${color.border}" 
                                      data-color-text="${color.text}"
                                      tabindex="0" 
                                      aria-label="Snapshot from ${self.formatTimestamp(snap.created_at)}${snap.is_current_version ? ' (current)' : ''}"
                                      ${dragDisabled}
                                      title="${dragTitle}"
                                      >
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold small">${self.formatTimestamp(snap.created_at)}</span>
                                ${isCurrent}
                            </div>
                            <div class="small text-muted">${snap.note ? snap.note : ''} ${dragText}</div>
                            <div class="d-flex gap-1 mt-1">
                                <button class="btn btn-outline-secondary btn-xs btn-sm set-current-btn" data-snap-id="${snap.id}" title="Set as current version" aria-label="Set as current"><i class="fa fa-check"></i></button>
                            </div>
                        </div>`;
                    });
                }
                html += '</div></div>';
                return html;
            }

            // Helper: render modal
            function renderModal() {
                Swal.update({
                    html: `<div class='d-flex flex-row' style='height:70vh;min-height:500px;max-height:80vh;'>
                        <div id='timeline-sidebar-modal'></div>
                        <div class='flex-fill px-3' style='overflow:auto;max-width:calc(100% - 220px);'>
                            <div class='d-flex flex-row gap-3 w-100 h-100'>
                                <div class='flex-fill' id='compare-section-a'></div>
                                <div class='flex-fill' id='compare-section-b'></div>
                            </div>
                        </div>
                    </div>`
                });
                $('#timeline-sidebar-modal').html(renderTimeline(selectedA, selectedB));
                renderSections();
                // Bind timeline actions
                $('.fetch-current-btn').off('click').on('click', function() {
                    fetchCurrent(nextSection);
                });
                // Timeline item click handler for alternating selection
                $('.timeline-item').off('click').on('click', function(e) {
                    const snapId = $(this).data('snap-id');
                    if (nextSection === 'a') {
                        fetchSnapshotData(snapId, 'a', () => {
                            nextSection = 'b';
                        });
                    } else {
                        fetchSnapshotData(snapId, 'b', () => {
                            nextSection = 'a';
                        });
                    }
                });
                // Display type switchers
                $('.display-type-switcher-a .btn-icon').off('click').on('click', function() {
                    displayTypeA = $(this).data('type');
                    renderSections();
                });
                $('.display-type-switcher-b .btn-icon').off('click').on('click', function() {
                    displayTypeB = $(this).data('type');
                    renderSections();
                });
                // Drag and drop logic
                $('.draggable-snapshot').attr('draggable', true).off('dragstart').on('dragstart', function(e) {
                    e.originalEvent.dataTransfer.setData('text/plain', $(this).data('snap-id'));
                    $(this).addClass('dragging');
                });
                $('.draggable-snapshot').off('dragend').on('dragend', function(e) {
                    $(this).removeClass('dragging');
                });
                // Section highlight on drag over
                $('.compare-section').off('dragover').on('dragover', function(e) {
                    e.preventDefault();
                    $(this).addClass('drag-over-section');
                });
                $('.compare-section').off('dragleave').on('dragleave', function(e) {
                    $(this).removeClass('drag-over-section');
                });
                $('.compare-section').off('drop').on('drop', function(e) {
                    e.preventDefault();
                    $(this).removeClass('drag-over-section');
                    const snapId = e.originalEvent.dataTransfer.getData('text/plain');
                    const target = $(this).data('drop-target');
                    fetchSnapshotData(snapId, target, () => {
                        nextSection = target === 'a' ? 'b' : 'a';
                    });
                });
                // Add highlight CSS if not present
                if ($('#compare-section-highlight-style').length === 0) {
                    $('head').append('<style id="compare-section-highlight-style">.drag-over-section { box-shadow: 0 0 0 4px #90caf9 !important; border-color: #1976d2 !important; }</style>');
                }
            }

            // Show modal
            Swal.fire({
                title: `<span class='fw-bold'>Compare Report Data</span>`,
                html: `<div class='d-flex flex-row' style='height:60vh;min-height:400px;max-height:70vh;'>
                    <div id='timeline-sidebar-modal'></div>
                    <div class='flex-fill px-3' style='overflow:auto;max-width:calc(100% - 220px);'>
                        <div class='d-flex flex-row gap-3 w-100 h-100'>
                            <div class='flex-fill' id='compare-section-a'></div>
                            <div class='flex-fill' id='compare-section-b'></div>
                        </div>
                    </div>
                </div>`,
                width: '90vw',
                heightAuto: false,
                showCancelButton: true,
                showConfirmButton: false,
                customClass: { popup: 'swal2-modal-compare-data' },
                didOpen: () => {
                    fetchTimeline(() => {
                        // Default: set A to current version, B to latest snapshot (if >1)
                        if (timeline.length > 0) {
                            selectedA = timeline.find(s => s.is_current_version)?.id || timeline[0].id;
                            selectedB = timeline.length > 1 ? timeline[1].id : null;
                            if (selectedA && selectedB) {
                                fetchCompare(selectedA, selectedB, () => {
                                    nextSection = 'a';
                                    renderModal();
                                });
                            } else if (selectedA) {
                                fetchSnapshotData(selectedA, 'a', () => {
                                    nextSection = 'b';
                                    renderModal();
                                });
                            } else {
                                renderModal();
                            }
                        } else {
                            renderModal();
                        }
                    });
                }
            });
        },

        /**
         * Render a diff table (side-by-side, highlight differences)
         */
        renderDiffTable: function(a, b) {
            // Lightweight diff: highlight cells in a that differ from b
            if (!Array.isArray(a) || a.length === 0) {
                return '<div class="text-muted small">No data</div>';
            }
            const headers = Object.keys(a[0]);
            let html = '<div class="table-responsive"><table class="table table-striped table-hover table-sm">';
            html += '<thead><tr>';
            headers.forEach(h => { html += `<th>${h}</th>`; });
            html += '</tr></thead><tbody>';
            a.forEach((row, i) => {
                html += '<tr>';
                headers.forEach(h => {
                    let diff = false;
                    // Only highlight differences if we have comparison data
                    if (b && Array.isArray(b) && b[i] && b[i][h] !== row[h]) {
                        diff = true;
                    }
                    html += `<td${diff ? ' style="background:#ffe5e5;"' : ''}>${row[h] != null ? row[h] : ''}</td>`;
                });
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            return html;
        },
    };

    // Expose assistant to global scope for onclick handlers
    window.assistant = assistant;
    
    return assistant;
});
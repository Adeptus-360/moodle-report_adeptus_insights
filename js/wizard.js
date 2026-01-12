/**
 * Adeptus Insights Report Wizard JavaScript
 * Modern, interactive wizard for generating reports
 */

class AdeptusWizard {
    constructor() {
        this.currentStep = 'step-categories';
        this.selectedCategory = null;
        this.selectedReport = null;
        this.wizardData = {};
        this.savedParameters = {};
        this.backendApiUrl = null; // Will be set from configuration
        this.currentResults = null;
        this.currentHeaders = null;
        this.currentView = 'table'; // Initialize current view
        this.backendEnabled = true; // Default to enabled
        this.fallbackEnabled = true; // Default to enabled
        this.debugMode = false; // Default to disabled
        this.chartJS = null; // Will store Chart.js instance
    }

    async init() {
        try {
            await this.loadWizardData();
            this.bindEvents();
            this.initializeQuickActions();
            this.initializeRecentReports();
            this.updateBookmarkStates();
            this.updateReportsLeftCounter();
            this.updateExportsCounter();

            // Render sections with persisted data immediately
            this.renderGeneratedReports();
            this.renderRecentReports();
            this.renderBookmarks();

            // Hide loading modal after everything is initialized
            this.hideLoading();
        } catch (error) {
            this.hideLoading();
            console.error('Initialization failed:', error);
            throw error;
        }
    }

    async loadWizardData() {

        // First, try to load data from the template (this is the main source)
        const wizardDataElement = document.getElementById('wizard-data');
        if (wizardDataElement) {
            try {
                const templateData = JSON.parse(wizardDataElement.textContent);
                this.wizardData = { ...this.wizardData, ...templateData };
            } catch (error) {
                console.error('Error parsing template data:', error);
            }
        } else {
            console.warn('Wizard data element not found in template');
        }

        // Backend API URL from template data
        this.backendApiUrl = this.wizardData.backend_api_url || '';
        
        // Load additional wizard data from PHP (if needed)
        try {
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/get_wizard_data.php`);
            const data = await response.json();
            
            if (data.success) {
                this.wizardData = { ...this.wizardData, ...data.data };
            } else {
                console.error('Failed to load additional wizard data:', data);
            }
        } catch (error) {
            console.error('Error loading additional wizard data:', error);
        }
        
        // Load reports from backend API
        await this.loadReportsFromBackend();
        
        // Load Chart.js from Moodle's core system
        this.loadChartJS();
    }
    
    async loadReportsFromBackend() {
        // Prevent duplicate loading if already loaded
        if (this.categoriesLoaded) {
            return;
        }

        try {
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/get_reports_from_backend.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `sesskey=${this.wizardData.sesskey}`
            });

            const data = await response.json();

            if (data.success) {
                this.wizardData.categories = data.categories;
                this.categoriesLoaded = true;

                // Initialize the wizard with the loaded data
                this.renderCategories();

                // Enhance recent reports and bookmarks with backend data
                this.enhanceRecentReportsAndBookmarks();
                this.updateBookmarkStates();
            } else {
                console.error('Failed to load reports from backend:', data.message);
                throw new Error(data.message || 'Failed to load reports from backend');
            }
        } catch (error) {
            console.error('Error loading reports from backend:', error);

            // Check if this is an authentication error (301/302 redirect to login)
            if (error.message.includes('HTTP 301') || error.message.includes('HTTP 302')) {
                this.showError('Your session has expired. Please refresh the page and log in again.');
            } else {
                this.showError('Failed to load reports from backend: ' + error.message);
            }
            throw error;
        }
    }
    
    // Method to reset categories loaded flag (useful for forcing a reload)
    resetCategoriesLoaded() {
        this.categoriesLoaded = false;
    }
    
    
    renderCategories() {
        
        const categoryGrid = document.querySelector('.category-grid');
        if (!categoryGrid) {
            console.error('Category grid container not found');
            return;
        }
        
        // Clear existing categories
        categoryGrid.innerHTML = '';
        
        // Render each category
        this.wizardData.categories.forEach(category => {
            const categoryCard = this.createCategoryCard(category);
            categoryGrid.appendChild(categoryCard);
        });
        
    }

    renderGeneratedReports() {
        const section = document.getElementById('generated-reports-section');
        const grid = document.getElementById('generated-reports-grid');
        
        if (!section || !grid) {
            console.error('Generated reports section or grid not found');
            return;
        }

        // Clear existing cards
        grid.innerHTML = '';

        // Use dedicated generated reports data
        const generatedReports = this.wizardData.generated_reports || [];

        if (generatedReports.length === 0) {
            section.style.display = 'none';
            return;
        }

        // Show section
        section.style.display = 'block';

        const maxVisible = 10;
        const hasMore = generatedReports.length > maxVisible;

        // Render each generated report
        generatedReports.forEach((report, index) => {
            const reportCard = this.createSectionReportCard(report, 'generated');
            if (index >= maxVisible) {
                reportCard.classList.add('hidden-item');
                reportCard.style.display = 'none';
            }
            grid.appendChild(reportCard);
        });

        // Add or update "Show All" button
        this.updateShowAllButton('generated-reports-section', hasMore, generatedReports.length);

    }

    renderRecentReports() {
        const section = document.getElementById('recent-reports-section');
        const grid = document.getElementById('recent-reports-grid');
        
        if (!section || !grid) {
            console.error('Recent reports section or grid not found');
            return;
        }

        // Clear existing cards
        grid.innerHTML = '';

        if (!this.wizardData.recent_reports || this.wizardData.recent_reports.length === 0) {
            section.style.display = 'none';
            return;
        }

        // Show section
        section.style.display = 'block';

        const maxVisible = 10;
        const hasMore = this.wizardData.recent_reports.length > maxVisible;

        // Render each recent report
        this.wizardData.recent_reports.forEach((report, index) => {
            const reportCard = this.createSectionReportCard(report, 'recent');
            if (index >= maxVisible) {
                reportCard.classList.add('hidden-item');
                reportCard.style.display = 'none';
            }
            grid.appendChild(reportCard);
        });

        // Add or update "Show All" button
        this.updateShowAllButton('recent-reports-section', hasMore, this.wizardData.recent_reports.length);

    }

    renderBookmarks() {
        const section = document.getElementById('bookmarks-section');
        const grid = document.getElementById('bookmarks-grid');
        
        if (!section || !grid) {
            console.error('Bookmarks section or grid not found');
            return;
        }

        // Clear existing cards
        grid.innerHTML = '';

        if (!this.wizardData.bookmarks || this.wizardData.bookmarks.length === 0) {
            section.style.display = 'none';
            return;
        }

        // Show section
        section.style.display = 'block';

        const maxVisible = 10;
        const hasMore = this.wizardData.bookmarks.length > maxVisible;

        // Render each bookmark
        this.wizardData.bookmarks.forEach((bookmark, index) => {
            const reportCard = this.createSectionReportCard(bookmark, 'bookmark');
            if (index >= maxVisible) {
                reportCard.classList.add('hidden-item');
                reportCard.style.display = 'none';
            }
            grid.appendChild(reportCard);
        });

        // Add or update "Show All" button
        this.updateShowAllButton('bookmarks-section', hasMore, this.wizardData.bookmarks.length);

    }
    
    getCategoryIcon(categoryName) {
        // Map category names to appropriate Font Awesome icons
        const iconMap = {
            'Student Performance': 'fa-graduation-cap',
            'Engagement': 'fa-users',
            'Course Analytics': 'fa-line-chart',
            'Assessment': 'fa-check-square-o',
            'Attendance': 'fa-calendar-check-o',
            'User Activity': 'fa-user-circle',
            'System': 'fa-cog',
            'Reports': 'fa-file-text-o',
            'Quiz': 'fa-question-circle',
            'Assignment': 'fa-edit',
            'Forum': 'fa-comments',
            'Grades': 'fa-trophy',
            'Progress': 'fa-line-chart',
            'Completion': 'fa-check-circle',
            'Time': 'fa-clock-o',
            'Resource': 'fa-folder-open',
            'Activity': 'fa-bolt',
            'Learning': 'fa-book',
            'Teaching': 'fa-chalkboard-teacher',
            'Administration': 'fa-user-shield'
        };

        // Try exact match first
        if (iconMap[categoryName]) {
            return iconMap[categoryName];
        }

        // Try partial match (case insensitive)
        const lowerCategoryName = categoryName.toLowerCase();
        for (const [key, icon] of Object.entries(iconMap)) {
            if (lowerCategoryName.includes(key.toLowerCase()) || key.toLowerCase().includes(lowerCategoryName)) {
                return icon;
            }
        }

        // Default icon
        return 'fa-folder-o';
    }

    createCategoryCard(category) {
        const card = document.createElement('div');
        card.className = 'category-card';
        card.setAttribute('data-category', category.name);
        
        // Add premium styling if user is on free plan and category has premium reports
        if (this.wizardData.is_free_plan && category.free_reports_count < category.report_count) {
            card.classList.add('premium-category');
        }
        
        // Get dynamic icon based on category name
        const iconClass = this.getCategoryIcon(category.name);
        
        card.innerHTML = `
            <div class="category-icon">
                <i class="fa ${iconClass}"></i>
            </div>
            <div class="category-content">
                <h6 class="category-title">${category.name}</h6>
                <p>${category.report_count} reports</p>
                ${this.wizardData.is_free_plan && category.free_reports_count < category.report_count ? 
                    `<span class="premium-badge">${category.free_reports_count}/${category.report_count} Free</span>` : 
                    ''
                }
            </div>
        `;
        
        return card;
    }
    
    enhanceRecentReportsAndBookmarks() {
        
        // Create a lookup map of all reports by name and index (for legacy numeric IDs)
        const reportLookup = {};
        const reportIndexLookup = {};
        this.wizardData.categories.forEach(category => {
            category.reports.forEach((report, index) => {
                reportLookup[report.name] = {
                    ...report,
                    category: category.original_name
                };
                // For legacy numeric IDs, create a mapping based on order
                reportIndexLookup[index + 1] = {
                    ...report,
                    category: category.original_name
                };
            });
        });
        
        // Enhance recent reports
        if (this.wizardData.recent_reports) {
            this.wizardData.recent_reports.forEach(report => {
                let backendReport = null;
                
                // Try to find by name first (new system)
                if (reportLookup[report.name]) {
                    backendReport = reportLookup[report.name];
                }
                // Try to find by numeric ID (legacy system)
                else if (typeof report.name === 'number' || !isNaN(report.name)) {
                    const numericId = parseInt(report.name);
                    if (reportIndexLookup[numericId]) {
                        backendReport = reportIndexLookup[numericId];
                        // Update the report name to the actual report name
                        report.name = backendReport.name;
                    }
                }
                
                if (backendReport) {
                    report.category = backendReport.category;
                    report.description = backendReport.description;
                    report.charttype = backendReport.charttype;
                }
                
                // Set has_data flag - assume all recent reports have data unless explicitly marked otherwise
                if (report.has_data === undefined) {
                    report.has_data = true;
                }
            });
        }
        
        // Enhance bookmarks
        if (this.wizardData.bookmarks) {
            this.wizardData.bookmarks.forEach(bookmark => {
                let backendReport = null;

                // Try to find by name first (new system)
                if (reportLookup[bookmark.name]) {
                    backendReport = reportLookup[bookmark.name];
                }
                // Try to find by numeric ID (legacy system)
                else if (typeof bookmark.name === 'number' || !isNaN(bookmark.name)) {
                    const numericId = parseInt(bookmark.name);
                    if (reportIndexLookup[numericId]) {
                        backendReport = reportIndexLookup[numericId];
                        // Update the bookmark name to the actual report name
                        bookmark.name = backendReport.name;
                    }
                }

                if (backendReport) {
                    bookmark.category = backendReport.category;
                    bookmark.description = backendReport.description;
                    bookmark.charttype = backendReport.charttype;
                }
            });
        }

        // Enhance generated reports
        if (this.wizardData.generated_reports) {
            this.wizardData.generated_reports.forEach(generatedReport => {
                let backendReport = null;

                // Try to find by name first (new system)
                if (reportLookup[generatedReport.name]) {
                    backendReport = reportLookup[generatedReport.name];
                }
                // Try to find by numeric ID (legacy system)
                else if (typeof generatedReport.name === 'number' || !isNaN(generatedReport.name)) {
                    const numericId = parseInt(generatedReport.name);
                    if (reportIndexLookup[numericId]) {
                        backendReport = reportIndexLookup[numericId];
                        // Update the generated report name to the actual report name
                        generatedReport.name = backendReport.name;
                    }
                }

                if (backendReport) {
                    generatedReport.category = backendReport.category;
                    generatedReport.description = backendReport.description;
                    generatedReport.charttype = backendReport.charttype;
                }
            });
        }


        // Re-render all sections after enhancement only if categories are loaded
        if (this.categoriesLoaded) {
            this.renderGeneratedReports();
            this.renderRecentReports();
            this.renderBookmarks();
        }
    }

    /**
     * Load Chart.js using Moodle's AMD require system
     * This ensures proper module loading in Moodle's environment
     */
    loadChartJS() {
        // Check if already loaded
        if (this.chartJS) {
            return;
        }

        // Check global Chart
        if (typeof Chart !== 'undefined') {
            this.chartJS = Chart;
            return;
        }

        // Use Moodle's require to load Chart.js
        if (typeof require !== 'undefined') {
            require(['core/chartjs'], (ChartModule) => {
                this.chartJS = ChartModule;
                // Also set global for compatibility
                if (typeof window !== 'undefined') {
                    window.Chart = ChartModule;
                }
            });
        } else {
            console.error('Moodle require() not available');
        }
    }

    /**
     * Get Chart.js instance, loading if necessary
     * Call this before creating charts
     */
    async getChartJS() {
        // Already loaded in this instance
        if (this.chartJS) {
            return this.chartJS;
        }

        // Check global Chart
        if (typeof Chart !== 'undefined') {
            this.chartJS = Chart;
            return this.chartJS;
        }

        // Load via Moodle's AMD require system
        return new Promise((resolve, reject) => {
            if (typeof require !== 'undefined') {
                require(['core/chartjs'], (ChartModule) => {
                    this.chartJS = ChartModule;
                    // Also set global for compatibility
                    if (typeof window !== 'undefined') {
                        window.Chart = ChartModule;
                    }
                    resolve(this.chartJS);
                }, (err) => {
                    console.error('Failed to load Chart.js via AMD:', err);
                    reject(new Error('Chart.js not available - please refresh the page'));
                });
            } else {
                reject(new Error('Moodle require() not available'));
            }
        });
    }

    bindEvents() {
        
        // Prevent duplicate event binding
        if (this.eventsBound) {
            return;
        }
        this.eventsBound = true;
        
        // Debug: Check what category cards are available
        const categoryCards = document.querySelectorAll('.category-card');
        categoryCards.forEach((card, index) => {
        });
        
        // Category selection
        document.addEventListener('click', (e) => {
            if (e.target.closest('.category-card')) {
                const categoryCard = e.target.closest('.category-card');
                const categoryName = categoryCard.dataset.category;
                this.selectCategory(categoryName);
            }
        });

        // Report selection (only for report selection step, not for section cards)
        document.addEventListener('click', (e) => {
            if (e.target.closest('.report-card')) {
                const reportCard = e.target.closest('.report-card');
                
                // Don't handle click if this is a section card (generated, recent, bookmark)
                // or if the click is on a button within the card
                const isButton = e.target.closest('.btn-load-config, .btn-remove');
                const isSectionCard = reportCard.dataset.action; // Section cards have data-action attribute
                
                if (isButton || isSectionCard) {
                    return; // Let button handlers deal with it
                }
                
                const reportId = reportCard.dataset.reportId;
                this.selectReport(reportId);
            }
        });

        // Report cards - Load Config buttons (for all sections)
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-load-config')) {
                e.preventDefault();
                e.stopPropagation();
                const card = e.target.closest('.report-card');
                const reportId = card.dataset.reportId;
                const action = card.dataset.action;
                const parameters = card.dataset.parameters || '{}';
                this.handleLoadConfiguration(reportId, action, parameters);
            }
        });

        // Remove buttons (for all sections)
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-remove')) {
                e.preventDefault();
                e.stopPropagation();
                const button = e.target.closest('.btn-remove');
                const card = button.closest('.report-card');
                const reportId = button.dataset.reportId;
                const action = card.dataset.action;
                
                switch (action) {
                    case 'bookmark':
                        this.removeBookmark(reportId);
                        break;
                    case 'recent':
                this.removeRecentReport(reportId);
                        break;
                    case 'generated':
                        this.removeFromGeneratedView(reportId);
                        break;
                    default:
                        console.warn('Unknown action for remove button:', action);
                }
            }
        });

        // Toggle recent reports button
        document.getElementById('toggle-recent-reports')?.addEventListener('click', () => {
            this.toggleRecentReports();
        });

        // Clear all recent reports button
        document.getElementById('clear-recent-reports')?.addEventListener('click', () => {
            this.clearAllRecentReports();
        });

        // Clear all bookmarks button
        document.getElementById('clear-bookmarked-reports')?.addEventListener('click', () => {
            this.clearAllBookmarks();
        });

        // Popup close button
        document.getElementById('popup-close')?.addEventListener('click', () => {
            this.hidePopup();
        });

        // Close popup when clicking overlay
        document.getElementById('popup-overlay')?.addEventListener('click', (e) => {
            if (e.target.id === 'popup-overlay') {
                this.hidePopup();
            }
        });

        // Back buttons
        document.getElementById('back-to-categories')?.addEventListener('click', () => {
            this.goToStep('step-select-category');
        });

        document.getElementById('back-to-reports')?.addEventListener('click', () => {
            // Preserve selected report and load category if needed
            if (this.selectedReport && !this.selectedCategory) {
                this.findCategoryForReport(this.selectedReport);
            }
            this.goToStep('step-select-report');
        });

        document.getElementById('back-to-config')?.addEventListener('click', () => {
            this.goToStep('step-configure');
        });

        // Generate report button
        document.getElementById('generate-report')?.addEventListener('click', () => {
            this.generateReport();
        });

        // Export functionality
        document.getElementById('export-btn')?.addEventListener('click', (e) => {
            // Prevent dropdown if button is disabled
            const exportBtn = e.currentTarget;
            if (exportBtn.disabled || exportBtn.classList.contains('disabled')) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            this.toggleExportMenu();
        });

        document.addEventListener('click', (e) => {
            if (e.target.closest('.export-menu a')) {
                e.preventDefault();
                const link = e.target.closest('a');
                const format = link.dataset.format;
                
                // Check if this is a premium export on free plan
                if (link.classList.contains('export-premium')) {
                    this.showExportUpgradePrompt(format);
                    return;
                }
                
                this.exportReport(format);
            }
        });

        // View toggle switching
        document.addEventListener('click', (e) => {
            if (e.target.closest('.view-toggle-btn')) {
                const toggleBtn = e.target.closest('.view-toggle-btn');
                const viewName = toggleBtn.dataset.view;
                this.switchView(viewName);
            }
        });

        // Bookmark functionality - Updated for toggle
        document.getElementById('bookmark-report')?.addEventListener('click', () => {
            this.toggleBookmark();
        });

        // Regenerate report
        document.getElementById('regenerate-report')?.addEventListener('click', () => {
            this.generateReport();
        });
    }

    initializeQuickActions() {
        // Prevent duplicate initialization
        if (this.quickActionsInitialized) {
            return;
        }
        this.quickActionsInitialized = true;
        
        // Add hover animations to quick action cards
        const quickActionCards = document.querySelectorAll('.quick-action-card');
        quickActionCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-2px)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    }

    initializeRecentReports() {
        // Prevent duplicate initialization
        if (this.recentReportsInitialized) {
            return;
        }
        this.recentReportsInitialized = true;
        
        // Initially hide all recent reports except the last 4
        const recentCards = document.querySelectorAll('.quick-action-card[data-action="recent"]');
        if (recentCards.length > 4) {
            recentCards.forEach((card, index) => {
                if (index >= 4) {
                    card.style.display = 'none';
                    card.classList.add('hidden-recent');
                }
            });
        }
    }

    toggleRecentReports() {
        const recentCards = document.querySelectorAll('.quick-action-card[data-action="recent"]');
        const toggleBtn = document.getElementById('toggle-recent-reports');
        
        if (recentCards.length <= 4) {
            // If 4 or fewer cards, no need to toggle
            // show that there are no more recent reports in message modal
            this.showPopup('No Recent Reports', 'There are no more recent reports to display.');
            return;
        }

        this.recentReportsExpanded = !this.recentReportsExpanded;
        
        if (this.recentReportsExpanded) {
            // Show all cards
            recentCards.forEach(card => {
                card.style.display = '';
                card.classList.remove('hidden-recent');
            });
            toggleBtn.innerHTML = '<i class="fa fa-eye-slash"></i> Hide All';
        } else {
            // Show only last 4 cards
            recentCards.forEach((card, index) => {
                if (index >= 4) {
                    card.style.display = 'none';
                    card.classList.add('hidden-recent');
                } else {
                    card.style.display = '';
                    card.classList.remove('hidden-recent');
                }
            });
            toggleBtn.innerHTML = '<i class="fa fa-eye"></i> Show All';
        }
    }

    updateBookmarkStates() {
        // Update bookmark button states based on current bookmarked reports
        if (this.wizardData.bookmarked_report_ids) {
            const bookmarkBtn = document.getElementById('bookmark-report');
            if (bookmarkBtn && this.selectedReport) {
                const isBookmarked = this.wizardData.bookmarked_report_ids.includes(this.selectedReport);
                this.updateBookmarkButton(isBookmarked);
            }
        }
    }

    updateBookmarkButton(isBookmarked) {
        const bookmarkBtn = document.getElementById('bookmark-report');
        if (!bookmarkBtn) return;

        if (isBookmarked) {
            bookmarkBtn.innerHTML = '<i class="fa fa-star"></i> Remove Bookmark';
            bookmarkBtn.classList.add('bookmarked');
        } else {
            bookmarkBtn.innerHTML = '<i class="fa fa-star-o"></i> Bookmark';
            bookmarkBtn.classList.remove('bookmarked');
        }
        bookmarkBtn.disabled = false;
    }

    findCategoryForReport(reportId) {
        // Find which category contains this report
        for (const category of this.wizardData.categories) {
            const report = category.reports.find(r => r.name === reportId); // Changed from r.id == reportId to r.name === reportId
            if (report) {
                this.selectedCategory = category.name;
                this.loadReportsForCategory(category.name);
                return category.name;
            }
        }
        return null;
    }

    selectCategory(categoryName) {
        this.selectedCategory = categoryName;
        
        // Animate category selection
        const categoryCard = document.querySelector(`[data-category="${categoryName}"]`);
        if (categoryCard) {
        categoryCard.style.transform = 'scale(0.95)';
        setTimeout(() => {
            categoryCard.style.transform = 'scale(1)';
        }, 150);
        } else {
            console.error('Category card not found for:', categoryName);
        }

        // Load reports for this category
        this.loadReportsForCategory(categoryName);
        
        // Navigate to report selection
        setTimeout(() => {
            this.goToStep('step-select-report');
        }, 300);
    }

    loadReportsForCategory(categoryName) {
        
        const category = this.wizardData.categories.find(cat => cat.name === categoryName);
        if (!category) {
            console.error('Category not found:', categoryName);
            return;
        }

        document.getElementById('selected-category-name').textContent = `Reports in ${categoryName}`;
        
        const reportsGrid = document.getElementById('reports-grid');
        reportsGrid.innerHTML = '';

        category.reports.forEach(report => {
            const reportCard = this.createReportCard(report);
            reportsGrid.appendChild(reportCard);
        });

        // Add entrance animation
        setTimeout(() => {
            const reportCards = reportsGrid.querySelectorAll('.report-card');
            reportCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.3s ease-out';
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        }, 100);
    }

    createReportCard(report) {
        const card = document.createElement('div');
        card.className = 'report-card';
        card.dataset.reportId = report.name; // Changed from report.id to report.name
        
        const isBookmarked = this.wizardData.bookmarked_report_ids && 
                            this.wizardData.bookmarked_report_ids.includes(report.name); // Changed from parseInt(report.id) to report.name
        
        // Check if user is on free plan and report is premium
        const isFreePlan = this.wizardData.is_free_plan;
        const isPremiumReport = isFreePlan && !report.is_free_tier;
        
        if (isPremiumReport) {
            card.classList.add('premium-report');
            card.style.opacity = '0.7';
            card.style.cursor = 'not-allowed';
        }
        
        card.innerHTML = `
            <h4>${report.name}</h4>
            <p>${report.description || 'No description available'}</p>
            <div class="report-meta">
                <span>Name: ${report.name}</span>
                ${report.charttype ? `<span class="chart-type">${report.charttype}</span>` : ''}
                ${isBookmarked ? '<span class="bookmark-indicator"><i class="fa fa-star"></i></span>' : ''}
                ${isPremiumReport ? '<span class="premium-badge"><i class="fa fa-crown"></i> Premium</span>' : ''}
            </div>
        `;

        // Add click handler for premium reports
        if (isPremiumReport) {
            card.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.showUpgradePrompt(report);
            });
        }

        return card;
    }

    createSectionReportCard(report, sectionType) {
        const card = document.createElement('div');
        card.className = 'report-card';
        card.dataset.reportId = report.reportid || report.name;
        card.dataset.action = sectionType;
        
        // Set different icons based on section type
        let iconClass = 'fa-file-text';
        let iconColor = '#007bff';
        
        switch (sectionType) {
            case 'generated':
                iconClass = 'fa-bar-chart';
                iconColor = '#28a745';
                break;
            case 'recent':
                iconClass = 'fa-clock-o';
                iconColor = '#ffc107';
                break;
            case 'bookmark':
                iconClass = 'fa-star';
                iconColor = '#fd7e14';
                break;
        }
        
        card.innerHTML = `
            <div class="card-icon" style="background: linear-gradient(135deg, ${iconColor} 0%, ${this.darkenColor(iconColor)} 100%);">
                <i class="fa ${iconClass}"></i>
            </div>
            <div class="card-content">
                <h4>${report.name}</h4>
                <p>${report.category || 'Unknown Category'}</p>
                <span class="card-date">${report.formatted_date || 'Just now'}</span>
            </div>
            <div class="card-actions">
                <button class="btn-load-config" title="Load Configuration">
                    <i class="fa fa-cog"></i>
                    <span>Load</span>
                </button>
                <button class="btn-remove" data-report-id="${report.reportid || report.name}" title="Remove">
                    <i class="fa fa-trash"></i>
                    <span>Remove</span>
                </button>
            </div>
        `;

        return card;
    }

    darkenColor(color) {
        // Simple color darkening function
        const colors = {
            '#007bff': '#0056b3',
            '#28a745': '#1e7e34',
            '#ffc107': '#d39e00',
            '#fd7e14': '#e55100'
        };
        return colors[color] || '#666';
    }

    selectReport(reportId) {
        // Find the report data to check if it's premium
        const category = this.wizardData.categories.find(cat => 
            cat.reports.some(rep => rep.name === reportId) // Changed from rep.id == reportId to rep.name === reportId
        );
        const report = category ? category.reports.find(rep => rep.name === reportId) : null; // Changed from rep.id == reportId to rep.name === reportId
        
        // Check if this is a premium report for free plan users
        const isFreePlan = this.wizardData.is_free_plan;
        const isPremiumReport = isFreePlan && report && !report.is_free_tier;
        
        if (isPremiumReport) {
            this.showUpgradePrompt(report);
            return;
        }
        
        this.selectedReport = reportId;
        
        // Animate report selection
        const reportCard = document.querySelector(`[data-report-id="${reportId}"]`);
        if (reportCard) {
        reportCard.style.transform = 'scale(0.95)';
        setTimeout(() => {
            reportCard.style.transform = 'scale(1)';
        }, 150);
        }

        // Load report parameters
        this.loadReportParameters(reportId);
        
        // Navigate to configuration
        setTimeout(() => {
            this.goToStep('step-configure');
        }, 300);
    }

    async loadReportParameters(reportId, savedParams = null) {
        this.showLoading('Loading report configuration...');
        
        try {
            // First, get the basic report parameters from the local endpoint
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/get_report_parameters.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `reportid=${reportId}&sesskey=${this.wizardData.sesskey}`
            });

            const data = await response.json();
            
            if (data.success) {
                // Store saved parameters if provided
                if (savedParams) {
                    this.savedParameters = savedParams;
                }
                
                // Process parameters using the backend API for enhanced functionality if enabled
                let enhancedParameters = data.parameters;
                if (this.backendEnabled && data.parameters && data.parameters.length > 0) {
                    try {
                        enhancedParameters = await this.enhanceParametersWithBackend(data.parameters);
                    } catch (error) {
                        console.warn('Backend enhancement failed, using local parameters:', error);
                        if (this.debugMode) {
                            this.showError('Backend enhancement failed, using local fallback');
                        }
                    }
                }
                
                this.displayReportConfiguration(data.report, enhancedParameters);
                this.updateBookmarkStates(); // Update bookmark button state
                
                // Show backend status if debug mode is enabled
                if (this.debugMode && data.backend_enhanced !== undefined) {
                }
            } else {
                this.showError('Failed to load report parameters');
            }
        } catch (error) {
            console.error('Error loading report parameters:', error);
            this.showError('Error loading report parameters');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Enhance parameters using the backend API for better type detection and processing
     */
    async enhanceParametersWithBackend(parameters) {
        if (!parameters || parameters.length === 0 || !this.backendEnabled) {
            return parameters;
        }

        try {
            // Get parameter type mapping from backend
            const typeMappingResponse = await fetch(`${this.backendApiUrl}/adeptus-reports/parameter-types`);
            if (!typeMappingResponse.ok) {
                throw new Error(`HTTP ${typeMappingResponse.status}: ${typeMappingResponse.statusText}`);
            }
            
            const typeMappingData = await typeMappingResponse.json();
            
            if (!typeMappingData.success) {
                console.warn('Failed to get parameter type mapping from backend, using local fallback');
                return parameters;
            }

            const typeMapping = typeMappingData.data;
            
            // Process each parameter using the backend API
            const enhancedParameters = [];
            
            for (const param of parameters) {
                try {
                    // Call backend API to process the parameter
                    const processResponse = await fetch(`${this.backendApiUrl}/adeptus-reports/process-parameter`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            paramName: param.name,
                            paramConfig: {
                                type: param.type,
                                label: param.label,
                                description: param.description,
                                required: param.required,
                                default: param.default,
                                options: param.options
                            }
                        })
                    });

                    if (!processResponse.ok) {
                        throw new Error(`HTTP ${processResponse.status}: ${processResponse.statusText}`);
                    }

                    const processData = await processResponse.json();
                    
                    if (processData.success) {
                        // Merge backend processed data with existing options and other local data
                        const enhancedParam = {
                            ...param,
                            ...processData.data,
                            // Preserve local options and other specific data
                            options: param.options || processData.data.options,
                            min: param.min || processData.data.min,
                            max: param.max || processData.data.max
                        };
                        enhancedParameters.push(enhancedParam);
                        
                        if (this.debugMode) {
                        }
                    } else {
                        // Fallback to original parameter if backend processing fails
                        console.warn(`Backend parameter processing failed for ${param.name}, using local fallback`);
                        enhancedParameters.push(param);
                    }
                } catch (error) {
                    console.warn(`Error processing parameter ${param.name} with backend, using local fallback:`, error);
                    enhancedParameters.push(param);
                }
            }
            
            return enhancedParameters;
            
        } catch (error) {
            console.warn('Backend API enhancement failed, using original parameters:', error);
            if (this.fallbackEnabled) {
                return parameters;
            } else {
                throw error; // Re-throw if fallback is disabled
            }
        }
    }

    displayReportConfiguration(report, parameters) {
        document.getElementById('selected-report-name').textContent = report.name;
        
        const configForm = document.getElementById('config-form');
        configForm.innerHTML = '<h3>Report Parameters</h3>';

        if (parameters && parameters.length > 0) {
            parameters.forEach(param => {
                const paramElement = this.createParameterElement(param);
                configForm.appendChild(paramElement);
            });

            // Apply saved parameters if available
            if (this.savedParameters && Object.keys(this.savedParameters).length > 0) {
                this.applySavedParameters();
            }
        } else {
            configForm.innerHTML += '<p>This report requires no additional parameters.</p>';
        }

        // Show preview
        this.updatePreview(report);
    }

    applySavedParameters() {
        // Apply saved parameter values to form inputs
        Object.keys(this.savedParameters).forEach(paramName => {
            const input = document.getElementById(`param_${paramName}`);
            if (input) {
                input.value = this.savedParameters[paramName];
            }
        });
        // Clear saved parameters after applying
        this.savedParameters = {};
    }

    createParameterElement(param) {
        const wrapper = document.createElement('div');
        wrapper.className = 'parameter-field';

        let inputHtml = '';
        let inputAttribs = '';
        
        // Set common attributes
        if (param.required) {
            inputAttribs += ' required';
        }
        
        switch (param.type) {
            case 'select':
            case 'quiz_select':
                inputHtml = `
                    <select name="${param.name}" id="param_${param.name}" class="form-control"${inputAttribs}>
                        <option value="">Select ${param.label}...</option>
                        ${param.options ? param.options.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('') : ''}
                    </select>
                `;
                break;
            case 'date':
                const defaultDate = param.default || '';
                inputHtml = `<input type="date" name="${param.name}" id="param_${param.name}" class="form-control" value="${defaultDate}"${inputAttribs}>`;
                break;
            case 'number':
                const min = param.min || '';
                const max = param.max || '';
                const defaultNum = param.default || '';
                inputHtml = `<input type="number" name="${param.name}" id="param_${param.name}" class="form-control" min="${min}" max="${max}" value="${defaultNum}"${inputAttribs}>`;
                break;
            default:
                const defaultText = param.default || '';
                inputHtml = `<input type="text" name="${param.name}" id="param_${param.name}" class="form-control" value="${defaultText}"${inputAttribs}>`;
        }

        wrapper.innerHTML = `
            <label for="param_${param.name}" class="form-label">${param.label || param.name}${param.required ? ' *' : ''}</label>
            ${inputHtml}
            ${param.description ? `<small class="form-text text-muted">${param.description}</small>` : ''}
        `;

        return wrapper;
    }

    updatePreview(report) {
        const previewContent = document.getElementById('preview-content');
        previewContent.innerHTML = `
            <h4>${report.name}</h4>
            <p><strong>Category:</strong> ${report.category}</p>
            <p><strong>Description:</strong> ${report.description || 'No description available'}</p>
            ${report.charttype ? `<p><strong>Chart Type:</strong> ${report.charttype}</p>` : ''}
            <div class="preview-sample">
                <small><em>Sample data will appear here after generation</em></small>
            </div>
        `;
    }

    async generateReport() {
        // Prevent multiple simultaneous calls
        if (this.isGeneratingReport) {
            return;
        }
        
        // Check if generate button is disabled (limit reached)
        const generateBtn = document.getElementById('generate-report');
        if (generateBtn && generateBtn.disabled) {
            return;
        }
        
        this.isGeneratingReport = true;
        this.showLoading('Generating your report...');
        
        // Collect parameters
        const formData = new FormData();
        formData.append('reportid', this.selectedReport);
        formData.append('sesskey', this.wizardData.sesskey);
        
        const paramInputs = document.querySelectorAll('#config-form input, #config-form select');
        paramInputs.forEach(input => {
            formData.append(input.name, input.value);
        });

        try {
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/generate_report.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.displayResults(data);
                this.goToStep('step-results');
                
                // Update reports left counter only if it's not a duplicate
                if (!data.is_duplicate) {
                    // Update counter immediately after generation
                    this.updateReportsLeftCounter();
                }
                
                // Refresh recent reports to show the newly generated report
                this.refreshRecentReports();
            } else {
                this.showError(data.message || 'Failed to generate report');
            }
        } catch (error) {
            console.error('Error generating report:', error);
            this.showError('Error generating report');
        } finally {
            this.hideLoading();
            this.isGeneratingReport = false;
        }
    }

    async refreshRecentReports() {
        try {
            // Reload wizard data to get updated recent reports
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/wizard.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `sesskey=${this.wizardData.sesskey}`
            });
            
            const html = await response.text();
            
            // Parse the new wizard data from the response
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newWizardDataScript = doc.querySelector('script#wizard-data');
            
            if (newWizardDataScript) {
                const newWizardData = JSON.parse(newWizardDataScript.textContent);
                this.wizardData.recent_reports = newWizardData.recent_reports;
                this.wizardData.generated_reports = newWizardData.generated_reports;
                this.wizardData.bookmarks = newWizardData.bookmarks;
                this.wizardData.bookmarked_report_ids = newWizardData.bookmarked_report_ids;
                
                // Re-enhance recent reports and bookmarks with backend data
                this.enhanceRecentReportsAndBookmarks();
                
                // Re-render all sections
                this.renderGeneratedReports();
                this.renderRecentReports();
                this.renderBookmarks();
                
            }
        } catch (error) {
            console.error('Error refreshing recent reports:', error);
            // Fallback: just re-render all sections with current data
            this.renderGeneratedReports();
            this.renderRecentReports();
            this.renderBookmarks();
        }
    }

    displayResults(data) {
        // Store current results for export
        this.currentResults = data;

        // Store report name for chart titles
        if (data.report_name) {
            this.wizardData.current_report_name = data.report_name;
        }

        // SAFETY CHECK: Protect against massive datasets
        const recordCount = data.results.length;
        const WARN_THRESHOLD = 10000;
        const MAX_DISPLAY_THRESHOLD = 50000;

        document.getElementById('results-report-name').textContent = data.report_name;
        document.getElementById('results-count').textContent = `${recordCount.toLocaleString()} records found`;

        // Enable export button for all result sizes
        const exportBtn = document.getElementById('export-btn');
        if (exportBtn) {
            if (recordCount === 0) {
                exportBtn.disabled = true;
                exportBtn.classList.add('disabled');
                exportBtn.title = 'No data available to export';
                exportBtn.style.opacity = '0.5';
                exportBtn.style.cursor = 'not-allowed';
            } else {
                exportBtn.disabled = false;
                exportBtn.classList.remove('disabled');
                exportBtn.title = 'Export report in various formats';
                exportBtn.style.opacity = '1';
                exportBtn.style.cursor = 'pointer';
            }
        }

        // CHECK 1: Large dataset - Enable Export Mode
        if (recordCount > MAX_DISPLAY_THRESHOLD) {
            const tableContainer = document.getElementById('results-table');
            tableContainer.innerHTML = `
                <div class="alert alert-info" style="padding: 20px; margin: 20px 0; border-left: 4px solid #5bc0de;">
                    <h4 style="margin-top: 0;"><i class="fa fa-download"></i> Large Dataset - Export Mode</h4>
                    <p style="font-size: 16px;">Your report has successfully generated <strong>${recordCount.toLocaleString()} records</strong>.</p>
                    <p>For datasets of this size, we've automatically enabled <strong>Export Mode</strong> to provide you with the best experience.</p>
                    <hr style="margin: 15px 0;">
                    <h5><i class="fa fa-file-text-o"></i> Download Your Data:</h5>
                    <p>Use the <strong>Export</strong> button above to download your complete report:</p>
                    <ul>
                        <li><strong>CSV</strong> - Perfect for Excel, data analysis, and pivot tables</li>
                        <li><strong>Excel (.xlsx)</strong> - Formatted spreadsheet with all features</li>
                        <li><strong>PDF</strong> - Professional document for presentations and reports</li>
                    </ul>
                    <hr style="margin: 15px 0;">
                    <p><i class="fa fa-lightbulb-o"></i> <strong>Pro Tip:</strong> For browser viewing, consider adding filters or date ranges to your report parameters to narrow down the results.</p>
                </div>
            `;

            this.goToStep('step-results');
            this.hideLoading();
            return; // Export mode activated
        }

        // CHECK 2: Large dataset - Offer viewing options
        if (recordCount > WARN_THRESHOLD) {
            const proceed = confirm(
                `📊 Large Dataset Detected\n\n` +
                `Your report contains ${recordCount.toLocaleString()} records.\n\n` +
                `Choose how you'd like to proceed:\n\n` +
                `✓ Click OK to view in browser\n` +
                `   (Data will be paginated for easy browsing)\n\n` +
                `✓ Click Cancel to use Export Mode\n` +
                `   (Recommended for data analysis and Excel)\n\n` +
                `Which would you prefer?`
            );

            if (!proceed) {
                // User chose Export Mode
                const tableContainer = document.getElementById('results-table');
                tableContainer.innerHTML = `
                    <div class="alert alert-success" style="padding: 20px; margin: 20px 0; border-left: 4px solid #5cb85c;">
                        <h4 style="margin-top: 0;"><i class="fa fa-check-circle"></i> Export Mode Selected</h4>
                        <p style="font-size: 16px;">Great choice! Export Mode is optimized for datasets with <strong>${recordCount.toLocaleString()} records</strong>.</p>
                        <hr style="margin: 15px 0;">
                        <p><i class="fa fa-download"></i> Use the <strong>Export</strong> button above to download your complete report in your preferred format.</p>
                        <p style="margin-top: 10px;"><em>Your data is ready and waiting for you!</em></p>
                    </div>
                `;

                this.goToStep('step-results');
                this.hideLoading();
                return;
            }

            // User chose to view in browser
            this.showLoading(`Preparing ${recordCount.toLocaleString()} records for display... Please wait.`);
        }

        // Display table (with progressive rendering for large datasets)
        this.displayTable(data.results, data.headers);

        // Setup chart controls with configurable axes
        this.setupChartControls(data.results, data.headers);

        // Display initial chart if available, or render from selectors
        if (data.chart_data) {
            this.displayChart(data.chart_data, data.chart_type);
        } else {
            // Render chart using selectors
            this.renderChartFromSelectors();
        }
    }

    /**
     * Format a value if it's a date/timestamp
     * @param {string} header - Column header name
     * @param {*} value - The value to potentially format
     * @returns {string} Formatted value
     */
    formatDateIfNeeded(header, value) {
        // If value is empty, return as-is
        if (value === null || value === undefined || value === '') {
            return '';
        }

        // Convert to string for processing
        const strValue = String(value);

        // Check if column name suggests it's a date/time field
        const headerLower = header.toLowerCase();

        // First, check if column name suggests it's a COUNT/NUMBER field (not a date)
        // These should never be formatted as dates even if they contain date-like keywords
        const isCountColumn = headerLower.includes('count') ||
                             headerLower.includes('total') ||
                             headerLower.includes('sum') ||
                             headerLower.includes('avg') ||
                             headerLower.includes('num_') ||
                             headerLower.includes('_num') ||
                             headerLower.includes('amount') ||
                             headerLower.includes('quantity') ||
                             headerLower.includes('distinct') ||
                             headerLower.includes('unique') ||
                             headerLower.includes('hits') ||
                             headerLower.includes('_id') ||
                             headerLower.endsWith('id');

        // If it's a count/number column, return as-is (don't format as date)
        if (isCountColumn) {
            return strValue;
        }

        const isDateColumn = headerLower.includes('date') ||
                            headerLower.includes('time') ||
                            headerLower.includes('created') ||
                            headerLower.includes('modified') ||
                            headerLower.includes('lastaccess') ||
                            headerLower.includes('last_access') ||
                            headerLower.includes('timestamp') ||
                            headerLower.includes('login') ||
                            headerLower.includes('logout');

        // Check if value looks like a Unix timestamp
        // Unix timestamps are typically 10 digits (seconds) or 13 digits (milliseconds)
        // Range: 946684800 (Jan 1, 2000) to 2147483647 (Jan 19, 2038 for 32-bit)
        const numValue = Number(strValue);
        const isTimestamp = !isNaN(numValue) &&
                           numValue > 946684800 &&
                           numValue < 2147483647;

        // If it's a date column or looks like a timestamp, format it
        if ((isDateColumn || isTimestamp) && !isNaN(numValue) && numValue > 0) {
            try {
                // Handle both seconds and milliseconds timestamps
                const timestamp = numValue < 10000000000 ? numValue * 1000 : numValue;
                const date = new Date(timestamp);

                // Check if date is valid
                if (!isNaN(date.getTime())) {
                    // Format as DD-MM-YYYY or DD-MM-YYYY HH:MM
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear();
                    const hours = date.getHours();
                    const minutes = date.getMinutes();

                    // If it's midnight (00:00), just show the date
                    if (hours === 0 && minutes === 0) {
                        return `${day}-${month}-${year}`;
                    }

                    // Otherwise show date and time
                    const hoursStr = String(hours).padStart(2, '0');
                    const minutesStr = String(minutes).padStart(2, '0');
                    return `${day}-${month}-${year} ${hoursStr}:${minutesStr}`;
                }
            } catch (e) {
                console.warn('Failed to format date:', value, e);
            }
        }

        return strValue;
    }

    displayTable(results, headers) {
        const tableContainer = document.getElementById('results-table');

        if (results.length === 0) {
            tableContainer.innerHTML = '<p>No data found for the selected criteria.</p>';
            return;
        }

        let tableHtml = '<table id="adeptus-results-table" class="table table-striped table-hover"><thead><tr>';
        headers.forEach(header => {
            tableHtml += `<th>${header}</th>`;
        });
        tableHtml += '</tr></thead><tbody>';

        results.forEach(row => {
            tableHtml += '<tr>';
            headers.forEach(header => {
                const formattedValue = this.formatDateIfNeeded(header, row[header]);
                tableHtml += `<td>${formattedValue}</td>`;
            });
            tableHtml += '</tr>';
        });

        tableHtml += '</tbody></table>';
        tableContainer.innerHTML = tableHtml;

        // Initialize simple-datatables.js with 15 results per page and search enabled
        if (window.adeptusResultsDataTable) {
            window.adeptusResultsDataTable.destroy();
            window.adeptusResultsDataTable = null;
        }
        if (window.DataTable) {
            window.adeptusResultsDataTable = new window.DataTable('#adeptus-results-table', {
                perPage: 15,
                perPageSelect: [5, 10, 15, 20, 25, 50],
                searchable: true,
                paging: true
            });
        } else {
            // Fallback: try to load simple-datatables.js dynamically if not loaded
            const localJs = (window.M && window.M.cfg && window.M.cfg.wwwroot ? window.M.cfg.wwwroot : '') + '/report/adeptus_insights/amd/vendor/simple-datatables.js';
            const localCss = (window.M && window.M.cfg && window.M.cfg.wwwroot ? window.M.cfg.wwwroot : '') + '/report/adeptus_insights/amd/vendor/style.css';
            if (!document.querySelector('link[href="' + localCss + '"]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = localCss;
                document.head.appendChild(link);
            }
            const script = document.createElement('script');
            script.src = localJs;
            script.onload = () => {
                // Support both global and namespaced DataTable
                const DataTableClass = window.DataTable || (window.simpleDatatables && window.simpleDatatables.DataTable);
                if (DataTableClass) {
                    window.adeptusResultsDataTable = new DataTableClass('#adeptus-results-table', {
                        perPage: 15,
                        perPageSelect: [5, 10, 15, 20, 25, 50],
                        searchable: true,
                        paging: true
                    });
                } else {
                    console.error('DataTable class not found after loading simple-datatables.js');
                }
            };
            document.head.appendChild(script);
        }
    }

    async displayChart(chartData, chartType) {
        const chartContainer = document.getElementById('results-chart');
        if (!chartContainer || !chartData) {
            console.error('Chart container or data not available');
            return;
        }

        // Clear previous chart instance
        if (window.adeptusResultsChartInstance) {
            window.adeptusResultsChartInstance.destroy();
        }

        // Create canvas element for chart
        chartContainer.innerHTML = '<canvas id="chart-canvas" width="400" height="300"></canvas>';
        const ctx = document.getElementById('chart-canvas');

        let chartConfig;

        // Check if data is already in Chart.js format (has labels and datasets)
        if (chartData.labels && chartData.datasets) {
            // Data is already in Chart.js format
            let processedChartData = { ...chartData };
            
            // For pie charts, enhance labels with values and percentages
            if (chartType.toLowerCase() === 'pie' || chartType.toLowerCase() === 'donut' || chartType.toLowerCase() === 'polar') {
                const labels = chartData.labels;
                const values = chartData.datasets[0].data;
                const total = values.reduce((sum, val) => sum + val, 0);
                
                // Create enhanced labels with values and percentages
                processedChartData.labels = labels.map((label, index) => {
                    const value = values[index];
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                    return `${label}: ${value} (${percentage}%)`;
                });
            }
            
            chartConfig = {
                type: this.mapChartType(chartType),
                data: processedChartData,
                options: this.getChartOptionsWithAxisLabels(chartType, chartData.axis_labels)
            };
        } else if (Array.isArray(chartData) && chartData.length > 0) {
            // Data is in raw format, need to process it
            const headers = Object.keys(chartData[0]);
            const dataTypes = {};
            
            // Analyze data types for each column
            headers.forEach(header => {
                const sampleValues = chartData.slice(0, 5).map(row => row[header]);
                const isNumeric = sampleValues.every(val => !isNaN(parseFloat(val)) && isFinite(val));
                const isString = sampleValues.every(val => typeof val === 'string' || val === null || val === undefined);
                dataTypes[header] = isNumeric ? 'number' : 'string';
            });

            // Find appropriate columns for labels and values
            const numericColumns = headers.filter(h => dataTypes[h] === 'number');
            const stringColumns = headers.filter(h => dataTypes[h] === 'string');
            
            let labelKey = stringColumns[0] || headers[0];
            let valueKey = numericColumns[0] || headers[1];
            
            // If no numeric columns found, try to convert string columns to numbers
            if (numericColumns.length === 0) {
                const convertedColumns = headers.filter(h => {
                    const values = chartData.map(row => parseFloat(row[h]));
                    return values.every(val => !isNaN(val) && isFinite(val));
                });
                if (convertedColumns.length > 0) {
                    valueKey = convertedColumns[0];
                }
            }

            // Prepare chart data
            const labels = chartData.map(r => r[labelKey] || 'Unknown');
            const values = chartData.map(r => parseFloat(r[valueKey]) || 0);

            // Generate colors for chart
            const colors = this.generateChartColors(values.length, chartType);

            // Create chart configuration based on type
            chartConfig = this.createChartConfig(chartType, labels, values, valueKey, colors);
        } else {
            // Invalid data format
            console.error('Invalid chart data format');
            chartContainer.innerHTML = `
                <div class="chart-placeholder">
                    <i class="fa fa-exclamation-triangle"></i>
                    <p>Invalid chart data format</p>
                    <small>Chart Type: ${chartType}</small>
                </div>
            `;
            return;
        }

        // Create and render the chart
        try {
            // Wait for Chart.js to be available
            const ChartJS = await this.getChartJS();
            window.adeptusResultsChartInstance = new ChartJS(ctx.getContext('2d'), chartConfig);
        } catch (error) {
            console.error('Error creating chart:', error);
            chartContainer.innerHTML = '<div class="chart-placeholder"><i class="fa fa-exclamation-triangle"></i><p>Chart library not available. Please refresh the page.</p><small>Chart Type: ' + chartType + '</small></div>';
        }
    }

    getReportTitle() {
        // Find the current report in the wizard data
        if (this.wizardData.reports && this.selectedReport) {
            for (const category of Object.values(this.wizardData.reports)) {
                if (Array.isArray(category)) {
                    const report = category.find(r => r.id == this.selectedReport);
                    if (report) {
                        return report.name;
                    }
                }
            }
        }
        
        // Fallback: try to get from current results
        if (this.currentResults && this.currentResults.report_name) {
            return this.currentResults.report_name;
        }
        
        // Fallback: try to get from wizard data
        if (this.wizardData.current_report_name) {
            return this.wizardData.current_report_name;
        }
        
        return 'Report Chart';
    }

    createChartConfig(chartType, labels, values, valueKey, colors) {
        const baseConfig = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: this.selectedReport ? this.getReportTitle() : 'Chart',
                    font: {
                        size: 16,
                        weight: 'bold'
                    },
                    padding: {
                        top: 10,
                        bottom: 20
                    }
                },
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    enabled: true
                }
            }
        };

        // For pie charts, enhance labels with values and percentages
        let enhancedLabels = labels;
        if (chartType.toLowerCase() === 'pie' || chartType.toLowerCase() === 'donut' || chartType.toLowerCase() === 'polar') {
            const total = values.reduce((sum, val) => sum + val, 0);
            enhancedLabels = labels.map((label, index) => {
                const value = values[index];
                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                return `${label}: ${value} (${percentage}%)`;
            });
        }

        switch (chartType.toLowerCase()) {
            case 'pie':
                return {
                    type: 'pie',
                    data: {
                        labels: enhancedLabels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderColor: colors.map(c => this.adjustColor(c, -20)),
                            borderWidth: 2
                        }]
                    },
                    options: {
                        ...baseConfig,
                        plugins: {
                            ...baseConfig.plugins,
                            legend: {
                                display: true,
                                position: 'right'
                            }
                        }
                    }
                };

            case 'donut':
                return {
                    type: 'doughnut',
                    data: {
                        labels: enhancedLabels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderColor: colors.map(c => this.adjustColor(c, -20)),
                            borderWidth: 2
                        }]
                    },
                    options: {
                        ...baseConfig,
                        plugins: {
                            ...baseConfig.plugins,
                            legend: {
                                display: true,
                                position: 'right'
                            }
                        }
                    }
                };

            case 'line':
                return {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: valueKey,
                            data: values,
                            borderColor: colors[0],
                            backgroundColor: this.adjustColor(colors[0], 80),
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        ...baseConfig,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            }
                        }
                    }
                };

            case 'radar':
                return {
                    type: 'radar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: valueKey,
                            data: values,
                            borderColor: colors[0],
                            backgroundColor: this.adjustColor(colors[0], 80),
                            borderWidth: 2,
                            fill: true
                        }]
                    },
                    options: {
                        ...baseConfig,
                        scales: {
                            r: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            }
                        }
                    }
                };

            case 'polar':
                return {
                    type: 'polarArea',
                    data: {
                        labels: enhancedLabels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderColor: colors.map(c => this.adjustColor(c, -20)),
                            borderWidth: 2
                        }]
                    },
                    options: {
                        ...baseConfig,
                        plugins: {
                            ...baseConfig.plugins,
                            legend: {
                                display: true,
                                position: 'right'
                            }
                        }
                    }
                };

            case 'bubble':
                // For bubble charts, we need at least 3 numeric columns
                return {
                    type: 'bubble',
                    data: {
                        datasets: [{
                            label: valueKey,
                            data: values.map((value, index) => ({
                                x: index,
                                y: value,
                                r: Math.sqrt(value) * 2
                            })),
                            backgroundColor: colors[0],
                            borderColor: this.adjustColor(colors[0], -20),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ...baseConfig,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            }
                        }
                    }
                };

            case 'bar':
            default:
                return {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: valueKey,
                            data: values,
                            backgroundColor: colors,
                            borderColor: colors.map(c => this.adjustColor(c, -20)),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ...baseConfig,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            }
                        }
                    }
                };
        }
    }

    generateChartColors(count, chartType) {
        const baseColors = [
            '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
            '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1',
            '#14b8a6', '#a855f7', '#eab308', '#22c55e', '#3b82f6'
        ];

        if (chartType.toLowerCase() === 'pie' || chartType.toLowerCase() === 'donut' || chartType.toLowerCase() === 'polar') {
            // Generate distinct colors for each data point
            const colors = [];
            for (let i = 0; i < count; i++) {
                colors.push(baseColors[i % baseColors.length]);
            }
            return colors;
        } else {
            // Use colors for each bar/point
            const colors = [];
            for (let i = 0; i < count; i++) {
                colors.push(baseColors[i % baseColors.length]);
            }
            return colors;
        }
    }

    /**
     * Detect numeric columns in data
     */
    detectNumericColumns(data, headers) {
        if (!data || data.length === 0) return [];
        return headers.filter(header => {
            let numericCount = 0;
            const sampleSize = Math.min(data.length, 20);
            for (let i = 0; i < sampleSize; i++) {
                const val = data[i][header];
                if (val !== null && val !== undefined && val !== '') {
                    const num = parseFloat(val);
                    if (!isNaN(num) && isFinite(num)) {
                        numericCount++;
                    }
                }
            }
            return numericCount >= sampleSize * 0.5;
        });
    }

    /**
     * Setup chart controls with axis selectors
     */
    setupChartControls(data, headers) {
        const chartControls = document.getElementById('chart-controls');
        if (!chartControls || !data || data.length === 0) return;

        const numericCols = this.detectNumericColumns(data, headers);

        let controlsHtml = '<div class="chart-controls d-flex flex-wrap align-items-end gap-3">';

        // Chart type selector
        controlsHtml += '<div class="control-group">';
        controlsHtml += '<label for="wizard-chart-type" class="form-label">Chart Type</label>';
        controlsHtml += '<select id="wizard-chart-type" class="form-select form-select-sm">';
        controlsHtml += '<option value="bar">Bar Chart</option>';
        controlsHtml += '<option value="line">Line Chart</option>';
        controlsHtml += '<option value="pie">Pie Chart</option>';
        controlsHtml += '<option value="doughnut">Doughnut Chart</option>';
        controlsHtml += '</select></div>';

        // X-Axis selector
        controlsHtml += '<div class="control-group">';
        controlsHtml += '<label for="wizard-chart-x-axis" class="form-label">X-Axis (Labels)</label>';
        controlsHtml += '<select id="wizard-chart-x-axis" class="form-select form-select-sm">';
        headers.forEach((header, idx) => {
            const formattedHeader = header.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            const selected = idx === 0 ? ' selected' : '';
            controlsHtml += `<option value="${header}"${selected}>${formattedHeader}</option>`;
        });
        controlsHtml += '</select></div>';

        // Y-Axis selector (only numeric columns)
        controlsHtml += '<div class="control-group">';
        controlsHtml += '<label for="wizard-chart-y-axis" class="form-label">Y-Axis (Values)</label>';
        controlsHtml += '<select id="wizard-chart-y-axis" class="form-select form-select-sm">';
        if (numericCols.length > 0) {
            numericCols.forEach((col, idx) => {
                const formattedHeader = col.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                const selected = idx === numericCols.length - 1 ? ' selected' : '';
                controlsHtml += `<option value="${col}"${selected}>${formattedHeader}</option>`;
            });
        } else {
            // Fallback to all columns if no numeric found
            headers.forEach((header, idx) => {
                const formattedHeader = header.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                const selected = idx === headers.length - 1 ? ' selected' : '';
                controlsHtml += `<option value="${header}"${selected}>${formattedHeader}</option>`;
            });
        }
        controlsHtml += '</select></div>';

        controlsHtml += '</div>'; // End chart-controls

        chartControls.innerHTML = controlsHtml;

        // Bind change events
        const self = this;
        document.getElementById('wizard-chart-type')?.addEventListener('change', () => self.renderChartFromSelectors());
        document.getElementById('wizard-chart-x-axis')?.addEventListener('change', () => self.renderChartFromSelectors());
        document.getElementById('wizard-chart-y-axis')?.addEventListener('change', () => self.renderChartFromSelectors());
    }

    /**
     * Render chart using selected axis values
     */
    async renderChartFromSelectors() {
        const data = this.currentResults && this.currentResults.results;
        if (!data || data.length === 0) return;

        const chartType = document.getElementById('wizard-chart-type') ? document.getElementById('wizard-chart-type').value : 'bar';
        const labelKey = document.getElementById('wizard-chart-x-axis') ? document.getElementById('wizard-chart-x-axis').value : null;
        const valueKey = document.getElementById('wizard-chart-y-axis') ? document.getElementById('wizard-chart-y-axis').value : null;

        if (!labelKey || !valueKey) return;

        const chartContainer = document.getElementById('results-chart');
        if (!chartContainer) return;

        // Destroy existing chart
        if (window.adeptusResultsChartInstance) {
            window.adeptusResultsChartInstance.destroy();
        }

        // Create canvas
        chartContainer.innerHTML = '<canvas id="chart-canvas"></canvas>';
        const ctx = document.getElementById('chart-canvas');

        // Limit data for chart (max 50 items)
        const chartData = data.slice(0, 50);
        const labels = chartData.map(r => {
            const label = r[labelKey];
            if (label === null || label === undefined) return 'Unknown';
            const labelStr = String(label);
            return labelStr.length > 30 ? labelStr.substring(0, 30) + '...' : labelStr;
        });
        const values = chartData.map(r => parseFloat(r[valueKey]) || 0);
        const colors = this.generateChartColors(values.length, chartType);

        const valueKeyFormatted = valueKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        const reportName = this.getReportTitle();

        const chartConfig = this.createConfigurableChartConfig(chartType, labels, values, valueKeyFormatted, colors, reportName);

        try {
            // Wait for Chart.js to be available
            const ChartJS = await this.getChartJS();
            window.adeptusResultsChartInstance = new ChartJS(ctx.getContext('2d'), chartConfig);
        } catch (error) {
            console.error('Error creating chart:', error);
            chartContainer.innerHTML = '<div class="chart-placeholder"><i class="fa fa-exclamation-triangle"></i><p>Chart library not available. Please refresh the page.</p></div>';
        }
    }

    /**
     * Create chart config for configurable axes
     */
    createConfigurableChartConfig(chartType, labels, values, valueKey, colors, reportName) {
        const baseConfig = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: reportName || 'Report Chart',
                    font: { size: 16, weight: 'bold' },
                    padding: { top: 10, bottom: 20 }
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
                options: {
                    ...baseConfig,
                    scales: {
                        y: { beginAtZero: true },
                        x: { ticks: { maxRotation: 45, minRotation: 45 } }
                    }
                }
            };
        } else {
            // Bar chart (default)
            return {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: valueKey,
                        data: values,
                        backgroundColor: colors,
                        borderColor: colors,
                        borderWidth: 1
                    }]
                },
                options: {
                    ...baseConfig,
                    scales: {
                        y: { beginAtZero: true },
                        x: { ticks: { maxRotation: 45, minRotation: 45 } }
                    }
                }
            };
        }
    }

    adjustColor(color, amount) {
        const usePound = color[0] === "#";
        const col = usePound ? color.slice(1) : color;
        const num = parseInt(col, 16);
        let r = (num >> 16) + amount;
        let g = (num >> 8 & 0x00FF) + amount;
        let b = (num & 0x0000FF) + amount;
        
        r = r > 255 ? 255 : r < 0 ? 0 : r;
        g = g > 255 ? 255 : g < 0 ? 0 : g;
        b = b > 255 ? 255 : b < 0 ? 0 : b;
        
        return (usePound ? "#" : "") + (r << 16 | g << 8 | b).toString(16).padStart(6, '0');
    }

    mapChartType(chartType) {
        switch (chartType.toLowerCase()) {
            case 'pie': return 'pie';
            case 'donut': return 'doughnut';
            case 'line': return 'line';
            case 'radar': return 'radar';
            case 'polar': return 'polarArea';
            case 'bubble': return 'bubble';
            case 'bar':
            default: return 'bar';
        }
    }

    getChartOptions(chartType) {
        const baseConfig = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    enabled: true
                }
            }
        };

        switch (chartType.toLowerCase()) {
            case 'pie':
            case 'donut':
            case 'polar':
                return {
                    ...baseConfig,
                    plugins: {
                        ...baseConfig.plugins,
                        legend: {
                            display: true,
                            position: 'right'
                        }
                    }
                };

            case 'line':
            case 'bar':
                return {
                    ...baseConfig,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                };

            case 'radar':
                return {
                    ...baseConfig,
                    scales: {
                        r: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                };

            case 'bubble':
                return {
                    ...baseConfig,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                };

            default:
                return baseConfig;
        }
    }

    getChartOptionsWithAxisLabels(chartType, axisLabels) {
        const baseConfig = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: this.selectedReport ? this.getReportTitle() : 'Chart',
                    font: {
                        size: 16,
                        weight: 'bold'
                    },
                    padding: {
                        top: 10,
                        bottom: 20
                    }
                },
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    enabled: true
                }
            }
        };

        // Format axis labels for better display
        const formatAxisLabel = (label) => {
            if (!label) return '';
            // Convert "course name" to "Course Name", "total size (mb)" to "Total Size (MB)"
            return label.split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        };

        const xAxisLabel = formatAxisLabel(axisLabels?.x_axis);
        const yAxisLabel = formatAxisLabel(axisLabels?.y_axis);

        switch (chartType.toLowerCase()) {
            case 'pie':
            case 'donut':
            case 'polar':
                return {
                    ...baseConfig,
                    plugins: {
                        ...baseConfig.plugins,
                        legend: {
                            display: true,
                            position: 'right'
                        }
                    }
                };

            case 'line':
            case 'bar':
                return {
                    ...baseConfig,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: yAxisLabel,
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: xAxisLabel,
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                };

            case 'radar':
                return {
                    ...baseConfig,
                    scales: {
                        r: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: yAxisLabel,
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                };

            case 'bubble':
                return {
                    ...baseConfig,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: yAxisLabel,
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: xAxisLabel,
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                };

            default:
                return baseConfig;
        }
    }

    goToStep(stepId) {
        // Hide all steps
        document.querySelectorAll('.wizard-step').forEach(step => {
            step.classList.remove('active');
        });
        
        // Show target step
        document.getElementById(stepId).classList.add('active');
        this.currentStep = stepId;
        
        // Scroll to top
        document.getElementById('wizard-container').scrollIntoView({ behavior: 'smooth' });
    }

    handleLoadConfiguration(reportId, action, parameters) {
        this.selectedReport = reportId;
        
        // Parse saved parameters if available
        let savedParams = {};
        if (parameters && parameters !== 'undefined') {
            try {
                savedParams = JSON.parse(parameters);
            } catch (e) {
                console.warn('Could not parse saved parameters:', parameters);
            }
        }
        
        if (action === 'recent' || action === 'generated') {
            // Load the report configuration with saved parameters
            this.loadReportParameters(reportId, savedParams);
            this.goToStep('step-configure');
        } else if (action === 'bookmark') {
            // Load the report configuration for bookmarked report
            this.loadReportParameters(reportId);
            this.goToStep('step-configure');
        }
    }

    toggleExportMenu() {
        // Check if export button is disabled
        const exportBtn = document.getElementById('export-btn');
        if (exportBtn && (exportBtn.disabled || exportBtn.classList.contains('disabled'))) {
            return;
        }
        
        const exportMenu = document.getElementById('export-menu');
        exportMenu.classList.toggle('show');
    }

    async exportReport(format) {
        // Check if export button is disabled (limit reached)
        const exportBtn = document.getElementById('export-btn');
        if (exportBtn && exportBtn.disabled) {
            return;
        }

        const reportName = this.getReportTitle();
        this.showLoading(`Exporting ${reportName} as ${format.toUpperCase()}...`);
        
        try {
            // First, check if user is eligible to export
            const eligibilityResponse = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/check_export_eligibility.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `format=${format}&sesskey=${this.wizardData.sesskey}`
            });
            
            const eligibilityData = await eligibilityResponse.json();
            if (!eligibilityData.success || !eligibilityData.eligible) {
                this.showError(eligibilityData.message || 'You are not eligible to export in this format.');
                return;
            }
            
            let endpoint = 'export_report.php';
            let body = `reportid=${this.selectedReport}&format=${format}&sesskey=${this.wizardData.sesskey}`;
            
            // Add view type to the request (table or chart)
            body += `&view=${this.currentView}`;
            
            // Send the actual report data instead of regenerating it
            if (this.currentResults) {
                body += `&report_data=${encodeURIComponent(JSON.stringify(this.currentResults))}`;
            }
            
            // If exporting chart, include chart data and capture chart image for PDF
            if (this.currentView === 'chart' && this.currentResults && this.currentResults.chart_data) {
                body += `&chart_data=${encodeURIComponent(JSON.stringify(this.currentResults.chart_data))}`;
                body += `&chart_type=${encodeURIComponent(this.currentResults.chart_type || 'bar')}`;
                
                // For PDF export, capture chart as image
                if (format === 'pdf') {
                    try {
                        // Add timeout to chart capture
                        const chartImagePromise = this.captureChartAsImage();
                        const timeoutPromise = new Promise((_, reject) => 
                            setTimeout(() => reject(new Error('Chart capture timeout')), 10000)
                        );
                        
                        const chartImage = await Promise.race([chartImagePromise, timeoutPromise]);
                        
                        if (chartImage && chartImage.length < 1000000) { // Limit to 1MB
                            body += `&chart_image=${encodeURIComponent(chartImage)}`;
                        } else {
                            console.warn('Chart image too large or failed to capture, proceeding without image');
                        }
                    } catch (error) {
                        console.warn('Failed to capture chart image:', error);
                        // Continue without chart image
                    }
                }
            }
            
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: body
            });


            // Check if response is an error (JSON without attachment disposition)
            // JSON export legitimately returns application/json, so we need to check Content-Disposition
            const contentType = response.headers.get('content-type');
            const contentDisposition = response.headers.get('content-disposition');
            const isFileDownload = contentDisposition && contentDisposition.includes('attachment');

            if (contentType && contentType.includes('application/json') && !isFileDownload) {
                // This is an error response, not a file download
                const errorData = await response.json();
                // Only log actual errors, not restrictions
                if (errorData.error !== 'dataset_too_large') {
                    console.error('Export error:', errorData.message || errorData.error);
                }
                const error = new Error(errorData.message || 'Export failed');
                error.customTitle = errorData.title; // Store custom title if provided
                error.errorType = errorData.error; // Store error type
                throw error;
            }
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error(`Export failed with status: ${response.status}`);
            }

            // Generate meaningful filename
            const reportName = this.getReportTitle();
            const sanitizedName = reportName.replace(/[^a-zA-Z0-9\s-]/g, '').replace(/\s+/g, '_').toLowerCase();
            const dateSuffix = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
            const viewSuffix = this.currentView === 'chart' ? 'chart' : 'table';
            
            // Set correct file extension for Excel
            const fileExtension = format === 'excel' ? 'csv' : format;
            
            // Download the file
                const blob = await response.blob();
            
            if (blob.size < 1000) {
                console.warn('Export file is suspiciously small:', blob.size, 'bytes - might be an error');
            }
            
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
            a.download = `${sanitizedName}_${viewSuffix}_${dateSuffix}.${fileExtension}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
            if (format === 'pdf') {
                this.showSuccess('PDF file downloaded successfully!');
            } else {
                this.showSuccess(`${format.toUpperCase()} file downloaded successfully!`);
            }
            
            // Track export in backend after successful download
            await this.trackExport(format);
            
            this.hideExportMenu();
        } catch (error) {
            // Only log actual errors, not restrictions
            if (error.errorType !== 'dataset_too_large') {
                console.error('Error exporting report:', error);
            }

            // Check if it's a response error
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                this.showError('Network error. Please check your connection and try again.');
            } else {
                // Show the specific error message from the backend
                const message = error.message || 'Error exporting report. Please try again.';
                const title = error.customTitle || 'Error';
                this.showPopup(title, message, 'error');
            }
        } finally {
            this.hideLoading();
        }
    }

    async captureChartAsImage() {
        try {
            const chartContainer = document.getElementById('results-chart');
            if (!chartContainer) {
                console.warn('Chart container not found');
                return null;
            }
            
            const canvas = chartContainer.querySelector('canvas');
            if (!canvas) {
                console.warn('Chart canvas not found');
                return null;
            }
            
            // Try native canvas method first (more reliable)
            try {
                const dataUrl = canvas.toDataURL('image/png', 0.8); // Reduce quality to 80%
                if (dataUrl && dataUrl.length > 0) {
                    return dataUrl;
                }
            } catch (canvasError) {
                console.warn('Native canvas capture failed:', canvasError);
            }
            
            // Fallback to html2canvas if native method fails
            try {
                if (!window.html2canvas) {
                    await this.loadHTML2Canvas();
                }
                
                if (window.html2canvas) {
                    const chartImage = await html2canvas(canvas, {
                        backgroundColor: '#ffffff',
                        scale: 1.5, // Reduced scale for smaller file size
                        useCORS: true,
                        allowTaint: true,
                        logging: false
                    });
                    return chartImage.toDataURL('image/png', 0.8);
                }
            } catch (html2canvasError) {
                console.warn('HTML2Canvas capture failed:', html2canvasError);
            }
            
            return null;
        } catch (error) {
            console.error('Error capturing chart image:', error);
            return null;
        }
    }

    async loadHTML2Canvas() {
        return new Promise((resolve, reject) => {
            if (window.html2canvas) {
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load HTML2Canvas'));
            document.head.appendChild(script);
        });
    }

    hideExportMenu() {
        document.getElementById('export-menu').classList.remove('show');
    }

    async toggleBookmark() {
        this.showLoading('Updating bookmark...');
        try {
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/bookmark_report.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `reportid=${encodeURIComponent(this.selectedReport)}&action=toggle&sesskey=${this.wizardData.sesskey}`
            });

            const data = await response.json();
            
            if (data.success) {
                if (data.action === 'added') {
                this.showSuccess('Report bookmarked successfully!');
                    if (!this.wizardData.bookmarked_report_ids.includes(this.selectedReport)) {
                        this.wizardData.bookmarked_report_ids.push(this.selectedReport);
                    }
                    // Add to Bookmarked Reports section live
                    this.addBookmarkCardLive(this.selectedReport);
                } else {
                    this.showSuccess('Bookmark removed successfully!');
                    const index = this.wizardData.bookmarked_report_ids.indexOf(this.selectedReport);
                    if (index > -1) {
                        this.wizardData.bookmarked_report_ids.splice(index, 1);
                    }
                    // Remove from Bookmarked Reports section live
                    this.removeBookmarkCardLive(this.selectedReport);
                }
                this.updateBookmarkButton(data.bookmarked);
            } else {
                this.showError(data.message || 'Failed to toggle bookmark');
            }
        } catch (error) {
            console.error('Error toggling bookmark:', error);
            this.showError('Error toggling bookmark');
        } finally {
            this.hideLoading();
        }
    }

    async removeBookmark(reportId) {
        this.showLoading('Removing bookmark...');
        try {
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/bookmark_report.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `reportid=${encodeURIComponent(reportId)}&action=remove&sesskey=${this.wizardData.sesskey}`
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Bookmark removed successfully!');
                // Remove the bookmark card from view
                this.removeBookmarkCardLive(reportId);
                // Remove from bookmarked IDs
                const index = this.wizardData.bookmarked_report_ids.indexOf(reportId);
                if (index > -1) {
                    this.wizardData.bookmarked_report_ids.splice(index, 1);
                }
                // Update bookmark indicators in report cards if they're currently displayed
                const reportCards = document.querySelectorAll(`[data-report-id="${reportId}"]`);
                reportCards.forEach(reportCard => {
                    const bookmarkIndicator = reportCard.querySelector('.bookmark-indicator');
                    if (bookmarkIndicator) {
                        bookmarkIndicator.remove();
                    }
                });
                // Update bookmark button state if this report is currently selected
                if (this.selectedReport == reportId) {
                    this.updateBookmarkButton(false);
                }
            } else {
                this.showError(data.message || 'Failed to remove bookmark');
            }
        } catch (error) {
            console.error('Error removing bookmark:', error);
            this.showError('Error removing bookmark');
        } finally {
            this.hideLoading();
        }
    }

    switchView(viewName) {
        // Update toggle buttons
        document.querySelectorAll('.view-toggle-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        const activeBtn = document.querySelector(`[data-view="${viewName}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        // Update view panels
        document.querySelectorAll('.view-panel').forEach(panel => {
            panel.classList.add('d-none');
        });
        const activePanel = document.getElementById(`${viewName}-view`);
        if (activePanel) {
            activePanel.classList.remove('d-none');
        }

        // Track current view
        this.currentView = viewName;

        // Render chart when switching to chart view
        if (viewName === 'chart' && this.currentResults?.results) {
            this.renderChartFromSelectors();
        }
    }

    // Keep legacy switchTab for backward compatibility
    switchTab(tabName) {
        this.switchView(tabName);
    }

    showLoading(message = 'Loading...') {
        const overlay = document.getElementById('loading-overlay');
        overlay.querySelector('h3').textContent = message;
        overlay.classList.add('show');
    }

    hideLoading() {
        document.getElementById('loading-overlay').classList.remove('show');
    }

    showError(message) {
        this.showPopup('Error', message, 'error');
    }

    showSuccess(message) {
        // Use toast-style notification (matching AI Assistant style)
        this.showToast(message, 'success');
    }

    showToast(message, type = 'success') {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('adeptus-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'adeptus-toast-container';
            toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10050;';
            document.body.appendChild(toastContainer);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
        toast.style.cssText = 'min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-bottom: 10px;';
        toast.innerHTML = `
            <i class="fa fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
            <span>${message}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.5rem;"></button>
        `;

        toastContainer.appendChild(toast);

        // Auto-remove after 4 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 150);
        }, 4000);

        // Also allow manual close
        toast.querySelector('.btn-close').addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 150);
        });
    }

    showPopup(title, message, type = 'success') {
        const popup = document.getElementById('popup-overlay');
        const content = document.getElementById('popup-content');
        const titleEl = document.getElementById('popup-title');
        const messageEl = document.getElementById('popup-message');
        
        titleEl.textContent = title;
        messageEl.textContent = message;
        
        // Reset classes
        content.classList.remove('success', 'error');
        content.classList.add(type);
        
        popup.classList.add('show');
        
        // Auto-hide after 3 seconds for success messages
        if (type === 'success') {
            setTimeout(() => {
                this.hidePopup();
            }, 3000);
        }
    }

    hidePopup() {
        const popup = document.getElementById('popup-overlay');
        popup.classList.remove('show');
    }

    async     updateReportsLeftCounter() {
        
        // Only update if user is on free plan
        if (!this.wizardData.is_free_plan) {
            return;
        }

        // Show loader
        const counterContent = document.getElementById('reports-counter-content');
        if (counterContent) {
            counterContent.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading...';
        }

        // Debounce multiple calls
        if (this._counterUpdateTimeout) {
            clearTimeout(this._counterUpdateTimeout);
        }
        
        this._counterUpdateTimeout = setTimeout(async () => {
            try {
            
            // Get current subscription status
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/check_subscription_status.php?t=${Date.now()}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });

            const data = await response.json();
            
            if (data.success && data.data) {
                
                // Get values from data.data (which includes values from subscription plan)
                const reportsUsed = data.data.reports_generated_this_month || 0;
                const reportsLimit = data.data.plan_exports_limit; // From subscription plan
                const reportsRemaining = Math.max(0, reportsLimit - reportsUsed);
                const usageType = data.data.usage_type || 'all-time';
                const isFreePlan = data.data.is_free_plan || false;


                // Update the counter display
                const counterContent = document.getElementById('reports-counter-content');
                if (counterContent) {
                    counterContent.innerHTML = `<span id="reports-used-count">${reportsUsed}</span>/<span id="reports-limit-count">${reportsLimit}</span> Reports Generated`;
                }
                
                // Update usage type text
                const usageTypeEl = document.getElementById('usage-type-text');
                if (usageTypeEl) {
                    if (isFreePlan || usageType === 'all-time') {
                        usageTypeEl.textContent = 'Free Plan Total Limit';
                    } else {
                        usageTypeEl.textContent = 'Monthly Limit';
                    }
                }

                // Update the indicator styling based on remaining reports
                const indicator = document.querySelector('.reports-left-indicator');
                if (indicator) {
                    if (reportsRemaining <= 0) {
                        // No reports left - red styling
                        indicator.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
                        indicator.style.boxShadow = '0 2px 8px rgba(231, 76, 60, 0.3)';
                    } else if (reportsRemaining <= 2) {
                        // Low reports left - orange styling
                        indicator.style.background = 'linear-gradient(135deg, #f39c12 0%, #e67e22 100%)';
                        indicator.style.boxShadow = '0 2px 8px rgba(243, 156, 18, 0.3)';
                    } else {
                        // Normal reports left - blue styling
                        indicator.style.background = 'linear-gradient(135deg, #3498db 0%, #2980b9 100%)';
                        indicator.style.boxShadow = '0 2px 8px rgba(52, 152, 219, 0.3)';
                    }
                }

                // Disable/enable generate button based on remaining reports
                this.updateGenerateButtonState(reportsRemaining <= 0);

            } else {
                console.error('Failed to get subscription data:', data);
                // Show error in counter
                if (counterContent) {
                    counterContent.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
                }
            }
            } catch (error) {
                console.error('Error updating reports left counter:', error);
                // Show error in counter
                const counterContent = document.getElementById('reports-counter-content');
                if (counterContent) {
                    counterContent.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
                }
            }
        }, 1000); // 1 second debounce to allow backend to process
    }

    updateShowAllButton(sectionId, hasMore, totalCount) {
        const section = document.getElementById(sectionId);
        if (!section) return;

        const stepHeader = section.querySelector('.step-header');
        if (!stepHeader) return;

        // Remove existing show all button if present
        const existingBtn = stepHeader.querySelector('.btn-show-all');
        if (existingBtn) {
            existingBtn.remove();
        }

        // Only add button if there are more than 10 items
        if (hasMore) {
            const showAllBtn = document.createElement('button');
            showAllBtn.className = 'btn-show-all';
            showAllBtn.dataset.section = sectionId;
            showAllBtn.innerHTML = '<i class="fa fa-eye"></i> Show All (' + totalCount + ')';
            
            showAllBtn.addEventListener('click', () => {
                this.toggleShowAll(sectionId);
            });

            // Find section-actions div or create one
            let actionsDiv = stepHeader.querySelector('.section-actions');
            if (!actionsDiv) {
                actionsDiv = document.createElement('div');
                actionsDiv.className = 'section-actions';
                stepHeader.appendChild(actionsDiv);
            }

            // Insert show all button as first button
            actionsDiv.insertBefore(showAllBtn, actionsDiv.firstChild);
        }
    }

    toggleShowAll(sectionId) {
        const section = document.getElementById(sectionId);
        if (!section) return;

        const hiddenItems = section.querySelectorAll('.hidden-item');
        const showAllBtn = section.querySelector('.btn-show-all');
        
        if (!showAllBtn) return;

        const isExpanded = showAllBtn.classList.contains('expanded');

        if (isExpanded) {
            // Hide items
            hiddenItems.forEach(item => {
                item.style.display = 'none';
            });
            showAllBtn.innerHTML = showAllBtn.innerHTML.replace('Hide', 'Show All');
            showAllBtn.classList.remove('expanded');
        } else {
            // Show all items
            hiddenItems.forEach(item => {
                item.style.display = '';
            });
            showAllBtn.innerHTML = showAllBtn.innerHTML.replace('Show All', 'Hide');
            showAllBtn.classList.add('expanded');
        }
    }

    async trackExport(format) {
        // Ensure we have a report name
        if (!this.selectedReport) {
            console.warn('trackExport: No selectedReport set, skipping tracking');
            return;
        }

        try {
            // Use Moodle endpoint which handles both free and paid plans
            const url = `${this.wizardData.wwwroot}/report/adeptus_insights/ajax/track_export.php`;
            const body = `format=${encodeURIComponent(format)}&report_name=${encodeURIComponent(this.selectedReport)}&sesskey=${this.wizardData.sesskey}`;


            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: body
            });


            const data = await response.json();
            
            if (data.success) {
                // Update export counter in UI
                await this.updateExportsCounter();
            } else {
                console.warn('✗ Failed to track export:', data.message);
                // Still try to update the counter
                await this.updateExportsCounter();
            }
        } catch (error) {
            console.error('✗ Error tracking export:', error);
            console.error('Error details:', error.message, error.stack);
            // Still try to update the counter even if tracking fails
            try {
                await this.updateExportsCounter();
            } catch (e) {
                console.error('Failed to update counter after error:', e);
            }
        }
        
    }

    async updateExportsCounter() {
        
        // Show loader
        const counterContent = document.getElementById('exports-counter-content');
        if (counterContent) {
            counterContent.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading...';
        }
        
        try {
            // Get current subscription status
            const statusUrl = `${this.wizardData.wwwroot}/report/adeptus_insights/ajax/check_subscription_status.php?t=${Date.now()}`;
            
            const response = await fetch(statusUrl, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });

            const data = await response.json();
            
            if (data.success && data.data) {
                
                // Get values directly from data.data (top level)
                const exportsUsed = data.data.exports_used || 0;
                const exportsLimit = data.data.plan_exports_limit || 10; // From subscription plan
                const exportsRemaining = data.data.exports_remaining || Math.max(0, exportsLimit - exportsUsed);


                // Update the counter display
                if (counterContent) {
                    counterContent.innerHTML = `<span id="exports-used-count">${exportsUsed}</span>/<span id="exports-limit-count">${exportsLimit}</span> Exports Used`;
                }

                // Update indicator styling based on remaining exports
                const indicator = document.querySelector('.exports-left-indicator');
                if (indicator) {
                    if (exportsRemaining <= 0) {
                        // No exports left - red styling
                        indicator.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
                        indicator.style.boxShadow = '0 2px 8px rgba(231, 76, 60, 0.3)';
                    } else if (exportsRemaining <= 5) {
                        // Low exports left - orange styling
                        indicator.style.background = 'linear-gradient(135deg, #f39c12 0%, #e67e22 100%)';
                        indicator.style.boxShadow = '0 2px 8px rgba(243, 156, 18, 0.3)';
                    } else {
                        // Normal exports left - blue styling
                        indicator.style.background = 'linear-gradient(135deg, #3498db 0%, #2980b9 100%)';
                        indicator.style.boxShadow = '0 2px 8px rgba(52, 152, 219, 0.3)';
                    }
                }

                // Disable/enable export functionality based on remaining exports
                this.updateExportButtonState(exportsRemaining <= 0);

            } else {
                console.error('✗ Failed to get exports counter data:', data);
                // Show error in counter
                if (counterContent) {
                    counterContent.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
                }
            }
        } catch (error) {
            console.error('✗ Error updating exports counter:', error);
            console.error('Error details:', error.message, error.stack);
            // Show error in counter
            if (counterContent) {
                counterContent.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Error';
            }
        }
        
    }

    updateGenerateButtonState(disabled) {
        const generateBtn = document.getElementById('generate-report');
        
        if (generateBtn) {
            if (disabled) {
                generateBtn.disabled = true;
                generateBtn.classList.add('disabled');
                generateBtn.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Report Limit Reached';
                generateBtn.title = 'Report generation limit reached. Upgrade your plan for more reports.';
            } else {
                generateBtn.disabled = false;
                generateBtn.classList.remove('disabled');
                generateBtn.innerHTML = '<i class="fa fa-play"></i> Generate Report';
                generateBtn.title = 'Generate the configured report';
            }
        }
    }

    updateExportButtonState(disabled) {
        const exportBtn = document.getElementById('export-btn');
        const exportMenu = document.getElementById('export-menu');
        
        if (exportBtn) {
            if (disabled) {
                exportBtn.disabled = true;
                exportBtn.classList.add('disabled');
                exportBtn.innerHTML = '<i class="fa fa-download"></i> Export (Limit Reached) <i class="fa fa-chevron-down"></i>';
                exportBtn.title = 'Export limit reached. Upgrade your plan for more exports.';
            } else {
                exportBtn.disabled = false;
                exportBtn.classList.remove('disabled');
                exportBtn.innerHTML = '<i class="fa fa-download"></i> Export <i class="fa fa-chevron-down"></i>';
                exportBtn.title = 'Export report in various formats';
            }
        }
        
        // Also disable all export menu items
        if (exportMenu) {
            const exportLinks = exportMenu.querySelectorAll('a');
            exportLinks.forEach(link => {
                if (disabled) {
                    link.classList.add('disabled');
                    link.style.pointerEvents = 'none';
                    link.style.opacity = '0.5';
                } else {
                    link.classList.remove('disabled');
                    link.style.pointerEvents = 'auto';
                    link.style.opacity = '1';
                }
            });
        }
    }

    showUpgradePrompt(report) {
        const subscriptionUrl = `${this.wizardData.wwwroot}/report/adeptus_insights/subscription.php`;
        
        Swal.fire({
            title: 'Premium Report',
            html: `
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 48px; color: #f39c12; margin-bottom: 15px;">
                        <i class="fa fa-crown"></i>
                    </div>
                    <h3 style="color: #2c3e50; margin-bottom: 15px;">"${report.name}" is a Premium Report</h3>
                    <p style="color: #7f8c8d; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
                        This report is only available with a paid subscription plan. Upgrade now to access all premium reports and unlock the full potential of Adeptus Insights.
                    </p>
                    <div style="background: #ecf0f1; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <p style="margin: 0; font-size: 14px; color: #34495e;">
                            <strong>Free Plan:</strong> Limited reports per category<br>
                            <strong>Paid Plans:</strong> Access to all reports + advanced features
                        </p>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa fa-arrow-up"></i> Upgrade Now',
            cancelButtonText: '<i class="fa fa-times"></i> Cancel',
            confirmButtonColor: '#3498db',
            cancelButtonColor: '#95a5a6',
            width: 500,
            customClass: {
                popup: 'upgrade-popup'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.open(subscriptionUrl, '_blank');
            }
        });
    }

    showExportUpgradePrompt(format) {
        const subscriptionUrl = `${this.wizardData.wwwroot}/report/adeptus_insights/subscription.php`;
        
        const formatNames = {
            'csv': 'CSV',
            'excel': 'Excel',
            'json': 'JSON'
        };
        
        const formatName = formatNames[format] || format.toUpperCase();
        
        Swal.fire({
            title: 'Premium Export Format',
            html: `
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 48px; color: #f39c12; margin-bottom: 15px;">
                        <i class="fa fa-crown"></i>
                    </div>
                    <h3 style="color: #2c3e50; margin-bottom: 15px;">${formatName} Export is a Premium Feature</h3>
                    <p style="color: #7f8c8d; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
                        Export to ${formatName} format is only available with a paid subscription plan. Upgrade now to unlock all export formats and advanced features.
                    </p>
                    <div style="background: #ecf0f1; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <p style="margin: 0; font-size: 14px; color: #34495e;">
                            <strong>Free Plan:</strong> PDF exports only<br>
                            <strong>Paid Plans:</strong> CSV, Excel, JSON, and PDF exports
                        </p>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa fa-arrow-up"></i> Upgrade Now',
            cancelButtonText: '<i class="fa fa-times"></i> Cancel',
            confirmButtonColor: '#3498db',
            cancelButtonColor: '#95a5a6',
            width: 500,
            customClass: {
                popup: 'upgrade-popup'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.open(subscriptionUrl, '_blank');
            }
        });
    }

    async removeRecentReport(reportId) {
        this.showLoading('Removing recent report...');
        
        try {
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/manage_recent_reports.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_single&reportid=${reportId}&sesskey=${this.wizardData.sesskey}`
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Report removed successfully!');
                // Remove from data and re-render all sections
                if (this.wizardData.recent_reports) {
                    const index = this.wizardData.recent_reports.findIndex(r => r.reportid === reportId);
                    if (index > -1) {
                        this.wizardData.recent_reports.splice(index, 1);
                    }
                }
                
                // Re-render only recent reports section
                this.renderRecentReports();
                // Don't re-render generated reports - they should remain independent
            } else {
                this.showError(data.message || 'Failed to remove report');
            }
        } catch (error) {
            console.error('Error removing recent report:', error);
            this.showError('Error removing recent report');
        } finally {
            this.hideLoading();
        }
    }

    async clearAllRecentReports() {
        if (!confirm('Are you sure you want to clear all recent reports? This action cannot be undone.')) {
            return;
        }
        
        this.showLoading('Clearing all recent reports...');
        
        try {
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/manage_recent_reports.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=clear_all&sesskey=${this.wizardData.sesskey}`
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('All recent reports cleared successfully!');
                // Clear data and re-render only recent reports section
                this.wizardData.recent_reports = [];
                this.renderRecentReports();
                // Don't re-render generated reports - they should remain independent
            } else {
                this.showError(data.message || 'Failed to clear recent reports');
            }
        } catch (error) {
            console.error('Error clearing recent reports:', error);
            this.showError('Error clearing recent reports');
        } finally {
            this.hideLoading();
        }
    }

    async clearAllBookmarks() {
        if (!confirm('Are you sure you want to clear all bookmarks? This action cannot be undone.')) {
            return;
        }
        this.showLoading('Clearing all bookmarks...');
        try {
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/bookmark_report.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=clear_all&sesskey=${this.wizardData.sesskey}`
            });
            const data = await response.json();
            if (data.success) {
                this.showSuccess('All bookmarks cleared successfully!');
                // Clear data and re-render bookmarks section
                this.wizardData.bookmarks = [];
                this.wizardData.bookmarked_report_ids = [];
                this.renderBookmarks();
                // Update bookmark button state if needed
                this.updateBookmarkButton(false);
            } else {
                this.showError(data.message || 'Failed to clear bookmarks');
            }
        } catch (error) {
            console.error('Error clearing bookmarks:', error);
            this.showError('Error clearing bookmarks');
        } finally {
            this.hideLoading();
        }
    }

    addBookmarkCardLive(reportId) {
        // Find report info from categories
        let found = null;
        for (const cat of this.wizardData.categories) {
            for (const rep of cat.reports) {
                if (rep.name === reportId) {
                    found = { ...rep, category: cat.name, reportid: reportId };
                    break;
                }
            }
            if (found) break;
        }
        if (!found) return;
        
        // Add to bookmarks data
        if (!this.wizardData.bookmarks) {
            this.wizardData.bookmarks = [];
        }
        
        // Check if already exists
        const existingIndex = this.wizardData.bookmarks.findIndex(b => b.reportid === reportId);
        if (existingIndex === -1) {
            this.wizardData.bookmarks.unshift({
                ...found,
                formatted_date: 'Just now'
            });
        }
        
        // Re-render bookmarks section
        this.renderBookmarks();
    }

    removeBookmarkCardLive(reportId) {
        // Remove from bookmarks data
        if (this.wizardData.bookmarks) {
            const index = this.wizardData.bookmarks.findIndex(b => b.reportid === reportId);
            if (index > -1) {
                this.wizardData.bookmarks.splice(index, 1);
            }
        }
        
        // Re-render bookmarks section
        this.renderBookmarks();
    }

    async removeFromGeneratedView(reportId) {
        this.showLoading('Removing generated report...');
        
        try {
            // Create a new endpoint for removing generated reports
            const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/manage_generated_reports.php`, {
                        method: 'POST',
                        headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_single&reportid=${reportId}&sesskey=${this.wizardData.sesskey}`
            });

            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Generated report removed successfully!');
                // Remove from data and re-render only generated reports section
                if (this.wizardData.generated_reports) {
                    const index = this.wizardData.generated_reports.findIndex(r => r.reportid === reportId);
                    if (index > -1) {
                        this.wizardData.generated_reports.splice(index, 1);
                    }
                }
                
                // Re-render only generated reports section
                this.renderGeneratedReports();
                // Don't re-render other sections - they should remain independent
            } else {
                this.showError(data.message || 'Failed to remove generated report');
            }
        } catch (error) {
            console.error('Error removing generated report:', error);
            this.showError('Error removing generated report');
        } finally {
            this.hideLoading();
        }
    }

}


// Make the class globally available
window.AdeptusWizard = AdeptusWizard;

// Test function to verify the class is working
window.testAdeptusWizard = function() {
    if (typeof AdeptusWizard === 'function') {
        const wizard = new AdeptusWizard();
        return true;
    } else {
        console.error('✗ AdeptusWizard class not available');
        return false;
    }
};


// Fallback initialization removed - template handles initialization properly

// Close export menu when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.export-dropdown')) {
        document.getElementById('export-menu')?.classList.remove('show');
    }
}); 
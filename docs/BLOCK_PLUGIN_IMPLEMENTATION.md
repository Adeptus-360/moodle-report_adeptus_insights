# Adeptus Insights Block Plugin - Implementation Document

**Document Version:** 2.0.5
**Created:** 2025-12-29
**Last Updated:** 2025-12-31
**Status:** Phase 1, 2 & 3 Complete, Phase 4 Complete (Core Features)
**Plugin Name:** `block_adeptus_insights`
**Repository:** https://gitlab.com/adeptus360/insights-block

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Problem Statement & Value Proposition](#2-problem-statement--value-proposition)
3. [Technical Architecture](#3-technical-architecture)
4. [Feature Specifications](#4-feature-specifications)
5. [Display Modes & Layouts](#5-display-modes--layouts)
6. [Configuration Settings](#6-configuration-settings)
7. [Integration Requirements](#7-integration-requirements)
8. [Implementation Phases](#8-implementation-phases)
9. [Progress Tracker](#9-progress-tracker)
10. [Future Considerations](#10-future-considerations)

---

## 1. Executive Summary

### Vision

The Adeptus Insights Block Plugin extends the core reporting functionality by bringing actionable data directly to where users work - dashboards, course pages, and category views. Rather than requiring users to navigate to a separate reporting interface, key metrics and reports are surfaced contextually, enabling faster decision-making and proactive intervention.

### Key Differentiators

1. **Context-Aware Intelligence** - Automatically filters data based on where the block is placed (site, course, category)
2. **Multiple Display Modes** - From simple link lists to fully embedded interactive reports
3. **Real-Time Alerts (Insurance Policy)** - Proactive notifications when metrics exceed thresholds
4. **Seamless Integration** - Leverages existing Adeptus Insights infrastructure and categories
5. **Role-Based Visibility** - Different users see different data based on their permissions

### Target Users

| User Type | Primary Use Case |
|-----------|------------------|
| **Site Administrators** | Site-wide KPIs on dashboard, system health monitoring |
| **Academic Managers** | Category/department performance overview |
| **Teachers** | Course-specific metrics on course pages |
| **Students** | Personal progress and engagement metrics |

---

## 2. Problem Statement & Value Proposition

### Problems We're Solving

| Problem | Impact | Our Solution |
|---------|--------|--------------|
| **Scattered Insights** | Users must navigate away from their work to find reports | Embed reports directly where users work |
| **Information Overload** | Full reports are too detailed for quick decisions | KPI cards with summarized metrics + drill-down |
| **Delayed Discovery** | Issues found too late in formal reporting | Real-time alerts when thresholds breached |
| **Role Fragmentation** | Different users need different data views | Context-aware filtering + role visibility |
| **Repetitive Navigation** | Frequently-used reports require multiple clicks | Quick-access links and embedded views |
| **Course Blindness** | Teachers lack visibility into course performance | Auto-filtered course metrics on course pages |
| **Trend Invisibility** | Hard to see changes over time | Comparison mode with trend indicators |
| **Reactive Management** | Problems addressed after damage done | **Insurance Policy Alerts** - proactive warnings |

### The "Insurance Policy" Concept

Real-time alerts function as an **insurance policy** for educational institutions:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        INSURANCE POLICY ALERTS                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  RISK: Student drops out                                                │
│  PREMIUM: Configure "Inactive Users > 7 days" alert                     │
│  PAYOUT: Early intervention prevents dropout                            │
│                                                                         │
│  RISK: Assignment deadline disaster                                     │
│  PREMIUM: Configure "Submissions < 50% at 24hr before due" alert        │
│  PAYOUT: Teacher sends reminder, improves completion rate               │
│                                                                         │
│  RISK: Course quality issues                                            │
│  PREMIUM: Configure "Avg Grade < 60%" alert                             │
│  PAYOUT: Early review of course content/assessments                     │
│                                                                         │
│  RISK: Engagement collapse                                              │
│  PREMIUM: Configure "Daily Active Users drop > 20%" alert               │
│  PAYOUT: Investigate and address engagement issues                      │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

**Alert Types:**
- **Warning (Amber)** - Approaching threshold, attention needed
- **Critical (Red)** - Threshold exceeded, immediate action required
- **Recovery (Green)** - Previously breached threshold now healthy

**Notification Channels:**

| Channel | Recipients | Use Case |
|---------|------------|----------|
| **Moodle Messages** | Users with selected roles | Internal notifications to Moodle users |
| **Email Notifications** | Specific email addresses | External stakeholders, specific individuals |
| **Visual Indicators** | Block display | Status badges and color coding |

**Email Notification Benefits:**
- Add external stakeholders without Moodle accounts
- Target specific individuals by email address
- Supports multiple addresses (one per line)
- HTML formatted emails with color-coded status

---

## 3. Technical Architecture

### File Structure

```
blocks/adeptus_insights/
├── block_adeptus_insights.php        # Main block class
├── version.php                        # Plugin metadata & dependencies
├── settings.php                       # Global admin settings
├── edit_form.php                      # Instance configuration form
├── styles.css                         # Block-specific styles
│
├── db/
│   ├── access.php                     # Capabilities definition
│   ├── install.xml                    # Database schema (alerts + kpi_history tables)
│   ├── install.php                    # Post-install tasks
│   ├── upgrade.php                    # Version upgrades
│   ├── services.php                   # External services (KPI history, report search)
│   ├── tasks.php                      # Scheduled tasks (KPI cleanup, alert checking)
│   └── messages.php                   # Message providers (alert notifications)
│
├── classes/
│   ├── output/
│   │   ├── renderer.php               # Custom renderer
│   │   ├── block_content.php          # Content renderable
│   │   ├── report_card.php            # KPI card renderable
│   │   └── report_modal.php           # Modal renderable
│   ├── external/
│   │   ├── save_kpi_history.php       # External service: save KPI value
│   │   ├── get_kpi_history.php        # External service: get KPI history
│   │   └── search_reports.php         # External service: search reports for alerts
│   ├── task/
│   │   ├── cleanup_kpi_history.php    # Scheduled task: cleanup old records
│   │   └── check_alerts.php           # Scheduled task: check alert thresholds
│   ├── alert_manager.php              # Alert threshold evaluation & notifications
│   ├── report_fetcher.php             # API integration
│   ├── context_filter.php             # Context-aware filtering
│   ├── kpi_history_manager.php        # KPI history save/get/cleanup logic
│   └── privacy/
│       └── provider.php               # GDPR compliance
│
├── templates/
│   ├── block_content.mustache         # Main block template
│   ├── embedded_report.mustache       # Full embedded report
│   ├── kpi_card.mustache              # Single KPI card
│   ├── kpi_grid.mustache              # Multi-KPI layout
│   ├── report_list.mustache           # Link list view
│   ├── report_tabs.mustache           # Tabbed interface
│   ├── report_modal.mustache          # Modal popup
│   ├── alert_badge.mustache           # Alert indicator
│   └── loading_skeleton.mustache      # Loading state
│
├── amd/
│   └── src/
│       ├── block.js                   # Main block controller
│       ├── edit_form.js               # Edit form enhancements (searchable dropdowns, alerts manager)
│       ├── report_loader.js           # AJAX report loading
│       ├── modal_handler.js           # Modal interactions
│       ├── chart_renderer.js          # Chart.js integration
│       ├── refresh_manager.js         # Auto-refresh logic
│       ├── alert_checker.js           # Client-side alert polling
│       ├── alert_report_search.js     # Ajax autocomplete for alert report selection
│       └── export_handler.js          # Quick export functionality
│
├── lang/
│   └── en/
│       └── block_adeptus_insights.php # Language strings
│
├── pix/
│   ├── icon.png                       # Plugin icon (recommended)
│   └── icon.svg                       # Plugin icon (vector)
│
└── tests/
    ├── block_test.php                 # PHPUnit tests
    └── behat/
        └── block_display.feature      # Behat tests
```

### Class Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        block_adeptus_insights                           │
│                        (extends block_base)                             │
├─────────────────────────────────────────────────────────────────────────┤
│ + init()                                                                │
│ + get_content() : stdClass                                              │
│ + specialization()                                                      │
│ + applicable_formats() : array                                          │
│ + instance_allow_multiple() : bool                                      │
│ + has_config() : bool                                                   │
│ + instance_config_save($data)                                           │
│ + get_required_javascript()                                             │
├─────────────────────────────────────────────────────────────────────────┤
│ - render_embedded_report() : string                                     │
│ - render_kpi_cards() : string                                           │
│ - render_report_list() : string                                         │
│ - render_tabbed_reports() : string                                      │
│ - get_context_filters() : array                                         │
│ - check_alerts() : array                                                │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ uses
                                    ▼
┌─────────────────────────┐  ┌─────────────────────────┐
│    report_fetcher       │  │    context_filter       │
├─────────────────────────┤  ├─────────────────────────┤
│ + fetch_report($slug)   │  │ + get_course_id()       │
│ + fetch_by_category()   │  │ + get_category_id()     │
│ + fetch_wizard_reports()│  │ + get_user_id()         │
│ + fetch_ai_reports()    │  │ + build_params()        │
└─────────────────────────┘  └─────────────────────────┘
                                    │
                                    │ uses
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                 report_adeptus_insights (parent plugin)                 │
├─────────────────────────────────────────────────────────────────────────┤
│ installation_manager | api_config | report_validator                    │
└─────────────────────────────────────────────────────────────────────────┘
```

### Database Schema

```sql
-- Block alert configurations (supports multiple alerts per block)
CREATE TABLE {block_adeptus_alerts} (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    blockinstanceid BIGINT NOT NULL,           -- Links to block_instances.id
    report_slug     VARCHAR(255) NOT NULL,     -- Which report to monitor
    metric_field    VARCHAR(100) NOT NULL DEFAULT 'value',  -- Which field to check
    alert_name      VARCHAR(255),              -- Custom alert name
    alert_description TEXT,                    -- Custom alert description
    operator        VARCHAR(20) NOT NULL,      -- gt, lt, eq, gte, lte, change_pct
    warning_value   DECIMAL(15,4),             -- Warning threshold
    critical_value  DECIMAL(15,4),             -- Critical threshold
    baseline_value  DECIMAL(15,4),             -- Baseline for percentage change
    baseline_period VARCHAR(20) DEFAULT 'previous',  -- previous, 7days, 30days
    check_interval  INT DEFAULT 3600,          -- Seconds between checks
    cooldown_seconds INT DEFAULT 3600,         -- Cooldown between notifications
    current_status  VARCHAR(20) DEFAULT 'ok',  -- ok, warning, critical
    last_checked    BIGINT,                    -- Unix timestamp
    last_value      DECIMAL(15,4),             -- Last recorded value
    last_alert_time BIGINT,                    -- Last notification sent timestamp
    notify_roles    TEXT,                      -- JSON array of role IDs for Moodle messages
    notify_emails   TEXT,                      -- Email addresses for direct notifications (one per line)
    notify_email    TINYINT DEFAULT 0,         -- Send email notifications
    notify_message  TINYINT DEFAULT 1,         -- Send Moodle message notifications
    notify_on_warning  TINYINT DEFAULT 1,      -- Notify at warning level
    notify_on_critical TINYINT DEFAULT 1,      -- Notify at critical level
    notify_on_recovery TINYINT DEFAULT 1,      -- Notify on recovery to OK
    enabled         TINYINT DEFAULT 1,
    timecreated     BIGINT NOT NULL,
    timemodified    BIGINT NOT NULL,
    createdby       BIGINT,                    -- User who created
    modifiedby      BIGINT,                    -- User who last modified
    INDEX idx_block_report (blockinstanceid, report_slug),
    INDEX idx_status (current_status),
    INDEX idx_enabled_check (enabled, last_checked)
);

-- Alert history for tracking status changes
CREATE TABLE {block_adeptus_alert_history} (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    alertid         BIGINT NOT NULL,           -- Links to block_adeptus_alerts.id
    blockinstanceid BIGINT NOT NULL,           -- Denormalized for query efficiency
    report_slug     VARCHAR(255) NOT NULL,     -- Denormalized for query efficiency
    previous_status VARCHAR(20),               -- Previous status (ok, warning, critical)
    new_status      VARCHAR(20) NOT NULL,      -- New status
    metric_value    DECIMAL(15,4) NOT NULL,    -- Value at time of check
    threshold_value DECIMAL(15,4),             -- Threshold that was breached
    threshold_type  VARCHAR(20),               -- warning or critical
    evaluation_details TEXT,                   -- JSON with evaluation context
    notified        TINYINT DEFAULT 0,         -- Whether notification was sent
    timecreated     BIGINT NOT NULL,
    INDEX idx_alert_time (alertid, timecreated),
    INDEX idx_block_time (blockinstanceid, timecreated),
    INDEX idx_status_time (new_status, timecreated),
    INDEX idx_timecreated (timecreated)
);

-- User preferences for block
CREATE TABLE {block_adeptus_user_prefs} (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    userid          BIGINT NOT NULL,
    blockinstanceid BIGINT NOT NULL,
    preferences     TEXT,                      -- JSON: favorites, collapsed state, etc.
    timecreated     BIGINT NOT NULL,
    timemodified    BIGINT NOT NULL,
    UNIQUE KEY user_block (userid, blockinstanceid)
);

-- KPI history for trend tracking (implemented v1.8.0)
CREATE TABLE {block_adeptus_kpi_history} (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    blockinstanceid BIGINT NOT NULL,           -- Links to block_instances.id
    report_slug     VARCHAR(255) NOT NULL,     -- Report identifier
    metric_value    DECIMAL(15,4) NOT NULL,    -- The KPI value at this point in time
    metric_label    VARCHAR(255),              -- Optional label for the metric
    row_count       INT DEFAULT 0,             -- Number of data rows
    source          VARCHAR(50) DEFAULT 'wizard', -- wizard or ai
    context_type    VARCHAR(50) DEFAULT 'site',   -- site, course, category
    context_id      BIGINT DEFAULT 0,          -- Related context ID
    timecreated     BIGINT NOT NULL,           -- Timestamp of recording
    INDEX idx_block_report (blockinstanceid, report_slug),
    INDEX idx_timecreated (timecreated)
);
```

### API Integration Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Browser   │────▶│    Block    │────▶│   Report    │────▶│  Backend    │
│             │     │  JavaScript │     │   Plugin    │     │    API      │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
      │                    │                   │                   │
      │  1. Page Load      │                   │                   │
      │───────────────────▶│                   │                   │
      │                    │                   │                   │
      │  2. Request Report │                   │                   │
      │                    │──────────────────▶│                   │
      │                    │                   │                   │
      │                    │  3. API Call      │                   │
      │                    │                   │──────────────────▶│
      │                    │                   │                   │
      │                    │                   │  4. Report Data   │
      │                    │                   │◀──────────────────│
      │                    │                   │                   │
      │                    │  5. Formatted     │                   │
      │                    │◀──────────────────│                   │
      │                    │                   │                   │
      │  6. Render Chart   │                   │                   │
      │◀───────────────────│                   │                   │
      │                    │                   │                   │
```

---

## 4. Feature Specifications

### 4.1 Core Display Features

#### F001: Embedded Report Display
**Priority:** P0 (MVP)
**Description:** Display a complete report (chart + table) directly within the block area.

**Acceptance Criteria:**
- [ ] Report chart renders using Chart.js (consistent with main plugin)
- [ ] Data table displays with pagination controls
- [ ] Responsive layout adapts to block width
- [ ] Loading skeleton shown during data fetch
- [ ] Error state displayed gracefully on failure
- [ ] "View Full Report" link opens main plugin

**Configuration:**
- Report selection (dropdown of available reports)
- Show chart (yes/no)
- Show table (yes/no)
- Chart height (150-400px slider)
- Max table rows (5/10/25/50)

---

#### F002: Report Link List
**Priority:** P0 (MVP)
**Description:** Display a list of report names as clickable links.

**Acceptance Criteria:**
- [ ] Reports displayed as list items with icons
- [ ] Category badge shown next to each report
- [ ] Click opens report in modal (default) or new tab
- [ ] Supports both Wizard and AI-generated reports
- [ ] Empty state when no reports available
- [ ] Configurable max items to display

**Configuration:**
- Report source (All / Wizard / AI / Category / Manual selection)
- Selected reports (multi-select when manual)
- Click action (Modal / New Tab / Inline Expand)
- Show category badges (yes/no)
- Max items to display

---

#### F003: Modal Report Viewer
**Priority:** P0 (MVP)
**Description:** Full-featured modal popup for viewing complete reports.

**Acceptance Criteria:**
- [ ] Modal opens with smooth animation
- [ ] Full report displayed (chart + table + export)
- [ ] Close via X button, Escape key, or backdrop click
- [ ] Maintains scroll position on close
- [ ] Mobile-responsive (full-screen on small devices)
- [ ] Export buttons functional within modal

---

#### F004: KPI Card Display ✅ COMPLETE
**Priority:** P1
**Description:** Compact cards showing single metrics with trend indicators.

**Acceptance Criteria:**
- [x] Large number display with label
- [x] Trend indicator (↑ ↓ →) with percentage
- [x] Sparkline mini-chart (database-backed history)
- [x] Color coding based on trend (green/red/grey)
- [x] Click to expand to full report
- [x] Configurable comparison period
- [x] **Custom icon selection** - Choose from 35 FontAwesome icons per KPI

**Custom Icon Categories:**
| Category | Icons |
|----------|-------|
| People | Users, User, New User |
| Education | Graduation Cap, Book, Certificate |
| Time | Clock, Calendar, Hourglass |
| Status | Complete, Check, Trophy, Star |
| Analytics | Bar Chart, Line Chart, Pie Chart, Area Chart |
| Numbers | Percent, Number, Ranking |
| Financial | Dollar, Pound, Money |
| Progress | Tasks, Progress, Target, Flag |
| Communication | Email, Comments, Notifications |
| Alerts | Warning, Info |
| Feedback | Thumbs Up, Thumbs Down, Satisfaction |

**Visual Design:**
```
┌─────────────────────┐
│  [icon]  👥 247     │  ← Custom icon per KPI
│   Active Users      │
│     ↑ 12%          │
│   vs last week      │
└─────────────────────┘
```

---

#### F005: Multi-Report Grid ✅ COMPLETE
**Priority:** P1
**Description:** Display multiple KPI cards in a grid layout.

**Acceptance Criteria:**
- [x] 2x2 or 1x4 grid layout options (1-4 columns configurable)
- [x] Each card independently configurable with custom icons
- [x] Consistent card sizing
- [x] Drag-and-drop reordering in settings
- [x] Responsive: stack on mobile

---

#### F006: Tabbed Reports
**Priority:** P1
**Description:** Multiple reports organized in tabs within a single block.

**Acceptance Criteria:**
- [ ] Tab headers with report names
- [ ] Lazy loading (only load active tab)
- [ ] Remember last selected tab per user
- [ ] Maximum 5 tabs per block
- [ ] Tab overflow handling (scroll or dropdown)

---

#### F007: Category Browser
**Priority:** P2
**Description:** Browse and access all reports within a category.

**Acceptance Criteria:**
- [ ] Category selection dropdown
- [ ] Grid/list toggle view
- [ ] Report count per category
- [ ] Category color coding
- [ ] Search/filter within category

---

### 4.2 Context Awareness Features

#### F010: Auto Context Detection
**Priority:** P0 (MVP)
**Description:** Automatically detect and apply context filters.

**Acceptance Criteria:**
- [ ] Detect page type (site, course, category, user)
- [ ] Extract course ID when on course page
- [ ] Extract category ID when on category page
- [ ] Pass context to report queries
- [ ] Show context indicator in block header

**Context Mapping:**
| Page Type | Detected Context | Filter Applied |
|-----------|------------------|----------------|
| Site index / Dashboard | Site-level | None (all data) |
| Course page | Course ID | `courseid = X` |
| Course category | Category ID | `categoryid = X` |
| User profile | User ID | `userid = X` |
| Activity page | Course + Activity | `courseid = X, cmid = Y` |

---

#### F011: Manual Context Override
**Priority:** P1
**Description:** Allow manual override of auto-detected context.

**Acceptance Criteria:**
- [ ] Override dropdown in block config
- [ ] Options: Auto / Specific Course / Specific Category / Site-wide
- [ ] Course/category picker when specific selected
- [ ] Clear indication when override active

---

### 4.3 Real-Time & Refresh Features

#### F020: Manual Refresh
**Priority:** P0 (MVP)
**Description:** Button to manually refresh report data.

**Acceptance Criteria:**
- [ ] Refresh icon in block header
- [ ] Spinner animation during refresh
- [ ] AJAX update without page reload
- [ ] Success/error feedback
- [ ] Rate limiting (max 1 refresh per 30 seconds)

---

#### F021: Auto-Refresh
**Priority:** P1
**Description:** Automatic periodic refresh of report data.

**Acceptance Criteria:**
- [ ] Configurable interval (5m / 15m / 30m / 1hr / Never)
- [ ] Visual countdown to next refresh (optional)
- [ ] Pause when tab/page not visible
- [ ] Resume when tab becomes visible
- [ ] "Last updated: X minutes ago" timestamp

---

#### F022: Alert Thresholds ✅ COMPLETE
**Priority:** P2
**Description:** Define thresholds that trigger visual alerts.

**Acceptance Criteria:**
- [x] Configure warning and critical thresholds
- [x] Operators: greater than, less than, equals, greater than or equal, less than or equal, percentage change
- [x] Visual indicators (status badges with color coding)
- [x] Alert persists until condition clears or recovery
- [x] Full history of alert triggers in `block_adeptus_alert_history` table
- [x] **Multi-alert support**: Configure multiple alerts per block instance
- [x] **Searchable report dropdown**: Ajax autocomplete for selecting monitored report
- [x] **Custom alert naming**: Name and description for each alert

**Implementation Details:**
- `alert_manager.php`: Core alert evaluation logic with threshold checking
- `check_alerts.php`: Scheduled task runs every minute to check due alerts
- Multi-alert UI with card-based list, inline edit panel, enable/disable toggles
- JSON-based alert configuration storage in form hidden field

**Configuration UI:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│ 📊 Alert Configuration                                     [+ Add Alert] │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │ 🔔 Inactive Users Alert                              [✓] [✎] [🗑] │ │
│  │     Report: user-activity-report                                   │ │
│  │     Condition: Value > 30 (warning) / > 50 (critical)             │ │
│  │     Status: ● OK  |  Check: Every 1 hour                          │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │ 🔔 Course Completion Drop                            [✓] [✎] [🗑] │ │
│  │     Report: course-completion-rates                                │ │
│  │     Condition: Change % > 10% (warning) / > 25% (critical)        │ │
│  │     Status: ⚠ WARNING  |  Check: Every 6 hours                    │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

#### F023: Alert Notifications ✅ COMPLETE
**Priority:** P3
**Description:** Notify users when thresholds are breached.

**Acceptance Criteria:**
- [x] Moodle notification system integration via message providers
- [x] Email notification to specific addresses (supports external stakeholders)
- [x] Role-based Moodle message recipients (multi-select)
- [x] Notification includes: metric, value, threshold, status, link to report
- [x] Cooldown period to prevent notification flooding
- [x] Notify on warning, critical, and recovery (configurable per alert)

**Notification Architecture:**

| Channel | Recipients | Configuration |
|---------|------------|---------------|
| **Moodle Messages** | Users with selected roles | Role multi-select dropdown |
| **Email** | Specific email addresses | Text area (one per line) |

**Implementation Details:**
- `db/messages.php`: Defines `alertnotification` message provider
- `alert_manager.php::send_alert_notifications()`: Dispatches both channels
- `alert_manager.php::send_direct_email()`: Sends to specific email addresses
- `alert_manager.php::parse_email_addresses()`: Parses newline/comma-separated emails
- Color-coded HTML emails: Red (critical), Orange (warning), Green (recovery)
- Respects `cooldown_seconds` to prevent repeated notifications

**Email Benefits:**
- External stakeholders without Moodle accounts can receive alerts
- Target specific individuals rather than entire roles
- Supports comma, semicolon, or newline-separated addresses
- Validates email format before sending

**Email Notification Format:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│                    🚨 CRITICAL ALERT                                    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Alert: Inactive Users Alert                                            │
│  Report: User Activity Monitoring                                       │
│  Current Value: 52                                                      │
│  Threshold: 50 (critical)                                               │
│  Status: OK → CRITICAL                                                  │
│                                                                         │
│  [View Report]                                                          │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 4.4 Export & Actions

#### F030: Quick Export
**Priority:** P1
**Description:** One-click export directly from block.

**Acceptance Criteria:**
- [ ] CSV export button
- [ ] PDF export button (chart + table)
- [ ] Excel export button
- [ ] Filename includes report name and date
- [ ] Respects current filters

---

#### F031: Copy to Clipboard
**Priority:** P2
**Description:** Copy table data to clipboard.

**Acceptance Criteria:**
- [ ] Copy button on table view
- [ ] Tab-separated format (paste into Excel)
- [ ] Include headers
- [ ] Success toast notification
- [ ] Copy current page or all data option

---

### 4.5 Personalization

#### F040: Favorite Reports
**Priority:** P2
**Description:** Users can mark reports as favorites.

**Acceptance Criteria:**
- [ ] Star/heart icon to toggle favorite
- [ ] Favorites appear first in lists
- [ ] "Show favorites only" filter
- [ ] Stored per-user in database
- [ ] Sync across block instances

---

#### F041: Collapsed State Memory
**Priority:** P2
**Description:** Remember expanded/collapsed state per user.

**Acceptance Criteria:**
- [ ] Block can be collapsed to header only
- [ ] State persisted per user per block instance
- [ ] Expand/collapse animation
- [ ] Collapsed state shows key metric summary

---

---

## 5. Display Modes & Layouts

### 5.1 Display Mode Reference

| Mode | Best For | Data Shown | Interaction |
|------|----------|------------|-------------|
| **Embedded Full** | Primary dashboard widget | Chart + Table | View full report link |
| **Embedded Compact** | Secondary metrics | Chart OR Table | Click for modal |
| **KPI Card** | Key metrics at glance | Single number + trend | Click for details |
| **KPI Grid** | Dashboard overview | 2-4 metrics | Each card clickable |
| **Link List** | Report directory | Report names | Click opens modal/tab |
| **Tabbed** | Multiple related reports | Multiple full reports | Tab switching |
| **Category Browser** | Discovery | Reports by category | Browse and select |

### 5.2 Layout Wireframes

#### Embedded Full Layout
```
┌─────────────────────────────────────────────────────────────────────────┐
│ 📊 Course Activity Report                        [⟳] [⬇ Export] [⋮]   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │                                                                   │ │
│  │                         [BAR CHART]                               │ │
│  │                                                                   │ │
│  │     ▓▓▓▓                                                         │ │
│  │     ▓▓▓▓  ▓▓▓▓                                                   │ │
│  │     ▓▓▓▓  ▓▓▓▓  ▓▓▓▓                                             │ │
│  │     ▓▓▓▓  ▓▓▓▓  ▓▓▓▓  ▓▓▓▓  ▓▓▓▓                                 │ │
│  │     Mon   Tue   Wed   Thu   Fri                                   │ │
│  │                                                                   │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│  ┌───────────────┬────────────┬────────────┬────────────┐             │
│  │ Day           │ Users      │ Sessions   │ Avg Time   │             │
│  ├───────────────┼────────────┼────────────┼────────────┤             │
│  │ Monday        │ 142        │ 287        │ 34m        │             │
│  │ Tuesday       │ 156        │ 312        │ 41m        │             │
│  │ Wednesday     │ 189        │ 398        │ 38m        │             │
│  │ Thursday      │ 134        │ 256        │ 29m        │             │
│  │ Friday        │ 98         │ 187        │ 22m        │             │
│  └───────────────┴────────────┴────────────┴────────────┘             │
│                                                                         │
│  Showing 5 of 7 results                      View Full Report →        │
└─────────────────────────────────────────────────────────────────────────┘
```

#### KPI Grid Layout
```
┌─────────────────────────────────────────────────────────────────────────┐
│ 📈 Quick Stats                                              [⟳] [⋮]   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────────┐    ┌─────────────────────────┐           │
│  │         👥              │    │         📚              │           │
│  │        247              │    │        89%              │           │
│  │    Active Users         │    │   Course Completion     │           │
│  │       ↑ 12%            │    │       ↑ 3%             │           │
│  │    ▁▂▃▄▅▆▇ (sparkline) │    │    ▇▆▅▆▇▇▇ (sparkline) │           │
│  └─────────────────────────┘    └─────────────────────────┘           │
│                                                                         │
│  ┌─────────────────────────┐    ┌─────────────────────────┐           │
│  │         ⏱              │    │         📝              │           │
│  │        4.2h             │    │         34              │           │
│  │   Avg Session Time      │    │   Pending Submissions   │           │
│  │       → 0%             │    │       ↓ 8%             │           │
│  │    ▅▅▅▅▅▅▅ (sparkline) │    │    ▇▆▅▄▃▂▁ (sparkline) │           │
│  └─────────────────────────┘    └─────────────────────────┘           │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

#### Link List Layout
```
┌─────────────────────────────────────────────────────────────────────────┐
│ 📊 Available Reports                              [+ Add] [Manage]     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ ○ User Login Activity                                           │   │
│  │   └─ [Performance]  Last run: Today 14:32                    → │   │
│  ├─────────────────────────────────────────────────────────────────┤   │
│  │ ○ Course Completion Rates                                       │   │
│  │   └─ [Analytics]    Last run: Yesterday                      → │   │
│  ├─────────────────────────────────────────────────────────────────┤   │
│  │ ○ Assignment Submission Status                                  │   │
│  │   └─ [Assessments]  Last run: 3 days ago                     → │   │
│  ├─────────────────────────────────────────────────────────────────┤   │
│  │ ○ Student Engagement Metrics                                    │   │
│  │   └─ [Engagement]   Last run: Never                          → │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  Showing 4 of 12 reports                              View All →       │
└─────────────────────────────────────────────────────────────────────────┘
```

#### Tabbed Layout
```
┌─────────────────────────────────────────────────────────────────────────┐
│ 📊 Course Analytics                                         [⟳] [⋮]   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────┬──────────┬──────────┬──────────┐                         │
│  │  Users   │  Grades  │ Activity │ Submiss. │                         │
│  └──────────┴──────────┴──────────┴──────────┘                         │
│  ════════════                                                           │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐ │
│  │                     [USERS TAB CONTENT]                           │ │
│  │                                                                   │ │
│  │                        ┌─────────┐                                │ │
│  │                       /           \                               │ │
│  │                      │   Active   │                               │ │
│  │                      │    78%     │                               │ │
│  │                       \           /                               │ │
│  │                        └─────────┘                                │ │
│  │                                                                   │ │
│  │          Active: 247  │  Inactive: 53  │  Never logged: 12       │ │
│  │                                                                   │ │
│  └───────────────────────────────────────────────────────────────────┘ │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

#### Alert State Variants
```
Normal State:
┌─────────────────────────────────────────┐
│ 📊 Inactive Users                 [⟳]  │
│ ─────────────────────────────────────── │
│           23 users                      │
│         within normal range             │
└─────────────────────────────────────────┘

Warning State:
┌─────────────────────────────────────────┐
│ ⚠️ Inactive Users              [⟳] 🔔  │
│ ─────────────────────────────────────── │
│           38 users                      │
│    ⚠️ Approaching threshold (30)       │
└─────────────────────────────────────────┘

Critical State:
┌─────────────────────────────────────────┐
│ 🚨 Inactive Users              [⟳] 🔔  │
│ ═══════════════════════════════════════ │
│           52 users                      │
│    🚨 CRITICAL: Exceeds threshold (50) │
│         [View Details] [Dismiss]        │
└─────────────────────────────────────────┘
```

---

## 6. Configuration Settings

### 6.1 Global Settings (Admin)

Located in: Site Administration → Plugins → Blocks → Adeptus Insights

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enable_alerts` | checkbox | Yes | Enable alert threshold feature |
| `default_refresh_interval` | select | 15 minutes | Default auto-refresh interval |
| `max_reports_per_block` | number | 10 | Maximum reports in link list |
| `enable_email_notifications` | checkbox | No | Allow email alert notifications |
| `cache_duration` | number | 300 | Report data cache in seconds |
| `allowed_export_formats` | multiselect | CSV, PDF, Excel | Available export formats |
| `enable_sparklines` | checkbox | Yes | Show sparklines in KPI cards |
| `default_chart_height` | number | 250 | Default chart height in pixels |

### 6.2 Instance Settings (Per Block)

#### General Tab
| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `title` | text | (report name) | Custom block title |
| `display_mode` | select | embedded | embedded / kpi / links / tabs |
| `report_source` | select | manual | all / wizard / ai / category / manual |
| `selected_reports` | multiselect | - | Reports to display |
| `selected_category` | select | - | Category filter (when source=category) |

#### Display Tab
| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `show_chart` | checkbox | Yes | Display chart visualization |
| `chart_type_override` | select | default | Override chart type |
| `chart_height` | range | 250 | Chart height (150-400px) |
| `show_table` | checkbox | Yes | Display data table |
| `table_max_rows` | select | 10 | Rows before pagination |
| `compact_mode` | checkbox | No | Reduced padding/margins |
| `show_header` | checkbox | Yes | Show block header |
| `show_footer` | checkbox | Yes | Show "View Full Report" link |

#### Behavior Tab
| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `click_action` | select | modal | modal / newtab / expand |
| `auto_refresh` | select | never | never / 5m / 15m / 30m / 1hr |
| `show_refresh_button` | checkbox | Yes | Manual refresh control |
| `show_export` | checkbox | Yes | Quick export buttons |
| `show_timestamp` | checkbox | Yes | "Last updated" display |
| `context_filter` | select | auto | auto / manual / none |
| `context_course` | course picker | - | Manual course filter |
| `context_category` | category picker | - | Manual category filter |
| `kpi_history_interval` | select | 1hr | KPI history save frequency (1hr/6hr/12hr/1d/3d/1w/1m) |

#### Appearance Tab
| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `color_scheme` | select | default | default / dark / custom |
| `custom_primary_color` | color | #3498db | Primary accent color |
| `border_style` | select | default | default / none / shadow |
| `show_category_badges` | checkbox | Yes | Category color tags |
| `kpi_columns` | select | 2 | KPI grid columns (1-4) |

#### KPI Mode Settings
| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `kpi_selected_reports` | report picker | - | Reports to display as KPI cards (max 4) |
| `kpi_report_icon` | icon picker | auto | Custom icon per report (35 FA4 icons in 11 categories) |
| `kpi_history_interval` | select | 1hr | KPI history save frequency |

**Icon Picker UI:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│ KPI Reports                                                             │
├─────────────────────────────────────────────────────────────────────────┤
│  [👥] [≡] 1. User Activity Report        [Wizard] [×]                  │
│  [📚] [≡] 2. Course Completion Rates     [Wizard] [×]                  │
│  [⏱] [≡] 3. Session Duration Metrics     [AI]     [×]                  │
│  [✓] [≡] 4. Assignment Submissions       [Wizard] [×]                  │
│        ↑                                                                │
│     Click icon to open picker with 35 icons in 11 categories           │
└─────────────────────────────────────────────────────────────────────────┘
```

#### Alerts Tab (when alerts enabled)
| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enable_alerts` | checkbox | No | Enable alerts for this block |
| `alert_name` | text | - | Custom name for the alert |
| `alert_report` | autocomplete | - | Report to monitor (searchable) |
| `alert_operator` | select | gt | gt / lt / eq / gte / lte / change_pct |
| `warning_threshold` | number | - | Warning level |
| `critical_threshold` | number | - | Critical level |
| `check_interval` | select | 1hr | Alert check frequency |
| `cooldown` | select | 1hr | Minimum time between notifications |
| `notify_on_warning` | checkbox | Yes | Notify at warning level |
| `notify_on_critical` | checkbox | Yes | Notify at critical level |
| `notify_on_recovery` | checkbox | Yes | Notify when alert recovers |
| `notify_email` | checkbox | No | Enable email notifications |
| `notify_emails` | textarea | - | Email addresses (one per line) |
| `notify_roles` | multiselect | - | Roles for Moodle messages |

---

## 7. Integration Requirements

### 7.1 Parent Plugin Dependency

```php
// version.php
$plugin->dependencies = [
    'report_adeptus_insights' => 2025111930  // Minimum version required
];
```

### 7.2 Shared Components

| Component | Location | Usage |
|-----------|----------|-------|
| `installation_manager` | `report_adeptus_insights\classes\` | API authentication |
| `api_config` | `report_adeptus_insights\classes\` | Backend URL |
| `report_validator` | `report_adeptus_insights\classes\` | Report compatibility |
| Chart.js config | `report_adeptus_insights\amd\` | Consistent chart styling |
| CSS variables | `report_adeptus_insights\styles\` | Consistent theming |

### 7.3 Capability Requirements

```php
// db/access.php
$capabilities = [
    'block/adeptus_insights:addinstance' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'block/adeptus_insights:myaddinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],
    'block/adeptus_insights:viewalerts' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'block/adeptus_insights:configurealerts' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
```

### 7.4 API Endpoints Used

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/wizard-reports` | GET | Fetch wizard reports list |
| `/api/v1/wizard-reports/{slug}` | GET | Fetch single wizard report |
| `/api/v1/ai-reports` | GET | Fetch AI reports list |
| `/api/v1/ai-reports/{slug}` | GET | Fetch single AI report |
| `/api/v1/reports/categories` | GET | Fetch categories |
| `/api/v1/reports/definitions` | GET | Fetch report definitions |

### 7.5 JavaScript Module Dependencies

```javascript
// amd/src/block.js
define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/modal_factory',
    'core/modal_events',
    'core/chartjs',  // Moodle's Chart.js wrapper
    'report_adeptus_insights/chart_config',  // Shared chart config
], function($, Ajax, Notification, ModalFactory, ModalEvents, Chart, ChartConfig) {
    // Block implementation
});
```

---

## 8. Implementation Phases

### Phase 1: MVP Foundation
**Target:** Core functionality for immediate value
**Duration:** 2-3 weeks
**Features:**

| ID | Feature | Priority | Status |
|----|---------|----------|--------|
| F001 | Embedded Report Display | P0 | 🔶 Partial (Links mode complete) |
| F002 | Report Link List | P0 | ✅ Complete |
| F003 | Modal Report Viewer | P0 | ✅ Complete |
| F010 | Auto Context Detection | P0 | ✅ Complete |
| F020 | Manual Refresh | P0 | ✅ Complete |
| - | Basic edit_form configuration | P0 | ✅ Complete |
| - | Language strings | P0 | ✅ Complete |
| - | Basic styling | P0 | ✅ Complete |

**Deliverables:**
- [x] Functional block plugin installable via Moodle
- [ ] Single report embedded display (KPI/Embedded modes pending)
- [x] Report link list with modal popup
- [x] Context-aware filtering (category filter)
- [x] Manual refresh capability

---

### Phase 2: Enhanced Display
**Target:** Multiple display modes and better UX
**Duration:** 2 weeks
**Features:**

| ID | Feature | Priority | Status |
|----|---------|----------|--------|
| F004 | KPI Card Display | P1 | ✅ Complete (trend indicators + sparklines) |
| F005 | Multi-Report Grid | P1 | ✅ Complete |
| F006 | Tabbed Reports | P1 | ✅ Complete |
| F011 | Manual Context Override | P1 | ✅ Complete |
| F021 | Auto-Refresh | P1 | ✅ Complete |
| F030 | Quick Export | P1 | ✅ Complete (CSV in all modes) |

**Deliverables:**
- [x] KPI cards with trend indicators and sparklines
- [x] Multi-report grid layout
- [x] Tabbed interface with Table/Chart toggle
- [x] Auto-refresh functionality (configurable intervals, visibility-aware)
- [x] Export buttons (CSV)
- [x] Manual context override (course/category/site-wide)

---

### Phase 3: Intelligence Layer ✅ COMPLETE
**Target:** Alerts, personalization, advanced features
**Duration:** 2-3 weeks
**Features:**

| ID | Feature | Priority | Status |
|----|---------|----------|--------|
| F007 | Category Browser | P2 | ⬜ Not Started (deferred) |
| F022 | Alert Thresholds | P2 | ✅ Complete |
| F031 | Copy to Clipboard | P2 | ⬜ Not Started (deferred) |
| F040 | Favorite Reports | P2 | ⬜ Not Started (deferred) |
| F041 | Collapsed State Memory | P2 | ⬜ Not Started (deferred) |
| - | Sparkline charts | P2 | ✅ Complete (with DB-backed history) |
| - | Comparison mode | P2 | ⬜ Not Started (deferred) |
| - | KPI History Database | P2 | ✅ Complete |
| - | Configurable History Interval | P2 | ✅ Complete |
| - | Batch KPI Loading | P2 | ✅ Complete (2s load time) |
| - | Multi-Alert Support | P2 | ✅ Complete |
| - | Searchable Dropdowns | P2 | ✅ Complete (all report selectors) |

**Deliverables:**
- [x] Alert threshold configuration with multi-alert support
- [x] Visual alert indicators (status badges, color coding)
- [ ] User personalization features (deferred to future release)
- [x] Sparkline visualizations (with database-backed history)
- [x] KPI history stored in database with configurable save frequency
- [x] Optimized batch loading for KPI cards (~2 seconds)
- [x] Searchable dropdowns for all report selectors

---

### Phase 4: Notifications & Polish ✅ COMPLETE (Core Features)
**Target:** Complete alert system, production ready
**Duration:** 1-2 weeks
**Features:**

| ID | Feature | Priority | Status |
|----|---------|----------|--------|
| F023 | Alert Notifications | P3 | ✅ Complete |
| - | Moodle Messaging | P3 | ✅ Complete |
| - | Email Notifications | P3 | ✅ Complete (HTML + plain text) |
| - | Email digest | P3 | ⬜ Not Started (future enhancement) |
| - | Mobile responsiveness audit | P3 | ✅ Complete |
| - | Accessibility audit | P3 | ⬜ Pending |
| - | Performance optimization | P3 | ✅ Complete (batch loading) |
| - | Documentation | P3 | ✅ Complete |

**Deliverables:**
- [x] Moodle notification integration (message providers)
- [x] Email notifications (HTML with color-coded status)
- [x] Alert cooldown to prevent notification flooding
- [x] Fully responsive design (mobile audit complete)
- [ ] WCAG 2.1 AA compliance (pending audit)
- [x] Implementation documentation updated

---

## 9. Progress Tracker

### Overall Progress

```
Phase 1 (MVP):        ██████████  100%  (All 4 display modes implemented)
Phase 2 (Enhanced):   ██████████  100%  (All features complete)
Phase 3 (Intelligence): ██████████  100%  (Alerts, sparklines, KPI history, multi-alert)
Phase 4 (Polish):     █████████░  90%   (Notifications + mobile complete, accessibility pending)
─────────────────────────────────────────────
Overall:              █████████░  95%
```

### Detailed Task Tracker

#### Phase 1 Tasks

| Task | Status | Assigned | Notes |
|------|--------|----------|-------|
| Create plugin directory structure | ✅ | - | Complete |
| Create version.php with dependencies | ✅ | - | Complete |
| Create block_adeptus_insights.php | ✅ | - | Complete |
| Create db/access.php capabilities | ✅ | - | Complete |
| Create lang/en strings | ✅ | - | Complete |
| Create edit_form.php basic config | ✅ | - | Complete |
| Create basic mustache templates | ✅ | - | report_list, report_modal, block_content |
| Implement get_content() method | ✅ | - | Complete |
| Implement report_fetcher class | ✅ | - | Via external API |
| Implement context_filter class | ✅ | - | Category filter implemented |
| Create block.js main controller | ✅ | - | Full implementation with caching |
| Create report_loader.js | ✅ | - | Integrated in block.js |
| Create modal_handler.js | ✅ | - | Chart.js + table + pagination |
| Create basic styles.css | ✅ | - | Modern card styling |
| Test on course page | ✅ | - | Working |
| Test on dashboard | ✅ | - | Working |
| Test on site front page | ✅ | - | Working |
| Implement session storage caching | ✅ | - | 5-minute TTL |
| Implement hover preloading | ✅ | - | 300ms delay optimization |
| Category filter dropdown | ✅ | - | Dynamic population |
| Pagination with scroll fix | ✅ | - | scrollIntoView on page change |

#### Key Milestones

| Milestone | Target Date | Status | Actual Date |
|-----------|-------------|--------|-------------|
| Phase 1 Complete (MVP) | TBD | ✅ 100% | 2025-12-29 |
| Phase 2 Complete | TBD | ✅ 100% | 2025-12-30 |
| Phase 3 Complete (Intelligence Layer) | TBD | ✅ 100% | 2025-12-30 |
| Phase 4 Complete (Core Features) | TBD | ✅ 80% | 2025-12-30 |
| Alert System MVP | TBD | ✅ 100% | 2025-12-30 |
| Multi-Alert Support | TBD | ✅ 100% | 2025-12-30 |
| Accessibility Audit | TBD | ⬜ Pending | - |
| Production Release | TBD | 🔶 Ready for Testing | - |

### Issue Log

| ID | Description | Severity | Status | Resolution |
|----|-------------|----------|--------|------------|
| BUG-001 | Pagination scrolls to bottom of page on page 3+ | Medium | ✅ Resolved | Added scrollIntoView on pagination handlers |
| BUG-002 | Some reports display without names | Low | ✅ Resolved | Added fallback chain: name → title → display_name → slug |
| BUG-003 | Category filter not filtering reports | Medium | ✅ Resolved | Replaced static badge with dynamic select dropdown |

### Change Log

| Date | Version | Changes |
|------|---------|---------|
| 2025-12-29 | 0.0.0 | Initial planning document created |
| 2025-12-29 | 0.1.0 | Core plugin structure implemented: block class, templates, edit form |
| 2025-12-29 | 0.2.0 | Links display mode fully implemented with report list |
| 2025-12-29 | 0.3.0 | Modal report viewer with Chart.js integration (table + chart toggle) |
| 2025-12-29 | 0.4.0 | Modal pagination, CSV export, table sorting |
| 2025-12-29 | 0.5.0 | Modern UI styling, card-based report items, hover effects |
| 2025-12-29 | 0.6.0 | Category filter dropdown (dynamic population from reports) |
| 2025-12-29 | 0.7.0 | Performance optimization: sessionStorage caching, hover preloading |
| 2025-12-29 | 0.8.0 | Pagination scroll fix (scrollIntoView on page change) |
| 2025-12-29 | 0.9.0 | Report name fallback resolution for edge cases |
| 2025-12-29 | 0.9.1 | Console log cleanup for production readiness |
| 2025-12-29 | 0.9.2 | Initial GitLab repository commit |
| 2025-12-29 | 1.0.0 | Embedded display mode fully implemented with Chart.js |
| 2025-12-29 | 1.1.0 | KPI card display mode with real data loading and formatting |
| 2025-12-29 | 1.2.0 | Tabbed reports display mode with chart/table per tab |
| 2025-12-30 | 1.3.0 | Searchable dropdown for category filter (all modes) and report selector (embedded) |
| 2025-12-30 | 1.4.0 | Loading overlay for embedded mode report switching |
| 2025-12-30 | 1.5.0 | Responsive container query styling for side column layouts |
| 2025-12-30 | 1.6.0 | Tabbed Reports redesign: Table/Chart toggle, chart controls, pagination, CSV export per tab |
| 2025-12-30 | 1.7.0 | KPI Cards enhanced: trend indicators with percentage change, sparkline mini-charts with localStorage history |
| 2025-12-30 | 1.7.1 | Phase 2 complete: verified F011 (Context Override) and F021 (Auto-Refresh) already implemented |
| 2025-12-30 | 1.8.0 | **Database-backed KPI history**: Replaced localStorage with proper database storage for trend indicators and sparklines. New `block_adeptus_kpi_history` table, external services for save/get operations, scheduled cleanup task |
| 2025-12-30 | 1.8.1 | **Configurable KPI history save frequency**: Added block setting for history interval (1h/6h/12h/1d/3d/1w/1m), respects configured interval before saving new values |
| 2025-12-30 | 1.8.2 | **Batch KPI loading optimization**: New `/report/adeptus_insights/ajax/batch_kpi_data.php` endpoint for loading all KPI cards in a single request. Fixed PHP session locking issue. Reduced load time from ~10-24 seconds to ~2 seconds |
| 2025-12-30 | 1.9.0 | **Alert System implementation**: Complete threshold monitoring system with `block_adeptus_alerts` and `block_adeptus_alert_history` tables. `alert_manager.php` for evaluation logic. `check_alerts.php` scheduled task. Operators: gt, lt, eq, gte, lte, change_pct |
| 2025-12-30 | 1.9.1 | **Alert Notifications**: Moodle message provider integration, HTML email notifications with color-coded status (red/orange/green), cooldown periods, configurable notification preferences (warning/critical/recovery) |
| 2025-12-30 | 1.9.2 | **Searchable dropdowns**: Ajax autocomplete for alert report selector using `search_reports.php` external service with 5-minute caching. Searchable dropdowns for KPI and Tab report selectors |
| 2025-12-30 | 2.0.0 | **Multi-alert support**: Complete UI overhaul for managing multiple alerts per block. Card-based alert list with inline edit panel. AlertsManager JavaScript class (~450 lines). JSON-based configuration storage. Enable/disable toggles per alert |
| 2025-12-30 | 2.0.1 | **Email notification redesign**: Email notifications now sent to specific addresses (supports external stakeholders). New `notify_emails` field for comma/newline-separated addresses. Moodle messages remain role-based. `send_direct_email()` for non-Moodle users |
| 2025-12-31 | 2.0.2 | **Custom KPI icons**: Icon picker for KPI cards with 35 FontAwesome 4 icons in 11 categories (People, Education, Time, Status, Analytics, Numbers, Financial, Progress, Communication, Alerts, Feedback). Smart dropup positioning for picker near viewport edge. Fixed FA4 compatibility (fa-clock → fa-clock-o) |
| 2025-12-31 | 2.0.3 | **Mobile responsiveness audit**: Comprehensive responsive CSS for tablet (≤767px), small mobile (≤480px), extra-small (≤575px). 44px touch targets, horizontal scrollable tabs, full-screen modals on mobile, stacked form layouts. Edit form and icon picker mobile optimizations |
| 2025-12-31 | 2.0.4 | **KPI layout refinement**: Moved refresh button from empty toolbar to footer inline with timestamp (left-aligned timestamp, right-aligned refresh). Eliminates unnecessary whitespace above KPI cards for cleaner layout |
| 2025-12-31 | 2.0.5 | **Configuration form improvements**: (1) Added comprehensive help tooltips for configuration options (title, display mode, report source, category, context filter). (2) Implemented searchable dropdown for manual report selection (replaces non-functional textarea). (3) Fixed visibility toggle - manual report selector now only shows when "Manually selected reports" is selected. (4) Fixed dropdown hover text readability (resolved CSS conflict between bg-light and text-white classes) |

---

## 10. Future Considerations

### Potential Future Features (Post v1.0)

1. **Scheduled Reports**
   - Email reports on schedule (daily/weekly/monthly)
   - PDF generation with branding
   - Multiple recipient support

2. **Dashboard Builder**
   - Drag-and-drop dashboard layout
   - Save custom dashboard configurations
   - Share dashboards with roles

3. **Predictive Analytics**
   - At-risk student identification
   - Completion predictions
   - Trend forecasting

4. **Comparative Analytics**
   - Course vs course comparison
   - Cohort comparison
   - Year-over-year trends

5. **External Integrations**
   - Google Analytics connection
   - LTI tool support
   - Webhook triggers on alerts

6. **White-labeling**
   - Custom branding per block
   - Institutional themes
   - Custom color palettes

### Technical Debt Tracking

| Item | Priority | Notes |
|------|----------|-------|
| - | - | - |

### Performance Considerations

- Implement aggressive caching for report data
- Lazy load charts only when visible
- Pagination for large datasets
- Background workers for alert checking
- CDN for Chart.js if not bundled

#### KPI Batch Loading Optimization (v1.8.2)

The KPI card display mode now uses batch loading to significantly improve performance:

**Before optimization:** ~10-24 seconds for 4 KPI cards (sequential loading due to PHP session locking)

**After optimization:** ~2 seconds for 4 KPI cards

**Key changes:**
1. **Batch API endpoint** (`/report/adeptus_insights/ajax/batch_kpi_data.php`):
   - Fetches all report definitions in a single API call
   - Executes SQL queries for all requested reports
   - Returns all results in one response

2. **Session lock release** (`READ_ONLY_SESSION` + `session_write_close()`):
   - Allows parallel HTTP requests by releasing PHP session lock early
   - Applied to both batch endpoint and individual report generation

3. **JavaScript batch loading** (`loadKpiBatch()` method):
   - Collects all wizard report IDs
   - Makes single AJAX request to batch endpoint
   - Falls back to individual loading on failure

### Security Considerations

- All data access must respect Moodle capabilities
- API calls must use authenticated tokens
- SQL must use prepared statements
- User input must be sanitized
- CSRF protection on all forms

---

## Appendix A: Glossary

| Term | Definition |
|------|------------|
| **Block Instance** | A specific placement of a block on a page |
| **Context** | Moodle's permission scope (site, category, course, etc.) |
| **KPI** | Key Performance Indicator |
| **Sparkline** | Miniature inline chart |
| **Threshold** | Value that triggers an alert |
| **Wizard Report** | Pre-defined report from the wizard interface |
| **AI Report** | Report generated by the AI Assistant |

## Appendix B: Related Documentation

- [Moodle Block Plugin Documentation](https://moodledev.io/docs/5.1/apis/plugintypes/blocks)
- [Adeptus Insights Report Plugin](../README.md)
- [Backend API Documentation](../BACKEND_MANAGEMENT.md)
- [Category System Documentation](./CATEGORY_SYSTEM.md)

---

*Document maintained by: Development Team*
*Last reviewed: 2025-12-31*
*Plugin version: 2025123106 (v2.0.5)*

# G8: Inactivity Alerts & At-Risk Digest — Implementation Report

**Version:** 1.11.0 (2026021906)  
**Date:** 2026-02-19  
**Tier:** Enterprise

## What Was Built

### 1. Alert Conditions Engine (`classes/alert_engine.php`)
- Evaluates two pre-defined conditions:
  - **Inactive learners**: No login for X days (default 14, configurable 1-365)
  - **Low completion**: Course completion below Y% (default 30%, configurable 1-100%)
- Groups results by course for readability
- Enterprise tier feature gate via `license_tier` config check
- Deduplication via period-based digest logging

### 2. Scheduled Task (`classes/task/send_alert_digest.php`)
- Runs weekly (Monday 6 AM) by default, configurable via Moodle task admin
- Checks enterprise gate and enabled status before executing
- Prevents duplicate sends per period (daily/weekly/monthly key)
- Renders HTML email via Mustache template
- Uses Moodle's `email_to_user()` API with noreply sender

### 3. Configuration UI (`alert_settings.php`)
- Admin page for managing all alert settings:
  - Enable/disable alerts
  - Inactivity threshold (days)
  - Completion rate threshold (%)
  - Digest frequency (daily/weekly/monthly)
  - Recipient roles (manager/editingteacher/teacher checkboxes)
- Enterprise tier gate with upgrade prompt for lower tiers
- Requires `report/adeptus_insights:managealerts` capability

### 4. Email Template (`templates/alert_digest_email.mustache`)
- Professional HTML email with styled sections
- Separate sections for inactive learners and low completion learners
- Grouped by course with learner tables
- Includes link back to alert settings page

### 5. Database Tables
- `report_adeptus_alert_config` — Alert type configurations (with unique index on alert_type)
- `report_adeptus_alert_log` — Digest send log (unique index on period_key for dedup)

### 6. Capability
- `report/adeptus_insights:managealerts` — granted to manager and editingteacher by default

## Files Created/Modified
| File | Action |
|------|--------|
| `classes/alert_engine.php` | NEW |
| `classes/task/send_alert_digest.php` | NEW |
| `alert_settings.php` | NEW |
| `templates/alert_digest_email.mustache` | NEW |
| `version.php` | MODIFIED (1.10.0 → 1.11.0) |
| `db/install.xml` | MODIFIED (added 2 tables) |
| `db/upgrade.php` | MODIFIED (added upgrade step) |
| `db/access.php` | MODIFIED (added capability) |
| `db/tasks.php` | MODIFIED (added scheduled task) |
| `settings.php` | MODIFIED (added alert settings link) |
| `lang/en/report_adeptus_insights.php` | MODIFIED (added 20 strings) |

## Validation
- ✅ `php -l` — all files pass syntax check
- ✅ Cache purge successful
- ✅ Upgrade runs cleanly (2026021906 savepoint)

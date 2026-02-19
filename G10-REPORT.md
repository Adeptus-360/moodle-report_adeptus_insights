# G10: Rule-Based Alert Triggers — Phase 1 Report

**Date:** 2026-02-19
**Version:** 1.13.0 (2026021908)
**Commit:** 746e3c0

## What Was Built

### 1. Database Schema (2 new tables)
- **`report_adeptus_alert_rules`** — Admin-defined alert rules with fields: name, rule_type (grade_below|completion_stalled|inactive_days|login_gap), threshold, optional course_id/role_id, notify_roles (JSON), enabled flag, created_by, timestamps.
- **`report_adeptus_alert_logs`** — Per-user triggered alert log with rule_id, user_id, course_id, triggered_value, notified flag, timestamp. Indexed for deduplication lookups.

### 2. Rule Engine (`classes/alert/rule_engine.php`)
- 4 evaluators querying Moodle core tables:
  - **grade_below** — Users whose course final grade < threshold
  - **completion_stalled** — Users with no completion activity in X days
  - **inactive_days** — Enrolled users with no log actions in X days
  - **login_gap** — Users who haven't logged in for X days
- `evaluate_all()` iterates enabled rules, deduplicates (7-day window), logs matches
- `get_and_mark_pending()` for notification dispatch

### 3. Scheduled Task (`classes/task/evaluate_alert_rules.php`)
- Runs hourly via Moodle cron
- Calls rule engine, then sends Moodle notifications to roles specified in each rule's `notify_roles` field
- Uses `report_adeptus_insights/alert_rule_triggered` message provider

### 4. Capabilities
- `report/adeptus_insights:viewalerts` — manager, editingteacher, teacher

### 5. Message Provider
- `db/messages.php` with `alert_rule_triggered` provider gated by `viewalerts` capability

### 6. Lang Strings
- 18 new strings for task name, notification subject/body, rule type labels, CRUD messages

## Upgrade Status
✅ Purge caches — success
✅ DB upgrade — success (tables created)
✅ Git committed (not pushed)

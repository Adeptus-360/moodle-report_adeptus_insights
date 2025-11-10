# Single Source of Truth - Subscription Stats

## Overview
This document explains how subscription statistics are fetched and displayed across the plugin to ensure data consistency.

## API Endpoint - Single Source of Truth

**Endpoint**: `/report/adeptus_insights/ajax/check_subscription_status.php`

This is the **ONLY** endpoint used for fetching subscription status across the entire plugin.

### Data Flow

```
Backend (Laravel)
    ↓
    InstallationManager::get_subscription_details()
    ↓
check_subscription_status.php (Moodle Plugin)
    ↓
    ├─→ Wizard Home (wizard.js)
    ├─→ AI Assistant (assistant.js)
    └─→ Subscription Page (subscription.js)
```

## Fields Returned by API

The `check_subscription_status.php` endpoint returns the following fields:

```json
{
  "success": true,
  "data": {
    // Plan Information
    "plan_name": "Free Plan",
    "plan_price": "0",
    "status": "active",
    "is_free_plan": true,
    
    // Credit Information (Tier-based)
    "credit_type": "basic",
    "total_credits_used_this_month": 260,
    "plan_total_credits_limit": 1000,
    
    // Reports Information
    "usage_type": "all-time",
    "reports_generated_this_month": 7,
    "plan_exports_limit": 10,
    
    // Exports Information
    "exports_used": 6,
    "exports_remaining": 4
  }
}
```

## Implementation Across Views

### 1. Wizard Home (`wizard.js`)

**Location**: Lines 2607-2690 (updateReportsLeftCounter) and 2807-2890 (updateExportsCounter)

**Displays**:
- **Reports Generated Counter**: `reports_generated_this_month` / `plan_exports_limit`
- **Exports Used Counter**: `exports_used` / `plan_exports_limit`

**API Calls**:
```javascript
// Reports counter update
const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/check_subscription_status.php?t=${Date.now()}`);

// Exports counter update (same endpoint)
const response = await fetch(`${this.wizardData.wwwroot}/report/adeptus_insights/ajax/check_subscription_status.php?t=${Date.now()}`);
```

**Logging**: Prefix `[WIZARD HOME]` and `[WIZARD HOME - EXPORTS]`

### 2. AI Assistant (`assistant.js`)

**Location**: Lines 1666-1700 (updateSubscriptionInfo)

**Displays** (Top Header):
- **Plan**: `plan_name`
- **Status**: `status` (with badge: active = green, other = warning)
- **Reports**: `reports_generated_this_month` / `plan_exports_limit`
- **AI Credits**: `total_credits_used_this_month` / `plan_total_credits_limit` (with `credit_type` label)

**API Call**:
```javascript
const response = await fetch(`${M.cfg.wwwroot}/report/adeptus_insights/ajax/check_subscription_status.php?t=${Date.now()}`);
```

**Logging**: Prefix `[AI Assistant]`

### 3. Subscription Page (`subscription.js`)

**Location**: Similar implementation

**Displays**:
- Full subscription details including plan, status, credits, reports, and exports

**API Call**: Same endpoint with cache-busting

## Enhanced Logging

All three views now include comprehensive logging to track data consistency:

### Wizard Home Logs:
```
[WIZARD HOME] ========================================
[WIZARD HOME] SUBSCRIPTION STATUS API RESPONSE
[WIZARD HOME] ========================================
[WIZARD HOME] Full response: { ... }
[WIZARD HOME] Endpoint: /report/adeptus_insights/ajax/check_subscription_status.php
[WIZARD HOME] Extracted fields for display:
[WIZARD HOME]   - plan_name: Free Plan
[WIZARD HOME]   - status: active
[WIZARD HOME]   - reports_generated_this_month: 7
[WIZARD HOME]   - total_credits_used_this_month: 260
...
```

### AI Assistant Logs:
```
[AI Assistant] ===== SUBSCRIPTION DATA BREAKDOWN =====
[AI Assistant] Plan Name: Free Plan
[AI Assistant] Status: active
[AI Assistant] Reports Generated: 7
[AI Assistant] AI Credits Used: 260
...
```

## Cache Busting

All API calls include a timestamp parameter to prevent caching:

```javascript
?t=${Date.now()}
```

This ensures every fetch returns fresh data from the backend.

## Data Consistency Guarantees

### ✅ Single API Endpoint
- All views call `/report/adeptus_insights/ajax/check_subscription_status.php`
- No parallel data sources or backend API calls

### ✅ Fresh Data on Every Fetch
- Cache-Control: no-cache header
- Timestamp query parameter
- No frontend caching of subscription data

### ✅ Consistent Field Usage
- All views use the same field names from `data.data.*`
- No data transformation or calculation differences

### ✅ Synchronized Updates
- Reports counter updates after report generation
- Exports counter updates after successful export
- AI Assistant refreshes on page load and manual refresh

## Troubleshooting

If stats appear inconsistent:

1. **Check Browser Console**: Look for `[WIZARD HOME]` and `[AI Assistant]` log prefixes
2. **Compare API Responses**: Verify both are receiving identical JSON responses
3. **Check Timing**: Ensure both fetches happen after the same operations
4. **Verify Cache-Busting**: Confirm timestamp parameter is being added
5. **Check Backend Logs**: Look for `check_subscription_status.php` in error logs

## Testing Verification

To verify single source of truth:

1. Open Wizard Home and check browser console
2. Look for `[WIZARD HOME] SUBSCRIPTION STATUS API RESPONSE`
3. Copy the full response JSON
4. Navigate to AI Assistant
5. Look for `[AI Assistant] SUBSCRIPTION DATA BREAKDOWN`
6. Compare the values - they should be **IDENTICAL**

## Maintenance

When adding new subscription-related features:

1. ✅ **DO**: Use `/report/adeptus_insights/ajax/check_subscription_status.php`
2. ✅ **DO**: Add cache-busting with `?t=${Date.now()}`
3. ✅ **DO**: Use fields from `data.data.*` directly
4. ✅ **DO**: Add prefix logging (e.g., `[YOUR_VIEW]`)

5. ❌ **DON'T**: Create new subscription status endpoints
6. ❌ **DON'T**: Call backend API directly from frontend
7. ❌ **DON'T**: Cache subscription data in localStorage
8. ❌ **DON'T**: Transform or recalculate subscription values

## Summary

All subscription statistics across the plugin are fetched from a **single API endpoint**, ensuring data consistency and eliminating discrepancies. Enhanced logging allows easy verification that all views are receiving and displaying identical data.

**Last Updated**: October 30, 2025  
**Status**: ✅ Implemented and Verified


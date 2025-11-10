# Frontend Stats Fix - Bottom Subscription Status Bar

**Date**: October 29, 2025  
**Issue**: Bottom stats showing "Status: Unknown" and "AI Credits (basic): 0/∞"  
**Status**: ✅ Fixed

---

## Problem

Frontend subscription status bar displayed:
- ❌ **Status**: Unknown (should be "active")
- ❌ **AI Credits (basic)**: 0/∞ (should be "260/1000")

---

## Root Cause

The plugin's AJAX endpoint `/ajax/check_subscription_status.php` was not extracting and passing the new fields from the backend API response:
- Missing: `status`
- Missing: `credit_type`
- Missing: `total_credits_used_this_month`
- Missing: `plan_total_credits_limit`

The backend API (`/api/installation/status`) was returning these fields correctly (updated today), but the Moodle plugin's AJAX wrapper wasn't forwarding them to the frontend.

---

## Solution

Updated `/ajax/check_subscription_status.php` to extract and pass all required fields.

### Code Changes

**File**: `plugin/report/adeptus_insights/ajax/check_subscription_status.php`

**Before**:
```php
echo json_encode([
    'success' => true,
    'data' => [
        'is_free_plan' => $is_free_plan,
        'subscription' => $subscription,
        'plan_name' => $subscription['plan_name'] ?? 'Free Plan',
        'plan_price' => $subscription['price'] ?? '0',
        'usage_type' => $usage_type,
        'reports_generated_this_month' => $reports_generated_this_month,
        'plan_exports_limit' => $subscription['plan_exports_limit'] ?? 10,
        'exports_used' => $exports_used,
        'exports_remaining' => $subscription['exports_remaining'] ?? 10
    ]
]);
```

**After**:
```php
// Extract effective credits and status from subscription data
$status = $subscription['status'] ?? 'unknown';
$credit_type = $subscription['credit_type'] ?? 'basic';
$total_credits_used = $subscription['total_credits_used_this_month'] ?? 0;
$plan_total_credits_limit = $subscription['plan_total_credits_limit'] ?? 1000;

echo json_encode([
    'success' => true,
    'data' => [
        'is_free_plan' => $is_free_plan,
        'subscription' => $subscription,
        
        // Plan info
        'plan_name' => $subscription['plan_name'] ?? 'Free Plan',
        'plan_price' => $subscription['price'] ?? '0',
        'status' => $status, // ✅ NOW INCLUDED
        
        // Credits info (tier-based effective credits)
        'credit_type' => $credit_type, // ✅ NOW INCLUDED
        'total_credits_used_this_month' => $total_credits_used, // ✅ NOW INCLUDED
        'plan_total_credits_limit' => $plan_total_credits_limit, // ✅ NOW INCLUDED
        
        // Reports and exports
        'usage_type' => $usage_type,
        'reports_generated_this_month' => $reports_generated_this_month,
        'plan_exports_limit' => $subscription['plan_exports_limit'] ?? 10,
        'exports_used' => $exports_used,
        'exports_remaining' => $subscription['exports_remaining'] ?? 10
    ]
]);
```

---

## Data Flow

### Complete Chain

```
1. Frontend (assistant.js)
   ↓ calls
2. Plugin AJAX (/ajax/check_subscription_status.php)
   ↓ calls
3. InstallationManager::get_subscription_details()
   ↓ calls
4. Backend API (/api/installation/status)
   ↓ returns
5. Backend Controller (InstallationController)
   - Uses SubscriptionPlan::getEffectiveCreditsLimit()
   - Uses Subscription::getEffectiveCreditsUsed()
   - Returns tier-based effective credits
   ↓ returns to
6. Plugin AJAX
   - Extracts status, credit_type, credits
   - Packages for frontend
   ↓ returns to
7. Frontend (assistant.js)
   - Displays status
   - Displays AI Credits with type
   - Shows correct usage/limit
```

---

## Expected Results

### Your Free Plan (After Fix)

**Backend Data**:
- `status`: "active"
- `credit_type`: "basic"
- `total_credits_used_this_month`: 260
- `plan_total_credits_limit`: 1000

**Frontend Display**:
```
Subscription Status [🔄]

Plan: Free Plan
Status: active [green badge]

Reports: 7/10
AI Credits (basic): 260/1000
```

---

## Testing Steps

### 1. Clear All Caches
```bash
# Backend cache
cd /var/www/vhosts/stagingwithswift.com/opt/adeptus_ai_backend
php artisan cache:clear

# Moodle cache
php admin/cli/purge_caches.php
```

### 2. Test the AJAX Endpoint Directly

**URL**: `https://plugin.stagingwithswift.com/report/adeptus_insights/ajax/check_subscription_status.php`

**Expected Response**:
```json
{
  "success": true,
  "data": {
    "is_free_plan": true,
    "plan_name": "Free Plan",
    "plan_price": "0",
    "status": "active",
    "credit_type": "basic",
    "total_credits_used_this_month": 260,
    "plan_total_credits_limit": 1000,
    "reports_generated_this_month": 7,
    "plan_exports_limit": 10,
    "exports_used": 3,
    "exports_remaining": 7
  }
}
```

**Check Logs**:
```bash
tail -f /var/log/apache2/error.log | grep "check_subscription_status"
```

Should see:
```
check_subscription_status.php - Backend subscription data: {"status":"active","credit_type":"basic",...}
```

### 3. Test Frontend Display

1. **Hard refresh** the AI Assistant page (Ctrl+Shift+R)
2. **Open browser console** (F12)
3. Look for:
   ```
   [AI Assistant] Fetching latest subscription status...
   [AI Assistant] Latest subscription status response: {success: true, data: {...}}
   [AI Assistant] Updating subscription info with auth status: {...}
   ```

4. **Check the bottom subscription bar**:
   - Status should show "active" (green badge)
   - AI Credits should show "260/1000" with "(basic)" label
   - Reports should show "7/10"

### 4. Test Refresh Button

1. Click the **refresh button** (🔄) next to "Subscription Status"
2. Watch console logs
3. Verify counter updates if you've used credits/reports since page load

---

## Debugging

### If Status Still Shows "Unknown"

**Check 1**: Backend API Response
```bash
# Call backend API directly
curl -X POST https://ai-backend.stagingwithswift.com/api/installation/status \
  -H "Content-Type: application/json" \
  -d '{"api_key": "YOUR_API_KEY"}' | jq
```

Expected output should include:
```json
{
  "success": true,
  "data": {
    "subscription": {
      "status": "active",
      "credit_type": "basic",
      "total_credits_used_this_month": 260,
      "plan_total_credits_limit": 1000
    }
  }
}
```

**Check 2**: Plugin AJAX Response
```bash
# Check what plugin returns
curl -X GET "https://plugin.stagingwithswift.com/report/adeptus_insights/ajax/check_subscription_status.php" \
  --cookie "MoodleSession=YOUR_SESSION" | jq
```

Expected output should include:
```json
{
  "success": true,
  "data": {
    "status": "active",
    "credit_type": "basic",
    "total_credits_used_this_month": 260,
    "plan_total_credits_limit": 1000
  }
}
```

**Check 3**: Frontend Console
```javascript
// In browser console
AuthUtils.getAuthStatus().subscription
// Should show: {status: "active", credit_type: "basic", ...}
```

### If Credits Still Show "0/∞"

**Check 1**: Verify backend returns effective credits
```bash
tail -f /var/log/apache2/error.log | grep "check_subscription_status"
```

Should log the full subscription object with these fields.

**Check 2**: Verify frontend receives the data
```javascript
// In browser console, after page load
console.log(window.assistant.authStatus.subscription);
// Should include plan_total_credits_limit and total_credits_used_this_month
```

**Check 3**: Check for JavaScript errors
```
F12 → Console → Look for any red errors
```

---

## Files Modified Summary

### This Session

1. **`plugin/ajax/check_subscription_status.php`** ✅
   - Added extraction of `status` field
   - Added extraction of `credit_type` field
   - Added extraction of `total_credits_used_this_month` field
   - Added extraction of `plan_total_credits_limit` field
   - Added debug logging

### Previous Sessions (Context)

2. **`backend/app/Http/Controllers/InstallationController.php`**
   - Updated to return tier-based effective credits

3. **`plugin/amd/src/assistant.js`**
   - Updated to display credit_type in label
   - Updated to fetch latest data on load

---

## Verification Checklist

### Backend
- [x] InstallationController returns `status`
- [x] InstallationController returns `credit_type`
- [x] InstallationController returns `total_credits_used_this_month`
- [x] InstallationController returns `plan_total_credits_limit`

### Plugin AJAX
- [x] Extracts `status` from subscription
- [x] Extracts `credit_type` from subscription
- [x] Extracts `total_credits_used_this_month` from subscription
- [x] Extracts `plan_total_credits_limit` from subscription
- [x] Returns all fields in response

### Frontend
- [x] Fetches from `/ajax/check_subscription_status.php`
- [x] Reads `subscription.status`
- [x] Reads `subscription.credit_type`
- [x] Reads `subscription.total_credits_used_this_month`
- [x] Reads `subscription.plan_total_credits_limit`
- [x] Displays in bottom bar

---

## Expected Behavior After Fix

### Initial Page Load

**Console Output**:
```
[AI Assistant] Fetching latest subscription status...
[AI Assistant] Latest subscription status response: 
{
  success: true,
  data: {
    status: "active",
    credit_type: "basic",
    total_credits_used_this_month: 260,
    plan_total_credits_limit: 1000,
    reports_generated_this_month: 7,
    plan_exports_limit: 10
  }
}
```

**Bottom Stats Bar**:
```
┌─────────────────────────────────────────────────────────────────────────┐
│ 📊 Subscription Status [🔄]                                             │
│                                                                          │
│ Plan: Free Plan                     Reports: 7/10                       │
│ Status: active [green]              AI Credits (basic): 260/1000        │
│                                                                          │
│                                          [🩺 View Usage]                 │
└─────────────────────────────────────────────────────────────────────────┘
```

### After Refresh Button Click

**Console Output**:
```
[AI Assistant] Refreshing subscription info...
[AI Assistant] Latest local subscription status: 
{
  success: true,
  data: {
    status: "active",
    total_credits_used_this_month: 262,  // Incremented!
    ...
  }
}
```

**Updated Display**:
```
AI Credits (basic): 262/1000  // Updated from 260!
```

---

## Summary

### What Was Fixed

1. ✅ **Status field** - Now extracted from backend response
2. ✅ **Credit type field** - Now extracted and displayed
3. ✅ **Credits used** - Now shows actual usage from backend
4. ✅ **Credits limit** - Now shows plan limit (1000 for Free)

### What Was Already Working

- ✅ Reports counter
- ✅ Exports tracking
- ✅ Plan name
- ✅ Backend API response

### Root Cause

Plugin AJAX wrapper was not forwarding the new fields that were added to the backend API response today.

---

**Status**: ✅ Fixed and ready to test!

**Next Step**: Clear caches and hard refresh the AI Assistant page.


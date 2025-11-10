# AI Assistant Fixes - October 29, 2025

## Issues Fixed

### 1. ✅ Report Table Overflow Issue
**Problem:** The report table in the Reports tab was extending beyond its container and overlapping with the bottom "Subscription Status" bar.

**Root Cause:** The report view container (`.report-view-body`) had no height constraints, allowing the table content to overflow and render on top of other page elements.

**Solution:**
- Added `max-height: 700px` and `overflow-y: auto` to the report view body container
- This ensures the table stays within its designated area and provides scrolling when content exceeds the height

**Files Modified:**
- `/report/adeptus_insights/templates/assistant.mustache` (line 142)

**Code Change:**
```html
<!-- Before -->
<div class="card-body p-4 report-view-body">

<!-- After -->
<div class="card-body p-4 report-view-body" style="max-height: 700px; overflow-y: auto;">
```

---

### 2. ✅ Subscription Status Data Not Updating
**Problem:** The bottom "Subscription Status" bar was showing stale data:
- "Reports: 0/10" 
- "Total AI Credits: 0/1000"

The counters were not reflecting the latest usage data, while the wizard view was correctly fetching and displaying current values.

**Root Cause:** The AI Assistant was only pulling subscription data from the cached `AuthUtils` auth status on initial load, without fetching fresh data from the server.

**Solution:**
Implemented the same data fetching pattern used by the wizard view:

1. **On Initial Load (`updateSubscriptionInfo`):**
   - Now fetches latest data from `/ajax/check_subscription_status.php` before displaying
   - Updates `AuthUtils` with fresh subscription data
   - Displays the most current usage statistics

2. **On Manual Refresh (`refreshSubscriptionInfo`):**
   - Uses the local subscription status endpoint to get real-time counts
   - Updates both `AuthUtils` and global auth data
   - Ensures all counters reflect current usage

**Files Modified:**
- `/report/adeptus_insights/amd/src/assistant.js`
  - `updateSubscriptionInfo()` function (lines 1530-1566)
  - `refreshSubscriptionInfo()` function (lines 1743-1796)

**Code Changes:**

#### updateSubscriptionInfo() - Now Async with Data Fetch
```javascript
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

        if (data.success && data.data) {
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
    // ... rest of the display logic
}
```

#### refreshSubscriptionInfo() - Now Uses Local Endpoint
```javascript
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
}
```

---

## Technical Details

### Data Flow Comparison

#### Wizard View (Working)
```
1. User loads page
2. JavaScript calls check_subscription_status.php
3. PHP queries database for latest counts
4. Returns: reports_generated_this_month, exports_used, etc.
5. Display updated with fresh data
```

#### AI Assistant (Before Fix)
```
1. User loads page
2. JavaScript reads cached AuthUtils data
3. Display shows stale/initial data
4. No server call = no updates
```

#### AI Assistant (After Fix)
```
1. User loads page
2. JavaScript calls check_subscription_status.php
3. PHP queries database for latest counts
4. AuthUtils updated with fresh data
5. Display updated with current usage
6. Refresh button re-fetches data on demand
```

### API Endpoint Used
**`/report/adeptus_insights/ajax/check_subscription_status.php`**

Returns:
```json
{
  "success": true,
  "data": {
    "plan_name": "Free Plan",
    "status": "active",
    "reports_generated_this_month": 7,
    "exports_used": 3,
    "plan_exports_limit": 10,
    "total_credits_used_this_month": 245,
    "plan_total_credits_limit": 1000,
    // ... other subscription fields
  }
}
```

### Cache Busting
Both functions use timestamp-based cache busting:
```javascript
`${url}?t=${Date.now()}`
```
This ensures the browser always fetches fresh data instead of serving cached responses.

---

## Testing Checklist

### Issue 1: Table Overflow
- [x] Load AI Assistant page
- [x] Navigate to Reports tab
- [x] Click any report with large dataset (30,000+ rows)
- [x] Verify table displays with scroll within its container
- [x] Verify table does NOT overlap bottom subscription bar
- [x] Verify pagination controls are visible and functional
- [x] Test with different screen sizes/zoom levels

### Issue 2: Subscription Data
- [x] Load AI Assistant page
- [x] Check bottom subscription bar shows correct values
- [x] Open browser console to verify API call to check_subscription_status.php
- [x] Generate a new report in wizard view
- [x] Return to AI Assistant
- [x] Verify "Reports" counter has incremented
- [x] Click refresh button on subscription status bar
- [x] Verify counters update to latest values
- [x] Check console logs show fresh data fetch

### Comparison Test
- [x] Check usage in Wizard view: "7/10 Reports Generated"
- [x] Check usage in AI Assistant: Should match "7/10"
- [x] Generate new report
- [x] Both views should show "8/10"

---

## Performance Considerations

### Minimal Impact
- Single lightweight API call on page load (~100-200ms)
- Endpoint queries local database (fast)
- No impact on subsequent page interactions
- Manual refresh only fetches on user action

### Optimizations in Place
- Cache busting prevents stale data
- Async/await prevents UI blocking
- Loading indicators show fetch status
- Error handling prevents crash on failure

---

## Browser Compatibility

Both fixes use modern JavaScript features:
- `async/await` (ES2017)
- `fetch` API (ES2015)
- CSS `overflow-y: auto`

**Supported Browsers:**
- Chrome 55+
- Firefox 52+
- Safari 10.1+
- Edge 79+

For older browsers, Moodle's Babel transpilation should handle compatibility.

---

## Debugging

### Console Logs Added

**Subscription Data Fetch:**
```javascript
'[AI Assistant] Fetching latest subscription status...'
'[AI Assistant] Latest subscription status response:', data
'[AI Assistant] Updating subscription info with auth status:', authStatus
'[AI Assistant] Subscription info refreshed successfully'
```

**Error Cases:**
```javascript
'[AI Assistant] Error fetching latest subscription status:', error
'[AI Assistant] Failed to refresh subscription info:', error
```

### How to Debug

1. Open browser console (F12)
2. Navigate to AI Assistant
3. Look for subscription-related logs
4. Check Network tab for `/ajax/check_subscription_status.php` calls
5. Verify response contains latest data
6. Check if AuthUtils is being updated

---

## Future Enhancements

### Potential Improvements
1. **Auto-refresh on tab switch**: Fetch latest data when switching between AI Assistant and Reports tabs
2. **Polling**: Auto-refresh every 30 seconds for real-time updates
3. **WebSocket**: Push updates from server when usage changes
4. **Visual feedback**: Show "Updated X seconds ago" timestamp
5. **Optimistic updates**: Update UI immediately after actions, then sync with server

### Related Improvements
1. **Unified data layer**: Create a shared subscription service used by both wizard and assistant
2. **State management**: Implement proper state management (Redux/Vuex style)
3. **Caching strategy**: Smart caching with expiry and invalidation
4. **Error recovery**: Retry logic for failed fetches

---

## Summary

### What Was Fixed
✅ **Issue 1**: Report table now contained within its view (no overlap)
✅ **Issue 2**: Subscription status bar now shows latest real-time data

### How It Was Fixed
1. **Container constraints**: Added max-height and overflow to report view
2. **Data fetching**: Implemented fresh data fetch on load and refresh
3. **Consistency**: AI Assistant now uses same endpoint as wizard view

### Impact
- **User Experience**: Accurate usage data displayed at all times
- **UI/UX**: Clean layout with no visual overlaps
- **Consistency**: Both wizard and assistant show matching data
- **Reliability**: Manual refresh ensures users can always get current state

---

## Changelog

### 2025-10-29
- Fixed report table overflow in Reports tab
- Implemented fresh subscription data fetching on page load
- Updated refresh button to use local subscription status endpoint
- Added comprehensive logging for debugging
- Documented data flow and testing procedures

---

**Status**: ✅ Both issues resolved and tested
**Compiled**: ✅ grunt amd completed successfully
**Documentation**: ✅ Complete


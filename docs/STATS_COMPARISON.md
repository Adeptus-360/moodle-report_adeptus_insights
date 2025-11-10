# Stats Comparison - Wizard Home vs AI Assistant

## Quick Reference

### Same API Endpoint ✅
Both views call: `/report/adeptus_insights/ajax/check_subscription_status.php`

### Same Data Source ✅
```json
{
  "success": true,
  "data": {
    "plan_name": "Free Plan",
    "status": "active",
    "reports_generated_this_month": 7,
    "plan_exports_limit": 10,
    "exports_used": 6,
    "total_credits_used_this_month": 260,
    "plan_total_credits_limit": 1000,
    "credit_type": "basic"
  }
}
```

---

## What Each View Displays

### Wizard Home (Header Counters)

**Display Location**: Top right of page

```
╔═══════════════════════════════╗
║  📊 7/10 Reports Generated    ║
║     Free Plan Total Limit     ║
╠═══════════════════════════════╣
║  💾 6/10 Exports Used         ║
║     Monthly Export Limit      ║
╚═══════════════════════════════╝
```

**Fields Used**:
- `reports_generated_this_month`: 7
- `plan_exports_limit`: 10
- `exports_used`: 6

**What's NOT Shown**:
- ❌ Plan name
- ❌ Status badge
- ❌ AI Credits

---

### AI Assistant (Subscription Header)

**Display Location**: Top of page (collapsible header)

```
╔════════════════════════════════════════════╗
║  📊 Subscription Status        🔄 Refresh  ║
╠════════════════════════════════════════════╣
║  Plan: Free Plan                           ║
║  Status: [active]  ← Green badge           ║
║                                            ║
║  Reports: 7/10                             ║
║  AI Credits (basic): 260/1000              ║
╠════════════════════════════════════════════╣
║  [🔬 View Usage]                           ║
╚════════════════════════════════════════════╝
```

**Fields Used**:
- `plan_name`: "Free Plan"
- `status`: "active"
- `reports_generated_this_month`: 7
- `plan_exports_limit`: 10
- `total_credits_used_this_month`: 260
- `plan_total_credits_limit`: 1000
- `credit_type`: "basic"

**What's NOT Shown**:
- ❌ Exports counter (not relevant for chat interface)

---

## Side-by-Side Comparison

| Metric | Wizard Home | AI Assistant | Same Data? |
|--------|-------------|--------------|------------|
| **Plan Name** | ❌ Not shown | ✅ "Free Plan" | ✅ Yes |
| **Status** | ❌ Not shown | ✅ "active" | ✅ Yes |
| **Reports** | ✅ 7/10 | ✅ 7/10 | ✅ **MATCH** |
| **Exports** | ✅ 6/10 | ❌ Not shown | ✅ Yes |
| **AI Credits** | ❌ Not shown | ✅ 260/1000 (basic) | ✅ Yes |

### Key Insight

**Both views use IDENTICAL data** but display **different subsets** based on their purpose:

- **Wizard Home**: Focus on report/export generation limits
- **AI Assistant**: Focus on overall subscription status and AI credit usage

---

## Why The Difference?

### UX Design Decision

Each view shows metrics **relevant to its context**:

#### Wizard Home Context:
- User is generating **reports** → Show report limit
- User is **exporting data** → Show export limit
- User doesn't need plan details → Hide plan name/status

#### AI Assistant Context:
- User is **chatting with AI** → Show AI credit usage
- User might have questions about plan → Show plan name
- User wants to know if subscription is active → Show status
- Exports not relevant here → Hide export counter

---

## Console Logging Examples

### When Opening Wizard Home

```javascript
[WIZARD HOME] ========================================
[WIZARD HOME] SUBSCRIPTION STATUS API RESPONSE
[WIZARD HOME] ========================================
[WIZARD HOME] Full response: {
  "success": true,
  "data": {
    "plan_name": "Free Plan",
    "status": "active",
    "reports_generated_this_month": 7,
    "plan_exports_limit": 10,
    "total_credits_used_this_month": 260,
    "plan_total_credits_limit": 1000,
    "credit_type": "basic",
    "exports_used": 6
  }
}
[WIZARD HOME] Endpoint: /report/adeptus_insights/ajax/check_subscription_status.php
[WIZARD HOME] Extracted fields for display:
[WIZARD HOME]   - reports_generated_this_month: 7
[WIZARD HOME]   - plan_exports_limit: 10
[WIZARD HOME]   - exports_used: 6
```

### When Opening AI Assistant

```javascript
[AI Assistant] ===== SUBSCRIPTION DATA BREAKDOWN =====
[AI Assistant] Plan Name: Free Plan
[AI Assistant] Status: active
[AI Assistant] Credit Type: basic
[AI Assistant] Reports Generated: 7
[AI Assistant] Reports Limit: 10
[AI Assistant] AI Credits Used: 260
[AI Assistant] AI Credits Limit: 1000
[AI Assistant] ========================================
```

---

## Verification Checklist

To verify stats consistency:

- [ ] Open Wizard Home
- [ ] Open browser console (F12)
- [ ] Note `reports_generated_this_month` value (e.g., 7)
- [ ] Note `total_credits_used_this_month` value (e.g., 260)
- [ ] Open AI Assistant
- [ ] Note "Reports Generated" value (should be 7)
- [ ] Note "AI Credits Used" value (should be 260)
- [ ] **✅ Values should MATCH**

---

## Common Misconceptions

### ❌ Misconception 1: "The views show different numbers"

**Reality**: They show different **metrics**, not different values for the same metric.
- Wizard: Shows reports (7) and exports (6)
- Assistant: Shows reports (7) and AI credits (260)

### ❌ Misconception 2: "They must be pulling from different APIs"

**Reality**: Same endpoint for both → `/report/adeptus_insights/ajax/check_subscription_status.php`

### ❌ Misconception 3: "One is cached and one is fresh"

**Reality**: Both use cache-busting with `?t=${Date.now()}` parameter

---

## Troubleshooting

### If Reports Counter Shows Different Values

1. Check if both fetches happened at the same time
2. If Wizard shows 7 and Assistant shows 6, one fetch happened before a report was generated
3. Refresh both pages to sync

### If Any Discrepancy Appears

1. Open console in both views
2. Compare the logged JSON responses
3. If JSON is identical but display differs → UI bug
4. If JSON is different → timing issue (one stale, one fresh)

---

## Summary

✅ **Single Source of Truth**: Both use same API  
✅ **Same Data**: Both receive identical JSON  
✅ **Different Display**: Intentional UX design  
✅ **Fully Logged**: Easy to verify consistency  
✅ **Cache-Proof**: Timestamp ensures fresh data  

**Conclusion**: The views are **consistent** in their data source. They just display different subsets of that data based on what's relevant to the user's current task.

---

**Last Updated**: October 30, 2025  
**Status**: ✅ Verified and Documented


# iiko Integration Fixes - Complete Summary

## Overview

This document summarizes the fixes applied to resolve all errors reported in the iiko integration system.

## Problems Reported

### 1. "Not Found" Errors for Synced Data
**Symptoms:**
- Clicking on "📂 Категории" (Categories) → ❌ Not Found
- Clicking on "🍕 Товары" (Products) → ❌ Not Found  
- Clicking on "🚫 Стоп-листы" (Stop-lists) → ❌ Not Found

**Root Cause:**
Users were trying to view synchronized data before running the synchronization process. The local database tables (`categories`, `products`, `stop_lists`) were empty.

**Solution:**
- Made `organization_id` parameter optional for `/data/stop-lists` endpoint
- Added helpful warning messages when data is empty, directing users to run "Полную синхронизацию" (Full Synchronization) first
- Improved UX with clear instructions

### 2. "TOO_MANY_DATA_REQUESTED" Errors (HTTP 422)
**Symptoms:**
- Loading delivery orders → ❌ Error: "Too many data requested"
- Loading iiko Cloud orders → ❌ Error: "Too many data requested"

**Root Cause:**
The system was hard-coded to request 7 days of order data, which exceeded iiko API's limit (~1000 orders).

**Solution:**
- Reduced default date range from 7 days to 1 day
- Added configurable date range selector in UI (1, 2, 3, 7 days options)
- Added server-side validation to prevent excessive date ranges (max 7 days)
- Updated both orders page and maintenance page with date controls

### 3. "organizationId not found" Error (HTTP 400)
**Symptoms:**
- Loading loyalty programs → ❌ Error: "Required property 'organizationId' not found in JSON"

**Root Cause:**
The `organization_id` was not configured in the API settings.

**Solution:**
Existing error handling already guides users correctly. This is expected behavior when organization_id is not configured. No code changes needed - users must configure organization_id in API settings first.

## Technical Changes

### Backend Changes

#### File: `backend/app/routes.py`

**Change 1: Optional organization_id for stop-lists**
```python
# Before
async def get_stop_lists(
    organization_id: str,  # Required
    ...
)

# After
async def get_stop_lists(
    organization_id: Optional[str] = None,  # Optional
    ...
)
```

**Change 2: Days parameter with validation**
```python
# Before
async def get_iiko_deliveries(
    setting_id: int,
    organization_id: str,
    statuses: str = "...",
    # No days parameter
    ...
):

# After  
async def get_iiko_deliveries(
    setting_id: int,
    organization_id: str,
    statuses: str = "...",
    days: int = 1,  # New parameter with default
    ...
):
    # Validate days parameter
    if days < 1 or days > 7:
        raise HTTPException(status_code=400, detail="Параметр 'days' должен быть от 1 до 7")
```

#### File: `backend/app/iiko_service.py`

**Change: Configurable date range**
```python
# Before
async def get_deliveries_by_statuses(self, organization_id: str, statuses: list) -> dict:
    date_from = (now - timedelta(days=7)).strftime(...)  # Hard-coded 7 days
    
# After
async def get_deliveries_by_statuses(self, organization_id: str, statuses: list, days: int = 1) -> dict:
    date_from = (now - timedelta(days=days)).strftime(...)  # Configurable
```

### Frontend Changes

#### File: `frontend/app/Http/Controllers/AdminController.php`

**Change: Pass days parameter**
```php
public function apiIikoDeliveries(Request $request): JsonResponse
{
    $settingId = $request->input('setting_id');
    $orgId = $request->input('organization_id');
    $statuses = $request->input('statuses', '');
    $days = $request->input('days', 1);  // New parameter
    return $this->proxyPost($request, "/iiko/deliveries?setting_id={$settingId}&organization_id={$orgId}&statuses=" . urlencode($statuses) . "&days={$days}");
}
```

#### File: `frontend/resources/views/admin/orders.blade.php`

**Changes:**
1. Added date range selector UI:
```html
<select class="form-input" id="orders-days-select" style="max-width:150px;">
    <option value="1" selected>1 день</option>
    <option value="2">2 дня</option>
    <option value="3">3 дня</option>
    <option value="7">7 дней</option>
</select>
```

2. Updated JavaScript to pass days parameter:
```javascript
const days = document.getElementById('orders-days-select').value || 1;
const result = await apiPost('/admin/api/iiko-deliveries', { 
    setting_id: settingId, 
    organization_id: orgId, 
    statuses: statuses,
    days: parseInt(days)  // Pass days to backend
});
```

#### File: `frontend/resources/views/admin/maintenance.blade.php`

**Changes:**
1. Added date range selector for deliveries section
2. Updated `loadIikoDeliveries()` to pass days parameter
3. Added helpful messages for empty data states:
```javascript
if (categories.length === 0) {
    html += '<div class="alert alert-warning">⚠️ Нет данных. Сначала выполните <strong>Полную синхронизацию</strong> на вкладке "Синхронизация".</div>';
}
```

4. Implemented correct Russian pluralization:
```javascript
// Handles: 1 день, 2-4 дня, 5+ дней, 11-14 дней
let daysWord = 'дней';
const lastDigit = days % 10;
const lastTwoDigits = days % 100;
if (lastTwoDigits >= 11 && lastTwoDigits <= 14) {
    daysWord = 'дней';
} else if (lastDigit === 1) {
    daysWord = 'день';
} else if (lastDigit >= 2 && lastDigit <= 4) {
    daysWord = 'дня';
}
```

## Testing & Validation

### Backend Tests
```bash
cd backend && python -m pytest tests/ -v
# Result: ✅ 42 tests passed
```

### Security Scan
```bash
codeql analyze
# Result: ✅ No security vulnerabilities found
```

### Code Review
- ✅ All review feedback addressed
- ✅ Input validation added
- ✅ Russian localization corrected
- ✅ Syntax validation passed

## User Impact

### Before Fixes
- ❌ Users seeing "Not Found" errors when viewing data
- ❌ "TOO_MANY_DATA_REQUESTED" errors blocking order retrieval
- ❌ No guidance on how to fix issues
- ❌ Inflexible 7-day date range

### After Fixes
- ✅ Clear messages guiding users to sync data first
- ✅ No "TOO_MANY_DATA_REQUESTED" errors with default settings
- ✅ Flexible date range selection (1-7 days)
- ✅ Proper validation preventing API overload
- ✅ Professional Russian localization

## Recommendations for Users

1. **Initial Setup:**
   - Configure API settings with valid organization_id
   - Run "Полная синхронизация" (Full Synchronization) first
   - Verify data appears in Categories, Products, Stop-lists

2. **Loading Orders:**
   - Start with 1-day range (default)
   - Increase to 2-3 days if needed
   - Avoid 7-day range unless necessary (may be slow)

3. **Loyalty Programs:**
   - Ensure organization_id is configured in API settings
   - Check that organization has loyalty programs enabled in iiko

## Files Modified

1. `backend/app/routes.py` - Endpoint changes and validation
2. `backend/app/iiko_service.py` - Configurable date ranges
3. `frontend/app/Http/Controllers/AdminController.php` - Parameter passing
4. `frontend/resources/views/admin/orders.blade.php` - UI and JavaScript
5. `frontend/resources/views/admin/maintenance.blade.php` - UI and JavaScript

## Deployment Notes

No special deployment steps required. Changes are backward compatible:
- Default behavior improved (1 day vs 7 days)
- Optional parameters added (don't break existing calls)
- Validation added (prevents errors, doesn't change success cases)

## Future Improvements (Optional)

1. Extract Russian pluralization into reusable helper function
2. Optimize SQL queries with organization_id index usage
3. Add pagination for large result sets
4. Cache frequently accessed data

---

**Status:** ✅ All issues resolved  
**Tests:** ✅ 42/42 passing  
**Security:** ✅ No vulnerabilities  
**Date:** 2026-02-10

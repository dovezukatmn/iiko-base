# Implementation: DELETE API Settings and Organization Loading Workflow

## Overview

This document describes the implementation of two features requested in the problem statement:
1. Fix DELETE method error when deleting iiko API settings
2. Verify and document the organization loading workflow

## Problem Statement (Russian)

**Issue 1:** При нажатии на кнопку удаления выскакивает ошибка:
```
⚠️ Ошибка при удалении: {"message":"The DELETE method is not supported for route admin/api/iiko-settings/4. Supported methods: PUT."}
```

**Issue 2:** Нужно реализовать:
- API ключ (apiLogin) - при вводе API ключ (apiLogin) рядом с полем ввода должна быть кнопка "загрузить организации"
- При нажатии на кнопку должно происходить выгрузка данных по доступным организациям и выгрузка Organization ID
- В список доступных организаций
- И только после выбора в списке нужной организации, при нажатии на кнопку сохранить - сохранялась настройка со всеми данными, iiko api login и id организации

## Implementation Details

### 1. Fixed DELETE Method Error

**Problem:** The DELETE route was missing from Laravel's routing configuration, causing the error "The DELETE method is not supported for route admin/api/iiko-settings/4".

**Solution:** Added three components:

#### a. DELETE Route (`frontend/routes/web.php`)
```php
Route::delete('/admin/api/iiko-settings/{id}', [AdminController::class, 'apiDeleteIikoSettings'])
    ->name('admin.api.iiko_settings.delete');
```

#### b. Controller Method (`frontend/app/Http/Controllers/AdminController.php`)
```php
public function apiDeleteIikoSettings(Request $request, int $id): JsonResponse
{
    return $this->proxyDelete($request, "/iiko/settings/{$id}");
}
```

#### c. Proxy Helper Method (`frontend/app/Http/Controllers/AdminController.php`)
```php
private function proxyDelete(Request $request, string $path): JsonResponse
{
    $token = $request->session()->get('token');
    try {
        $response = Http::withToken($token)->timeout(15)->delete("{$this->apiBase}{$path}");
        if ($response->status() === 401) {
            $detail = $response->json('detail') ?? '';
            if (str_contains($detail, 'Сессия') || str_contains($detail, 'токен')) {
                return response()->json(['error' => $detail, 'session_expired' => true], 401);
            }
        }
        return response()->json($response->json(), $response->status());
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Ошибка подключения к API: ' . $e->getMessage()], 502);
    }
}
```

**Backend Support:** The backend DELETE endpoint already exists at `/iiko/settings/{setting_id}` (backend/app/routes.py:265-277)

### 2. Organization Loading Workflow

**Status:** Already correctly implemented ✅

The organization loading workflow was already fully implemented in the codebase. Here's how it works:

#### UI Components (`frontend/resources/views/admin/maintenance.blade.php`)

1. **API Key Input Field** (line 134):
   ```html
   <input type="password" class="form-input" id="api-key-input" 
          placeholder="Введите ваш iiko API логин" autocomplete="new-password">
   ```

2. **Load Organizations Button** (line 153):
   ```html
   <button type="button" class="btn btn-sm" id="btn-load-orgs" 
           onclick="loadOrganizations()" 
           title="Загрузить организации по API ключу">🔄 Загрузить</button>
   ```

3. **Organization Select Dropdown** (line 150):
   ```html
   <select class="form-input" id="org-id-select" style="flex:1;">
       <option value="">— Не выбрано —</option>
   </select>
   ```

#### Workflow Steps

1. **User enters API key:** User types their iiko API login into the `api-key-input` field

2. **User clicks Load button:** Clicking "🔄 Загрузить" triggers `loadOrganizations()` function

3. **Organizations are fetched:** 
   - If editing existing settings: calls `/admin/api/iiko-organizations` with `setting_id`
   - If creating new settings: calls `/admin/api/iiko-organizations-by-key` with `api_key` and `api_url`

4. **Organizations populate dropdown:**
   ```javascript
   function populateOrgSelect(sel, orgs) {
       sel.innerHTML = '';
       // Add default option
       const defaultOpt = document.createElement('option');
       defaultOpt.value = '';
       defaultOpt.textContent = '— Не выбрано —';
       sel.appendChild(defaultOpt);
       
       // Add organization options
       orgs.forEach(org => {
           const opt = document.createElement('option');
           opt.value = org.id;  // Organization UUID
           opt.setAttribute('data-org-name', org.name);  // Store name in data attribute
           opt.textContent = org.name + ' (' + org.id.substring(0, 8) + '...)';
           sel.appendChild(opt);
       });
   }
   ```

5. **User selects organization:** User selects desired organization from dropdown

6. **User clicks Save:** Triggers `saveSettings()` function

7. **Settings are saved:**
   ```javascript
   async function saveSettings() {
       const orgIdFromSelect = document.getElementById('org-id-select').value;
       const orgIdFromInput = document.getElementById('org-id-input').value.trim();
       const orgId = orgIdFromSelect || orgIdFromInput;
       
       // Get organization name from data attribute
       let orgName = null;
       if (orgIdFromSelect) {
           const sel = document.getElementById('org-id-select');
           if (sel && sel.selectedIndex >= 0) {
               const selectedOption = sel.options[sel.selectedIndex];
               orgName = selectedOption ? selectedOption.getAttribute('data-org-name') : null;
           }
       }
       
       const body = {
           api_url: apiUrl || 'https://api-ru.iiko.services/api/1',
           organization_id: orgId || null,
           organization_name: orgName || null,
       };
       
       if (apiKey) {
           body.api_key = apiKey;
       }
       
       // POST or PUT to save settings
       if (currentSettingId) {
           result = await apiPut('/admin/api/iiko-settings/' + currentSettingId, body);
       } else {
           result = await apiPost('/admin/api/iiko-settings', body);
       }
   }
   ```

#### Backend Endpoints

1. **Load Organizations by API Key:** 
   - Endpoint: `POST /iiko/organizations-by-key`
   - Location: `backend/app/routes.py:392-420`
   - Purpose: Fetch organizations using temporary credentials without saving

2. **Load Organizations by Setting ID:**
   - Endpoint: `POST /iiko/organizations`
   - Location: `backend/app/routes.py:378-389`
   - Purpose: Fetch organizations using saved credentials

## Data Flow

```
User Input → Load Organizations → Fetch from iiko API → Populate Dropdown → User Selection → Save Settings
    ↓              ↓                      ↓                    ↓                  ↓               ↓
API Key      /organizations-by-key   iiko API Response   data-org-name    organization_id  Database
                                      (id + name)         attribute        + org_name
```

## Security Considerations

1. **API Key Protection:** API keys are stored in password fields and cleared after save for security
2. **Session Management:** All proxy methods include session expiry detection
3. **Authorization:** Backend endpoints require admin role authentication
4. **HTTPS Required:** API URL validation ensures HTTPS protocol

## Testing Recommendations

1. **Delete Functionality:**
   - Create a test iiko settings entry
   - Click the delete button (🗑️)
   - Confirm deletion in the dialog
   - Verify settings are removed from list and database

2. **Organization Loading:**
   - Enter a valid iiko API key
   - Click "🔄 Загрузить" button
   - Verify organizations load into dropdown
   - Select an organization
   - Click "💾 Сохранить"
   - Verify both organization_id and organization_name are saved

3. **Error Handling:**
   - Test with invalid API key
   - Test with network errors
   - Verify proper error messages are displayed

## Files Modified

1. `frontend/routes/web.php` - Added DELETE route
2. `frontend/app/Http/Controllers/AdminController.php` - Added DELETE proxy method and controller method

## Files Verified (No Changes Needed)

1. `frontend/resources/views/admin/maintenance.blade.php` - Organization workflow already implemented
2. `backend/app/routes.py` - Backend DELETE endpoint already exists

## Conclusion

Both issues from the problem statement have been addressed:
1. ✅ DELETE method error fixed by adding missing route and methods
2. ✅ Organization loading workflow verified and documented (already working correctly)

The implementation follows existing patterns in the codebase and maintains consistency with the established architecture.

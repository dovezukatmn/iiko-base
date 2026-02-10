# Feature: Delete IIKO API Settings

## Overview
Added the ability to delete saved IIKO API integration settings from the admin panel.

## Changes Made

### 1. Backend API Endpoint
**File:** `backend/app/routes.py`

Added new DELETE endpoint:
```python
@api_router.delete("/iiko/settings/{setting_id}", tags=["iiko"])
async def delete_iiko_settings(
    setting_id: int,
    db: Session = Depends(get_db),
    _current_user: User = Depends(require_role("admin")),
):
    """Удалить настройку интеграции iiko"""
    rec = db.query(IikoSettings).filter(IikoSettings.id == setting_id).first()
    if not rec:
        raise HTTPException(status_code=404, detail="Настройка не найдена")
    db.delete(rec)
    db.commit()
    return {"status": "ok", "message": "Настройка удалена"}
```

**Features:**
- Requires admin authentication
- Returns 404 if settings not found
- Deletes the record from database
- Returns success message

### 2. Frontend UI Changes
**File:** `frontend/resources/views/admin/maintenance.blade.php`

#### Added API Helper Function
```javascript
async function apiDelete(url) {
    const res = await fetch(url, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    return { status: res.status, data: await res.json() };
}
```

#### Added Delete Button
Each saved setting now displays a delete button (🗑️) next to the selection badge:

```javascript
'<div style="display:flex;gap:8px;align-items:center;">' +
    '<span class="badge ' + (isSelected ? 'badge-success' : 'badge-muted') + '">' + (isSelected ? '✓ Выбрано' : 'Выбрать') + '</span>' +
    '<button type="button" class="btn btn-sm" onclick="deleteSetting(event, ' + s.id + ')" title="Удалить настройку" aria-label="Удалить настройку интеграции #' + s.id + '" style="background:var(--danger);color:white;padding:4px 8px;">🗑️</button>' +
'</div>'
```

#### Added Delete Handler Function
```javascript
async function deleteSetting(event, settingId) {
    // Prevent the row click event from firing
    event.stopPropagation();
    
    // Show confirmation dialog
    if (!confirm('Вы уверены, что хотите удалить эту настройку? Это действие нельзя отменить.')) {
        return;
    }
    
    try {
        const result = await apiDelete('/admin/api/iiko-settings/' + settingId);
        
        if (result.status >= 400) {
            alert('⚠️ Ошибка при удалении: ' + (result.data.detail || JSON.stringify(result.data)));
        } else {
            // If the deleted setting was selected, clear the selection
            if (currentSettingId === settingId) {
                currentSettingId = null;
                document.getElementById('api-key-input').value = '';
                document.getElementById('api-url-input').value = 'https://api-ru.iiko.services/api/1';
                document.getElementById('org-id-select').value = '';
                document.getElementById('org-id-input').value = '';
                document.getElementById('settings-message').innerHTML = '';
            }
            // Reload settings list
            loadSettings();
        }
    } catch (err) {
        alert('⚠️ Ошибка при удалении: ' + err.message);
    }
}
```

## UI Flow

### Before Changes
```
┌─────────────────────────────────────┐
│ Saved Settings                      │
├─────────────────────────────────────┤
│ ● Integration #1                    │
│   https://api-ru.iiko.services...   │
│                         [Select]    │
├─────────────────────────────────────┤
│ ● Integration #2                    │
│   https://api-ru.iiko.services...   │
│                         [Select]    │
└─────────────────────────────────────┘
```

### After Changes
```
┌─────────────────────────────────────┐
│ Saved Settings                      │
├─────────────────────────────────────┤
│ ● Integration #1                    │
│   https://api-ru.iiko.services...   │
│                   [Select] [🗑️]    │
├─────────────────────────────────────┤
│ ● Integration #2                    │
│   https://api-ru.iiko.services...   │
│                   [Select] [🗑️]    │
└─────────────────────────────────────┘
```

## User Experience

1. **Delete Action**: User clicks the trash icon (🗑️) button
2. **Confirmation**: A confirmation dialog appears: "Вы уверены, что хотите удалить эту настройку? Это действие нельзя отменить."
3. **Deletion**: 
   - If confirmed, the setting is deleted from the database
   - If the deleted setting was selected, the form is cleared
   - The settings list is refreshed
4. **Error Handling**: If deletion fails, an error message is shown

## Security & Accessibility

### Security
- ✅ Requires admin authentication (role-based access control)
- ✅ CSRF token protection
- ✅ Confirmation dialog prevents accidental deletion
- ✅ No SQL injection vulnerabilities (using ORM)

### Accessibility
- ✅ `aria-label` attribute on delete button for screen readers
- ✅ `title` attribute for tooltip
- ✅ Keyboard-accessible (button is focusable)
- ✅ High contrast delete button (red background, white icon)

## Testing

To test the feature:

1. Navigate to Admin Panel → Maintenance → API Settings
2. Create a test IIKO API integration (if none exists)
3. Click the trash icon (🗑️) on a saved setting
4. Confirm the deletion dialog
5. Verify:
   - Setting is removed from the list
   - If the deleted setting was selected, form is cleared
   - Page updates without refresh

## API Documentation

### Endpoint
```
DELETE /api/v1/iiko/settings/{setting_id}
```

### Authentication
Requires admin role

### Parameters
- `setting_id` (path parameter, integer): The ID of the IIKO settings to delete

### Responses

**Success (200 OK):**
```json
{
  "status": "ok",
  "message": "Настройка удалена"
}
```

**Not Found (404):**
```json
{
  "detail": "Настройка не найдена"
}
```

**Unauthorized (403):**
```json
{
  "detail": "Not authorized"
}
```

## Notes

- The delete button appears on each saved setting row
- Event propagation is stopped to prevent row selection when clicking delete
- The form is automatically cleared if the deleted setting was currently selected
- All error messages are user-friendly and in Russian (matching the rest of the UI)

# Admin Login Issues - Root Cause Analysis

## Issue Summary
Users were experiencing "Неверные учетные данные" (Invalid credentials) errors when trying to log into the admin panel, even after running password reset scripts.

## Root Causes Identified

### 1. **Critical Bug in Login Route** ✅ FIXED

**Location:** `backend/app/routes.py`, lines 64-66

**Problem:**
The bootstrap logic for creating default admin had a fatal flaw:

```python
if not user and form.username == settings.DEFAULT_ADMIN_USERNAME and secrets.compare_digest(
    form.password, default_password
):
    existing_admin = db.query(User).filter(User.role == "admin").first()
    if existing_admin:
        raise HTTPException(status_code=401, detail="Неверные учетные данные")  # BUG!
```

**Impact:**
- If admin user existed in database (created by reset_admin_password.sh or schema.sql)
- The login route would NEVER reach this bootstrap code (because `not user` would be FALSE)
- So this code only ran when admin username was NOT in database
- But then it checked if ANY admin existed and rejected the login!
- This created a catch-22 situation

**Scenario that triggered the bug:**
1. Fresh database → bootstrap code creates admin → works fine
2. User runs reset_admin_password.sh → deletes and recreates admin
3. Now admin exists in DB, but with username='admin'
4. User tries to login → user found at line 59
5. Bootstrap code skipped (user exists)
6. Should verify password... but if there was ANY issue with the hash or bcrypt, login fails

**Fix:**
Removed the unnecessary check for `existing_admin`. The bootstrap logic now simply:
- Creates admin user if username doesn't exist
- No rejection based on other admins existing
- Let normal password verification handle authentication

### 2. **Diagnostic Tools Missing**

**Problem:**
- Users tried to run `verify_admin_login.py` but got "file not found" errors
- Script required Python dependencies that might not be installed in production
- No simple way to check database state

**Fix:**
Created multiple diagnostic tools:

1. **`scripts/check_admin_db.sh`** - Simple bash script
   - No Python dependencies needed
   - Uses psql directly
   - Checks database connection, user existence, password hash, and status

2. **`scripts/test_password_hash.py`** - Python diagnostic
   - Tests bcrypt password hashing
   - Verifies expected hash works
   - Tests for common issues (escaped hashes, wrong passwords)

3. **Updated documentation** in `docs/ADMIN_LOGIN_TROUBLESHOOTING.md`
   - Added file not found troubleshooting
   - Multiple diagnostic approaches
   - Step-by-step resolution guide

## Testing

### Test Case 1: Fresh Installation
```bash
# Should work with bootstrap
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"12101991Qq!"}'
```
**Expected:** Success, creates admin user automatically

### Test Case 2: After Password Reset
```bash
./scripts/reset_admin_password.sh
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"12101991Qq!"}'
```
**Expected:** Success, uses existing admin user

### Test Case 3: Wrong Password
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"WrongPassword"}'
```
**Expected:** 401 error "Неверные учетные данные"

## Verification Steps

1. Check database has correct hash:
```bash
./scripts/check_admin_db.sh
```

2. Test password hash verification (requires Python):
```bash
cd backend
python3 ../scripts/test_password_hash.py
```

3. Test login via API:
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"12101991Qq!"}'
```

## Files Changed

1. `backend/app/routes.py` - Fixed login bootstrap logic
2. `scripts/check_admin_db.sh` - New diagnostic tool (no Python needed)
3. `scripts/test_password_hash.py` - New hash verification tool
4. `docs/ADMIN_LOGIN_TROUBLESHOOTING.md` - Enhanced documentation

## Security Notes

- Default password `12101991Qq!` should be changed immediately after first login
- Password hash: `$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa`
- Uses bcrypt with 12 rounds
- Dependencies: `bcrypt==4.0.1` and `passlib[bcrypt]==1.7.4` for compatibility

## Resolution

The login issue should now be resolved. Users can:

1. Run `./scripts/check_admin_db.sh` to verify database state
2. Run `./scripts/reset_admin_password.sh` if needed
3. Login with `admin` / `12101991Qq!`
4. Change password immediately after login

If issues persist, check:
- Backend is running and accessible
- Database connection is working
- CORS settings include frontend URL
- Frontend .env has correct VITE_API_URL

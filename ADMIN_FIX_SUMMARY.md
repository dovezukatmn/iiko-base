# Admin Authentication Fix - Summary

## Problem
Users were unable to login to the admin panel with credentials `admin` / `12101991Qq!`, receiving "Неверные учетные данные" (Invalid credentials) error.

## Root Causes
1. **Missing Admin User**: The database initialization did not automatically create an admin user, relying instead on the bootstrap logic in the application code which only works when NO admin users exist in the database.

2. **Bcrypt Compatibility**: There was a version incompatibility between `passlib 1.7.4` and `bcrypt 5.0.0`, causing authentication to fail even when credentials were correct.

## Solutions Implemented

### 1. Database Schema Update (`database/schema.sql`)
- Added automatic admin user creation during database initialization
- Fixed column ordering: `role` column is now added BEFORE the admin user INSERT
- Used `ON CONFLICT DO NOTHING` to prevent accidental password resets on subsequent schema runs
- Admin user is created with:
  - Username: `admin`
  - Email: `admin@example.com`
  - Password: `12101991Qq!` (bcrypt hashed)
  - Role: `admin`
  - Superuser privileges enabled

### 2. Dependency Fix (`backend/requirements.txt`)
- Pinned `bcrypt` to version `4.0.1` for compatibility with `passlib 1.7.4`
- This resolves the "password cannot be longer than 72 bytes" error

### 3. Password Reset Tools
Created multiple ways to reset admin password:

#### a. SQL Script (`database/reset_admin.sql`)
- Can be run manually via psql
- Deletes existing admin user and creates new one with default credentials

#### b. Shell Script (`scripts/reset_admin_password.sh`)
- Automated bash script for quick password reset
- Includes helpful success/failure messages
- Shows login credentials after successful reset

#### c. Documentation (`docs/admin_password_reset.md`)
- Comprehensive guide in Russian
- Multiple reset methods documented
- Security warnings prominently displayed

## Security Enhancements
1. **ON CONFLICT DO NOTHING**: Prevents accidental password resets during schema updates
2. **Removed plaintext passwords** from SQL file comments
3. **Added security warnings** in all documentation and scripts
4. **Documented best practice**: Users should change default password immediately after login

## Testing Results
All authentication features tested successfully:
- ✅ Login with default credentials works
- ✅ JWT token generation and validation works
- ✅ Password change functionality works
- ✅ Password reset scripts work correctly
- ✅ ON CONFLICT DO NOTHING prevents password overwrites (when tables aren't dropped)

## How to Use

### For Fresh Installation
No action needed - admin user is created automatically with default credentials.

### For Existing Installation
Run one of these methods:

**Method 1 - Shell Script:**
```bash
./scripts/reset_admin_password.sh
```

**Method 2 - SQL File:**
```bash
psql -h localhost -U iiko_user -d iiko_db -f database/reset_admin.sql
```

**Method 3 - Docker:**
```bash
docker exec -it iiko-postgres psql -U iiko_user -d iiko_db -f /path/to/reset_admin.sql
```

**Method 4 - Complete Database Reset:**
```bash
docker-compose down -v
docker-compose up -d
```

### After Login
**IMPORTANT**: Change the default password immediately via the admin interface!

## Files Modified
1. `backend/requirements.txt` - Fixed bcrypt version
2. `database/schema.sql` - Added automatic admin user creation
3. `database/reset_admin.sql` - New password reset script
4. `scripts/reset_admin_password.sh` - New reset automation script
5. `docs/admin_password_reset.md` - New comprehensive documentation

## Default Credentials
- **Username**: `admin`
- **Password**: `12101991Qq!`

⚠️ **Change this password immediately after your first login!**

# Quick Fix Guide: Admin Login Not Working

## Problem
You're getting "Неверные учетные данные" (Invalid credentials) error when trying to login as admin.

## Quick Solution

### Step 1: Check Database (30 seconds)
```bash
cd /var/www/iiko-base  # or your project directory
./scripts/check_admin_db.sh
```

### Step 2: Reset Password if Needed (30 seconds)
```bash
./scripts/reset_admin_password.sh
```

### Step 3: Login
- **Username:** `admin`
- **Password:** `12101991Qq!`

⚠️ **Change this password immediately after login!**

---

## If That Doesn't Work

### Check 1: Is Backend Running?
```bash
# If using systemd:
sudo systemctl status iiko-backend

# If using Docker:
docker-compose ps
```

If not running, start it:
```bash
# Systemd:
sudo systemctl start iiko-backend

# Docker:
docker-compose up -d
```

### Check 2: Test API Directly
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"12101991Qq!"}'
```

**If this works but frontend doesn't:**
- Check frontend `.env` file has correct `VITE_API_URL`
- Check CORS settings in `backend/config/settings.py`

**If this fails:**
- Check backend logs: `sudo journalctl -u iiko-backend -n 50`
- Or Docker logs: `docker-compose logs backend`

### Check 3: Verify Hash in Database
```bash
PGPASSWORD="12101991Qq!" psql -h localhost -U iiko_user -d iiko_db -c \
  "SELECT username, LEFT(hashed_password, 20) as hash_start FROM users WHERE username = 'admin';"
```

Should show hash starting with `$2b$12$` (NOT `\$2b\$12\$`).

If hash starts with backslash, run:
```bash
./scripts/reset_admin_password.sh
```

---

## Common Issues

### Issue: "File not found" when running scripts
```
python3: can't open file '/var/www/iiko-base/scripts/verify_admin_login.py'
```

**Solution:** You're in wrong directory or code not deployed.
```bash
cd /var/www/iiko-base  # or your actual project directory
ls scripts/  # verify files exist
git pull origin main  # update code if needed
```

Use the simpler check script that doesn't need Python:
```bash
./scripts/check_admin_db.sh
```

### Issue: Password reset script fails
```
psql: error: connection to server on socket "/var/run/postgresql/.s.PGSQL.5432" failed
```

**Solution:** PostgreSQL not running
```bash
sudo systemctl start postgresql
sudo systemctl status postgresql
```

### Issue: Hash in database looks wrong
Hash should look like:
```
$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa
```

NOT like:
```
\$2b\$12\$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa
```

If you see backslashes, the hash was incorrectly escaped. Run:
```bash
./scripts/reset_admin_password.sh
```

---

## Nuclear Option: Complete Reset

If nothing works:

### Docker:
```bash
docker-compose down -v  # WARNING: Deletes all data!
docker-compose up -d
```

### Without Docker:
```bash
# Stop backend
sudo systemctl stop iiko-backend

# Recreate database
sudo -u postgres psql << EOF
DROP DATABASE IF EXISTS iiko_db;
CREATE DATABASE iiko_db;
GRANT ALL PRIVILEGES ON DATABASE iiko_db TO iiko_user;
EOF

# Create schema and admin
psql -h localhost -U iiko_user -d iiko_db -f database/schema.sql

# Start backend
sudo systemctl start iiko-backend

# Verify
./scripts/check_admin_db.sh
```

---

## Diagnostic Tools

### Simple Database Check (No Python needed)
```bash
./scripts/check_admin_db.sh
```
Shows: connection status, user exists, hash correct, user active

### Python Verification (Requires dependencies)
```bash
python3 scripts/verify_admin_login.py
```
Full backend integration test

### Hash Testing
```bash
cd backend
python3 ../scripts/test_password_hash.py
```
Tests bcrypt hashing/verification

### Login Logic Testing
```bash
python3 scripts/test_login_logic.py
```
Simulates login scenarios

---

## Getting Help

If still stuck, gather diagnostics:

```bash
# Run all checks
./scripts/check_admin_db.sh > diagnostics.txt
sudo journalctl -u iiko-backend -n 100 >> diagnostics.txt

# Or for Docker:
docker-compose logs backend --tail=100 >> diagnostics.txt
```

Then create an issue on GitHub with:
- The diagnostics.txt file
- Description of what you tried
- Your environment (Docker/systemd, OS version)

---

## Default Credentials

**Username:** `admin`  
**Password:** `12101991Qq!`

The password hash for this is:
```
$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa
```

This should match exactly what's in the database.

---

## Why This Happened

The issue was caused by a bug in the login route logic that would incorrectly reject valid admin credentials in certain scenarios. This has been fixed in the latest version of the code.

**What was fixed:**
1. Login route bootstrap logic (removed faulty admin existence check)
2. Added better diagnostic tools
3. Improved documentation

**To get the fix:**
```bash
git pull origin main
sudo systemctl restart iiko-backend  # or docker-compose restart backend
```

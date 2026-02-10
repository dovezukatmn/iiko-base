# Scripts Directory

This directory contains utility scripts for managing the iiko-base application.

## Admin Login & Password Management

### `check_admin_db.sh` ⭐ RECOMMENDED
**Purpose:** Check admin user status in database  
**Requirements:** psql command-line tool  
**Usage:**
```bash
./scripts/check_admin_db.sh
```

**What it does:**
- ✅ Verifies database connection
- ✅ Checks if admin user exists
- ✅ Compares password hash with expected value
- ✅ Shows user status (active/inactive, superuser)
- ✅ Provides troubleshooting guidance

**When to use:** First thing to run when having login issues.

---

### `reset_admin_password.sh`
**Purpose:** Reset admin password to default  
**Requirements:** psql  
**Usage:**
```bash
./scripts/reset_admin_password.sh
```

**What it does:**
- Deletes existing admin user
- Creates new admin user with default credentials
- Username: `admin`
- Password: `12101991Qq!`

⚠️ **Security:** Change password immediately after using this script!

---

### `verify_admin_login.py`
**Purpose:** Comprehensive admin login verification  
**Requirements:** Python 3, backend dependencies installed  
**Usage:**
```bash
python3 scripts/verify_admin_login.py
```

**What it does:**
- Checks configuration
- Tests database connection
- Verifies admin user exists
- Tests password hash verification
- Provides detailed troubleshooting

**Note:** If you get "file not found" or import errors, use `check_admin_db.sh` instead.

---

### `test_password_hash.py`
**Purpose:** Test bcrypt password hashing  
**Requirements:** Python 3, passlib, bcrypt  
**Usage:**
```bash
cd backend
python3 ../scripts/test_password_hash.py
```

**What it does:**
- Tests password verification with expected hash
- Generates new hash and verifies it
- Tests wrong password rejection
- Detects escaped hash issues

---

### `test_login_logic.py`
**Purpose:** Test login route logic  
**Requirements:** Python 3, backend dependencies  
**Usage:**
```bash
python3 scripts/test_login_logic.py
```

**What it does:**
- Simulates different login scenarios
- Tests bootstrap logic
- Verifies password verification works

---

## Deployment Scripts

### `install.sh`
**Purpose:** Initial system installation  
**Usage:**
```bash
sudo ./scripts/install.sh
```

Installs all required system packages and dependencies.

---

### `setup.sh`
**Purpose:** Configure environment  
**Usage:**
```bash
./scripts/setup.sh
```

Sets up configuration files and environment variables.

---

### `deploy.sh`
**Purpose:** Deploy application  
**Usage:**
```bash
sudo ./scripts/deploy.sh
```

Deploys the application and starts all services.

---

### `check.sh`
**Purpose:** Verify installation  
**Usage:**
```bash
./scripts/check.sh
```

Checks that all required files and directories exist.

---

### `verify-deployment.sh`
**Purpose:** Comprehensive deployment verification  
**Usage:**
```bash
bash scripts/verify-deployment.sh
```

Checks versions, configurations, and readiness to run.

---

## Backup & Restore

### `backup.sh`
**Purpose:** Backup database and files  
**Usage:**
```bash
./scripts/backup.sh
```

Creates timestamped backups.

---

### `restore.sh`
**Purpose:** Restore from backup  
**Usage:**
```bash
./scripts/restore.sh <backup_file>
```

---

## Database Management

### `migrate_db.sh`
**Purpose:** Run database migrations  
**Usage:**
```bash
./scripts/migrate_db.sh
```

---

## Troubleshooting Quick Reference

### Problem: Can't login as admin
1. Run `./scripts/check_admin_db.sh`
2. If password mismatch, run `./scripts/reset_admin_password.sh`
3. Login with `admin` / `12101991Qq!`
4. Change password immediately

### Problem: "File not found" for verify_admin_login.py
- Use `./scripts/check_admin_db.sh` instead (no Python needed)
- Or check you're in correct directory: `cd /var/www/iiko-base`

### Problem: Reset script fails
- Check PostgreSQL is running: `sudo systemctl status postgresql`
- Check credentials in the script match your database

---

## Security Notes

**Default Credentials:**
- Username: `admin`
- Password: `12101991Qq!`
- Hash: `$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa`

⚠️ **IMPORTANT:** Always change the default password immediately after first login!

---

## Need Help?

See documentation:
- [Admin Login Troubleshooting](../docs/ADMIN_LOGIN_TROUBLESHOOTING.md)
- [Quick Fix Guide](../QUICK_ADMIN_LOGIN_FIX.md)
- [Root Cause Analysis](../docs/ADMIN_LOGIN_ROOT_CAUSE.md)

# 🚨 QUICK FIX: ADMIN LOGIN NOT WORKING

## Getting "Invalid credentials" error? Run these 3 commands:

### 1️⃣ Auto-diagnose and fix (30 seconds)
```bash
cd /var/www/iiko-base
./scripts/diagnose_and_fix_admin.sh
```

### 2️⃣ Restart backend
```bash
# For systemd:
sudo systemctl restart iiko-backend

# For Docker:
docker-compose restart backend
```

### 3️⃣ Login to admin panel
- **Username:** `admin`
- **Password:** `12101991Qq!`

⚠️ **Change password immediately after login!**

---

## 🔍 What diagnose_and_fix_admin.sh does:

✅ Checks PostgreSQL is running  
✅ Tests database connection  
✅ Verifies users table exists  
✅ **Fixes missing role column** (most common issue!)  
✅ Creates admin user if missing  
✅ Activates admin user  
✅ Fixes password hash if incorrect  
✅ Checks backend service status  
✅ Tests API directly  
✅ Validates .env configuration  

**The script automatically fixes all detected issues!**

---

## 📊 If script shows errors:

### ❌ PostgreSQL not running
```bash
sudo systemctl start postgresql
```

### ❌ Backend not running
```bash
# Systemd:
sudo systemctl start iiko-backend

# Docker:
docker-compose up -d backend
```

### ❌ Missing .env file
```bash
# Backend:
cp backend/.env.example backend/.env

# Frontend:
cp frontend/.env.example frontend/.env
```

Edit the files and set correct parameters.

---

## 🆘 Still not working? Full documentation:

📖 **[ПОЛНОЕ_РЕШЕНИЕ_ВХОДА_В_АДМИНКУ.md](ПОЛНОЕ_РЕШЕНИЕ_ВХОДА_В_АДМИНКУ.md)** (Russian)

Contains:
- All 10 possible causes
- Detailed diagnostics for each
- Step-by-step solutions
- How to collect logs for debugging

---

## 🧪 Additional checks:

### Manually check database:
```bash
./scripts/check_admin_db.sh
```

### Test API directly:
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"12101991Qq!"}'
```

Should return:
```json
{"access_token": "eyJ...", "token_type": "bearer"}
```

### View backend logs:
```bash
# Systemd:
sudo journalctl -u iiko-backend -n 50

# Docker:
docker-compose logs backend --tail=50
```

---

## 💡 Common issues:

| Issue | Solution |
|-------|----------|
| Missing `role` column | `./scripts/diagnose_and_fix_admin.sh` fixes automatically |
| No admin user | `./scripts/diagnose_and_fix_admin.sh` creates it |
| Wrong password hash | `./scripts/diagnose_and_fix_admin.sh` fixes it |
| Backend not running | `sudo systemctl start iiko-backend` |
| PostgreSQL not running | `sudo systemctl start postgresql` |
| Incorrect .env | Copy from .env.example and configure |

---

## ⚙️ Default credentials:

**Username:** `admin`  
**Password:** `12101991Qq!`  
**Email:** `admin@example.com`  

**Expected password hash in database:**
```
$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa
```

⚠️ **If hash starts with `\$` instead of `$` - that's a bug!**  
Script will fix automatically.

---

## 📞 Need help?

1. Run diagnostics and save output:
   ```bash
   ./scripts/diagnose_and_fix_admin.sh > diagnostics.txt 2>&1
   ```

2. Collect logs:
   ```bash
   sudo journalctl -u iiko-backend -n 100 > backend.log
   ```

3. Create GitHub issue with diagnostics.txt and backend.log files

---

**Version:** 1.0  
**Date:** 2026-02-09  
**Author:** dovezukatmn

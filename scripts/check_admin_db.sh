#!/bin/bash
# Simple script to check admin user in database
# This script connects directly to PostgreSQL and checks the admin user
#
# SECURITY NOTE: This script uses default credentials for diagnostic purposes.
# These are the same default credentials documented throughout the project.
# Users should change the default password immediately after first login.

echo "========================================"
echo "   Admin User Database Check"
echo "========================================"
echo ""

# Database credentials (default values - should match your installation)
DB_HOST="localhost"
DB_USER="iiko_user"
DB_NAME="iiko_db"
DB_PASSWORD="12101991Qq!"

# Expected hash for password 12101991Qq!
# This is the documented default password that should be changed after first login
EXPECTED_HASH='$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa'

echo "1. Checking database connection..."
if PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "\q" 2>/dev/null; then
    echo "   ✓ Database connection successful"
else
    echo "   ✗ Cannot connect to database"
    echo "   Please check:"
    echo "     - PostgreSQL is running: sudo systemctl status postgresql"
    echo "     - Database exists and credentials are correct"
    exit 1
fi

echo ""
echo "2. Checking if admin user exists..."
ADMIN_EXISTS=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT COUNT(*) FROM users WHERE username = 'admin';")

if [ "$ADMIN_EXISTS" -eq "0" ]; then
    echo "   ✗ Admin user does NOT exist in database"
    echo ""
    echo "   To create admin user, run:"
    echo "     ./scripts/reset_admin_password.sh"
    echo "   Or:"
    echo "     psql -h localhost -U iiko_user -d iiko_db -f database/reset_admin.sql"
    exit 1
else
    echo "   ✓ Admin user exists"
fi

echo ""
echo "3. Retrieving admin user details..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "
SELECT 
    id,
    username,
    email,
    role,
    is_active,
    is_superuser,
    LEFT(hashed_password, 30) || '...' as password_hash_preview
FROM users 
WHERE username = 'admin';
"

echo ""
echo "4. Checking password hash..."
CURRENT_HASH=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT hashed_password FROM users WHERE username = 'admin';")

if [ "$CURRENT_HASH" = "$EXPECTED_HASH" ]; then
    echo "   ✓ Password hash MATCHES expected value"
    echo "   Login should work with:"
    echo "     Username: admin"
    echo "     Password: 12101991Qq!"
else
    echo "   ✗ Password hash does NOT match expected value"
    echo ""
    echo "   Expected hash: $EXPECTED_HASH"
    echo "   Current hash:  $CURRENT_HASH"
    echo ""
    echo "   To fix this, run:"
    echo "     ./scripts/reset_admin_password.sh"
fi

echo ""
echo "5. Checking admin user status..."
IS_ACTIVE=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT is_active FROM users WHERE username = 'admin';")
IS_SUPERUSER=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc "SELECT is_superuser FROM users WHERE username = 'admin';")

if [ "$IS_ACTIVE" = "t" ]; then
    echo "   ✓ Admin user is active"
else
    echo "   ✗ Admin user is INACTIVE"
    echo "   To activate, run:"
    echo "     PGPASSWORD='12101991Qq!' psql -h localhost -U iiko_user -d iiko_db -c \"UPDATE users SET is_active = TRUE WHERE username = 'admin';\""
fi

if [ "$IS_SUPERUSER" = "t" ]; then
    echo "   ✓ Admin user has superuser privileges"
else
    echo "   ⚠ Admin user does NOT have superuser privileges"
fi

echo ""
echo "========================================"
echo "   Summary"
echo "========================================"

if [ "$CURRENT_HASH" = "$EXPECTED_HASH" ] && [ "$IS_ACTIVE" = "t" ]; then
    echo "✓ All checks passed!"
    echo ""
    echo "You should be able to login with:"
    echo "  Username: admin"
    echo "  Password: 12101991Qq!"
    echo ""
    echo "If login still fails, check:"
    echo "  1. Backend is running: sudo systemctl status iiko-backend"
    echo "  2. Backend logs: journalctl -u iiko-backend -n 50"
    echo "  3. Frontend .env has correct VITE_API_URL"
    echo "  4. No firewall/proxy blocking requests"
else
    echo "✗ Issues found - see details above"
    echo ""
    echo "Recommended action: Run password reset"
    echo "  ./scripts/reset_admin_password.sh"
fi
echo ""

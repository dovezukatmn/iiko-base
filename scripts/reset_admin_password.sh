#!/bin/bash
# Script to reset admin password to default (12101991Qq!)
# This connects to the PostgreSQL database and resets the admin user

echo "Resetting admin password to default (12101991Qq!)..."

# Use single quotes for the SQL to avoid bash escaping issues
# The hash should be passed as-is without any escaping
PGPASSWORD="12101991Qq!" psql -h localhost -U iiko_user -d iiko_db << 'SQL'
DELETE FROM users WHERE username = 'admin';
INSERT INTO users (email, username, hashed_password, role, is_active, is_superuser)
VALUES (
    'admin@example.com',
    'admin',
    '$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa',
    'admin',
    TRUE,
    TRUE
);
SQL

if [ $? -eq 0 ]; then
    echo "✓ Admin password reset successfully!"
    echo "You can now login with:"
    echo "  Username: admin"
    echo "  Password: 12101991Qq!"
else
    echo "✗ Failed to reset password. Make sure PostgreSQL is running and accessible."
    echo ""
    echo "Alternative: Run the SQL file manually:"
    echo "  psql -h localhost -U iiko_user -d iiko_db -f database/reset_admin.sql"
fi

#!/bin/bash
# Comprehensive Admin Login Diagnostic and Fix Script
# Проверяет и исправляет все возможные проблемы с входом в админку
#
# SECURITY NOTE:
# This script uses default credentials for convenience in development/testing.
# For production use, always set secure passwords via environment variables:
#   export DB_PASSWORD="your_secure_db_password"
#   export ADMIN_PASSWORD="your_secure_admin_password"
#   ./diagnose_and_fix_admin.sh
#
# The script uses psql parameterized queries to prevent SQL injection.

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration - можно переопределить через переменные окружения
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-iiko_db}"
DB_USER="${DB_USER:-iiko_user}"
DB_PASSWORD="${DB_PASSWORD:-12101991Qq!}"
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-12101991Qq!}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"

# Correct hash for password "12101991Qq!"
EXPECTED_HASH='$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa'

# Escape single quotes in variables for safe use in psql \set commands
# Replace ' with '' (SQL standard escaping)
ADMIN_USERNAME_ESCAPED="${ADMIN_USERNAME//\'/\'\'}"
ADMIN_EMAIL_ESCAPED="${ADMIN_EMAIL//\'/\'\'}"
# No escaping needed for EXPECTED_HASH as it's within single quotes in psql

# Input validation for security
# Validate DB_USER contains only alphanumeric and underscore
if ! [[ "$DB_USER" =~ ^[a-zA-Z0-9_]+$ ]]; then
    echo -e "${RED}✗ Invalid DB_USER: must contain only letters, numbers, and underscores${NC}"
    exit 1
fi
# Validate DB_NAME contains only alphanumeric and underscore
if ! [[ "$DB_NAME" =~ ^[a-zA-Z0-9_]+$ ]]; then
    echo -e "${RED}✗ Invalid DB_NAME: must contain only letters, numbers, and underscores${NC}"
    exit 1
fi

# Security warning if using default passwords
if [ "$DB_PASSWORD" = "12101991Qq!" ] || [ "$ADMIN_PASSWORD" = "12101991Qq!" ]; then
    echo -e "${RED}⚠️  WARNING: Using default passwords!${NC}"
    echo -e "${RED}   For production, set secure passwords via environment variables:${NC}"
    echo -e "${RED}   export DB_PASSWORD='your_secure_password'${NC}"
    echo -e "${RED}   export ADMIN_PASSWORD='your_secure_password'${NC}"
    echo ""
fi

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Admin Login Comprehensive Diagnostics${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Error counter
ERRORS=0
FIXES_APPLIED=0

# Test 1: Check PostgreSQL is running
echo -e "${YELLOW}[1/10] Checking PostgreSQL service...${NC}"
if systemctl is-active --quiet postgresql 2>/dev/null || pgrep -x postgres > /dev/null; then
    echo -e "${GREEN}✓ PostgreSQL is running${NC}"
else
    echo -e "${RED}✗ PostgreSQL is NOT running${NC}"
    echo "  Attempting to start PostgreSQL..."
    
    if sudo systemctl start postgresql 2>/dev/null; then
        # Wait a moment for PostgreSQL to fully start
        sleep 2
        if systemctl is-active --quiet postgresql 2>/dev/null || pgrep -x postgres > /dev/null; then
            echo -e "${GREEN}✓ PostgreSQL started successfully${NC}"
            ((FIXES_APPLIED++))
        else
            echo -e "${RED}✗ Failed to start PostgreSQL${NC}"
            echo "  Manual fix: sudo systemctl start postgresql"
            ((ERRORS++))
            exit 1
        fi
    else
        echo -e "${RED}✗ Failed to start PostgreSQL (need sudo)${NC}"
        echo "  Manual fix: sudo systemctl start postgresql"
        ((ERRORS++))
        exit 1
    fi
fi
echo ""

# Test 2: Check database connection
echo -e "${YELLOW}[2/10] Testing database connection...${NC}"
# Temporarily disable set -e for this test
set +e
DB_CONNECT_TEST=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1" 2>&1)
DB_CONNECT_RESULT=$?
set -e

if [ $DB_CONNECT_RESULT -eq 0 ]; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Cannot connect to database${NC}"
    echo "  Database: $DB_NAME"
    echo "  User: $DB_USER"
    echo "  Host: $DB_HOST"
    echo "  Attempting to create database user and database..."
    
    # Try to create the database user
    set +e
    USER_EXISTS=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" 2>/dev/null)
    set -e
    if [ "$USER_EXISTS" != "1" ]; then
        echo "  Creating database user: $DB_USER"
        set +e
        sudo -u postgres psql <<EOF > /dev/null 2>&1
CREATE USER $DB_USER WITH PASSWORD '$DB_PASSWORD';
ALTER USER $DB_USER CREATEDB;
EOF
        CREATE_USER_RESULT=$?
        set -e
        if [ $CREATE_USER_RESULT -eq 0 ]; then
            echo -e "${GREEN}✓ Database user created${NC}"
            ((FIXES_APPLIED++))
        else
            echo -e "${RED}✗ Failed to create database user${NC}"
            ((ERRORS++))
            exit 1
        fi
    else
        echo "  Database user already exists"
    fi
    
    # Try to create the database
    set +e
    DB_EXISTS=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" 2>/dev/null)
    set -e
    if [ "$DB_EXISTS" != "1" ]; then
        echo "  Creating database: $DB_NAME"
        set +e
        sudo -u postgres psql <<EOF > /dev/null 2>&1
CREATE DATABASE $DB_NAME OWNER $DB_USER;
GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
EOF
        CREATE_DB_RESULT=$?
        set -e
        if [ $CREATE_DB_RESULT -eq 0 ]; then
            echo -e "${GREEN}✓ Database created${NC}"
            ((FIXES_APPLIED++))
        else
            echo -e "${RED}✗ Failed to create database${NC}"
            ((ERRORS++))
            exit 1
        fi
    else
        echo "  Database already exists"
    fi
    
    # Test connection again
    set +e
    PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1" > /dev/null 2>&1
    RETEST_RESULT=$?
    set -e
    if [ $RETEST_RESULT -eq 0 ]; then
        echo -e "${GREEN}✓ Database connection successful after fixes${NC}"
    else
        echo -e "${RED}✗ Still cannot connect to database${NC}"
        echo "  Manual intervention required"
        ((ERRORS++))
        exit 1
    fi
fi
echo ""

# Test 3: Check users table exists
echo -e "${YELLOW}[3/10] Checking users table...${NC}"
TABLE_EXISTS=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc \
    "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'users');")
if [ "$TABLE_EXISTS" = "t" ]; then
    echo -e "${GREEN}✓ Users table exists${NC}"
else
    echo -e "${RED}✗ Users table does NOT exist${NC}"
    echo "  Attempting to create database tables..."
    
    # Try to find the backend directory and create tables using Python
    BACKEND_DIR=""
    if [ -d "/var/www/iiko-base/backend" ]; then
        BACKEND_DIR="/var/www/iiko-base/backend"
    elif [ -d "$(pwd)/backend" ]; then
        BACKEND_DIR="$(pwd)/backend"
    elif [ -d "../backend" ]; then
        BACKEND_DIR="../backend"
    fi
    
    if [ -n "$BACKEND_DIR" ] && [ -f "$BACKEND_DIR/database/models.py" ]; then
        echo "  Found backend directory: $BACKEND_DIR"
        ORIG_DIR=$(pwd)
        cd "$BACKEND_DIR"
        python3 <<'PYEOF' 2>&1
try:
    from database.connection import engine, Base
    from database.models import User, MenuItem, IikoSettings, Order, WebhookEvent, ApiLog
    Base.metadata.create_all(bind=engine)
    print("  ✓ Database tables created successfully")
except Exception as e:
    print(f"  ✗ Error creating tables: {e}")
    exit(1)
PYEOF
        CREATE_RESULT=$?
        cd "$ORIG_DIR"
        if [ $CREATE_RESULT -eq 0 ]; then
            echo -e "${GREEN}✓ Database tables created${NC}"
            ((FIXES_APPLIED++))
        else
            echo -e "${RED}✗ Failed to create database tables${NC}"
            echo "  Manual fix: Run database migration or setup script"
            ((ERRORS++))
            exit 1
        fi
    else
        echo -e "${RED}✗ Cannot find backend directory to create tables${NC}"
        echo "  Manual fix: Run database migration: ./scripts/setup.sh"
        ((ERRORS++))
        exit 1
    fi
fi
echo ""

# Test 4: Check role column exists (CRITICAL)
echo -e "${YELLOW}[4/10] Checking for role column...${NC}"
ROLE_COLUMN_EXISTS=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tAc \
    "SELECT EXISTS (SELECT FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'role');")
if [ "$ROLE_COLUMN_EXISTS" = "t" ]; then
    echo -e "${GREEN}✓ Role column exists${NC}"
else
    echo -e "${RED}✗ Role column is MISSING (critical bug)${NC}"
    echo "  This is a common cause of login failures!"
    echo "  Attempting to fix..."
    
    # Add role column
    PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" <<EOF
ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'viewer';
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_superuser BOOLEAN DEFAULT FALSE;
EOF
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Role column added successfully${NC}"
        ((FIXES_APPLIED++))
    else
        echo -e "${RED}✗ Failed to add role column${NC}"
        ((ERRORS++))
    fi
fi
echo ""

# Test 5: Check if admin user exists
echo -e "${YELLOW}[5/10] Checking admin user...${NC}"
ADMIN_EXISTS=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tA <<EOF
\set username '$ADMIN_USERNAME_ESCAPED'
SELECT EXISTS (SELECT 1 FROM users WHERE username = :'username');
EOF
)
if [ "$ADMIN_EXISTS" = "t" ]; then
    echo -e "${GREEN}✓ Admin user exists${NC}"
    
    # Get admin details
    ADMIN_INFO=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tA <<EOF
\set username '$ADMIN_USERNAME_ESCAPED'
SELECT id, username, email, role, is_active, is_superuser FROM users WHERE username = :'username';
EOF
)
    echo "  Details: $ADMIN_INFO"
else
    echo -e "${RED}✗ Admin user does NOT exist${NC}"
    echo "  Creating admin user..."
    
    # Create admin user with parameterized query
    PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" <<EOF
\set username '$ADMIN_USERNAME_ESCAPED'
\set email '$ADMIN_EMAIL_ESCAPED'
\set hash '$EXPECTED_HASH'
INSERT INTO users (username, email, hashed_password, role, is_active, is_superuser, created_at)
VALUES (:'username', :'email', :'hash', 'admin', TRUE, TRUE, NOW())
ON CONFLICT (username) DO NOTHING;
EOF
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Admin user created${NC}"
        ((FIXES_APPLIED++))
    else
        echo -e "${RED}✗ Failed to create admin user${NC}"
        ((ERRORS++))
    fi
fi
echo ""

# Test 6: Verify admin is active
echo -e "${YELLOW}[6/10] Checking admin is active...${NC}"
if [ "$ADMIN_EXISTS" = "t" ]; then
    IS_ACTIVE=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tA <<EOF
\set username '$ADMIN_USERNAME_ESCAPED'
SELECT is_active FROM users WHERE username = :'username';
EOF
)
    if [ "$IS_ACTIVE" = "t" ]; then
        echo -e "${GREEN}✓ Admin user is active${NC}"
    else
        echo -e "${RED}✗ Admin user is INACTIVE${NC}"
        echo "  Activating admin user..."
        
        PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" <<EOF
\set username '$ADMIN_USERNAME_ESCAPED'
UPDATE users SET is_active = TRUE WHERE username = :'username';
EOF
        
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓ Admin user activated${NC}"
            ((FIXES_APPLIED++))
        else
            echo -e "${RED}✗ Failed to activate admin user${NC}"
            ((ERRORS++))
        fi
    fi
fi
echo ""

# Test 7: Check password hash
echo -e "${YELLOW}[7/10] Verifying password hash...${NC}"
if [ "$ADMIN_EXISTS" = "t" ]; then
    CURRENT_HASH=$(PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -tA <<EOF
\set username '$ADMIN_USERNAME_ESCAPED'
SELECT hashed_password FROM users WHERE username = :'username';
EOF
)
    
    # Remove whitespace
    CURRENT_HASH=$(echo "$CURRENT_HASH" | tr -d '[:space:]')
    EXPECTED_HASH_CLEAN=$(echo "$EXPECTED_HASH" | tr -d '[:space:]')
    
    if [ "$CURRENT_HASH" = "$EXPECTED_HASH_CLEAN" ]; then
        echo -e "${GREEN}✓ Password hash is correct${NC}"
    elif [[ "$CURRENT_HASH" == *"\\$"* ]]; then
        echo -e "${RED}✗ Password hash has escaped characters (common bug)${NC}"
        echo "  Current: ${CURRENT_HASH:0:30}..."
        echo "  Expected: ${EXPECTED_HASH:0:30}..."
        echo "  Fixing hash..."
        
        PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" <<EOF
\set hash '$EXPECTED_HASH'
\set username '$ADMIN_USERNAME_ESCAPED'
UPDATE users SET hashed_password = :'hash' WHERE username = :'username';
EOF
        
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓ Password hash corrected${NC}"
            ((FIXES_APPLIED++))
        else
            echo -e "${RED}✗ Failed to fix password hash${NC}"
            ((ERRORS++))
        fi
    else
        echo -e "${YELLOW}⚠ Password hash differs from expected${NC}"
        echo "  Current: ${CURRENT_HASH:0:30}..."
        echo "  Expected: ${EXPECTED_HASH:0:30}..."
        echo "  Resetting to default password..."
        
        PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" <<EOF
\set hash '$EXPECTED_HASH'
\set username '$ADMIN_USERNAME_ESCAPED'
UPDATE users SET hashed_password = :'hash' WHERE username = :'username';
EOF
        
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓ Password reset to default${NC}"
            ((FIXES_APPLIED++))
        else
            echo -e "${RED}✗ Failed to reset password${NC}"
            ((ERRORS++))
        fi
    fi
fi
echo ""

# Test 8: Check backend service
echo -e "${YELLOW}[8/10] Checking backend service...${NC}"
BACKEND_RUNNING=false
if systemctl is-active --quiet iiko-backend 2>/dev/null; then
    echo -e "${GREEN}✓ Backend service is running (systemd)${NC}"
    BACKEND_RUNNING=true
elif docker ps | grep -q iiko.*backend 2>/dev/null; then
    echo -e "${GREEN}✓ Backend service is running (Docker)${NC}"
    BACKEND_RUNNING=true
elif pgrep -f "uvicorn.*app.main" > /dev/null; then
    echo -e "${GREEN}✓ Backend service is running (direct)${NC}"
    BACKEND_RUNNING=true
else
    echo -e "${YELLOW}⚠ Backend service is not running${NC}"
    echo "  Attempting to start backend service..."
    
    # Try systemd first
    if systemctl list-unit-files | grep -q iiko-backend 2>/dev/null; then
        if sudo systemctl start iiko-backend 2>/dev/null; then
            sleep 2
            if systemctl is-active --quiet iiko-backend 2>/dev/null; then
                echo -e "${GREEN}✓ Backend service started (systemd)${NC}"
                BACKEND_RUNNING=true
                ((FIXES_APPLIED++))
            fi
        fi
    fi
    
    # If systemd didn't work and backend directory exists, try to start directly
    if [ "$BACKEND_RUNNING" = false ]; then
        BACKEND_DIR=""
        if [ -d "/var/www/iiko-base/backend" ]; then
            BACKEND_DIR="/var/www/iiko-base/backend"
        elif [ -d "$(pwd)/backend" ]; then
            BACKEND_DIR="$(pwd)/backend"
        elif [ -d "../backend" ]; then
            BACKEND_DIR="../backend"
        fi
        
        if [ -n "$BACKEND_DIR" ] && [ -f "$BACKEND_DIR/app/main.py" ]; then
            echo "  Starting backend directly from: $BACKEND_DIR"
            ORIG_DIR=$(pwd)
            cd "$BACKEND_DIR"
            # Check if .env exists
            if [ ! -f ".env" ] && [ -f ".env.example" ]; then
                cp .env.example .env
                # Update DATABASE_URL in .env if needed
                if [ -f ".env" ]; then
                    # Update DATABASE_URL to match current configuration (|| true to ignore errors)
                    sed -i "s|DATABASE_URL=.*|DATABASE_URL=postgresql://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}|g" .env || true
                fi
                echo "  Created .env from .env.example"
            fi
            # Start backend in background
            # Note: Binds to 0.0.0.0 to allow external access. Use firewall rules or nginx proxy for production security.
            nohup uvicorn app.main:app --host 0.0.0.0 --port 8000 > /tmp/iiko-backend.log 2>&1 &
            BACKEND_PID=$!
            cd "$ORIG_DIR"
            sleep 3
            if kill -0 $BACKEND_PID 2>/dev/null; then
                echo -e "${GREEN}✓ Backend started directly (PID: $BACKEND_PID)${NC}"
                echo "  Logs: /tmp/iiko-backend.log"
                BACKEND_RUNNING=true
                ((FIXES_APPLIED++))
            else
                echo -e "${RED}✗ Backend failed to start${NC}"
                echo "  Check logs: cat /tmp/iiko-backend.log"
            fi
        fi
    fi
    
    if [ "$BACKEND_RUNNING" = false ]; then
        echo -e "${YELLOW}⚠ Could not start backend automatically${NC}"
        echo "  Manual fix: sudo systemctl start iiko-backend"
        echo "  Or: cd backend && uvicorn app.main:app --host 0.0.0.0 --port 8000"
    fi
fi
echo ""

# Test 9: Test API endpoint
echo -e "${YELLOW}[9/10] Testing API endpoint...${NC}"
if command -v curl > /dev/null 2>&1; then
    API_RESPONSE=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
        -H "Content-Type: application/json" \
        -d "{\"username\":\"$ADMIN_USERNAME\",\"password\":\"$ADMIN_PASSWORD\"}" 2>&1)
    
    if echo "$API_RESPONSE" | grep -q "access_token"; then
        echo -e "${GREEN}✓ API login successful!${NC}"
        echo "  Token received - backend is working correctly"
    elif echo "$API_RESPONSE" | grep -q "Connection refused"; then
        echo -e "${RED}✗ Backend is not reachable on port 8000${NC}"
        echo "  Fix: Start backend service"
        ((ERRORS++))
    elif echo "$API_RESPONSE" | grep -q "401\|Неверные учетные данные"; then
        echo -e "${RED}✗ API returns 401 - authentication failed${NC}"
        echo "  Response: $API_RESPONSE"
        echo "  The database might be correct but backend has issues"
        ((ERRORS++))
    else
        echo -e "${YELLOW}⚠ Unexpected API response${NC}"
        echo "  Response: $API_RESPONSE"
    fi
else
    echo -e "${YELLOW}⚠ curl not installed, skipping API test${NC}"
fi
echo ""

# Test 10: Check environment files
echo -e "${YELLOW}[10/10] Checking environment configuration...${NC}"
ISSUES=""

if [ -f "backend/.env" ]; then
    if grep -q "DATABASE_URL.*$DB_NAME" backend/.env; then
        echo -e "${GREEN}✓ Backend .env exists and configured${NC}"
    else
        echo -e "${YELLOW}⚠ Backend .env exists but may have wrong DATABASE_URL${NC}"
        ISSUES="${ISSUES}\n  - Check DATABASE_URL in backend/.env"
    fi
else
    echo -e "${YELLOW}⚠ Backend .env not found${NC}"
    ISSUES="${ISSUES}\n  - Create backend/.env from backend/.env.example"
fi

if [ -f "frontend/.env" ]; then
    if grep -q "BACKEND_API_URL\|VITE_API_URL" frontend/.env; then
        echo -e "${GREEN}✓ Frontend .env exists and configured${NC}"
    else
        echo -e "${YELLOW}⚠ Frontend .env missing API URL configuration${NC}"
        ISSUES="${ISSUES}\n  - Add BACKEND_API_URL or VITE_API_URL to frontend/.env"
    fi
else
    echo -e "${YELLOW}⚠ Frontend .env not found${NC}"
    ISSUES="${ISSUES}\n  - Create frontend/.env from frontend/.env.example"
fi

if [ -n "$ISSUES" ]; then
    echo -e "${YELLOW}Configuration issues found:${NC}"
    echo -e "$ISSUES"
fi
echo ""

# Summary
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}DIAGNOSTIC SUMMARY${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ All checks passed!${NC}"
    echo ""
    echo -e "${GREEN}You should be able to login with:${NC}"
    echo -e "  Username: ${YELLOW}$ADMIN_USERNAME${NC}"
    echo -e "  Password: ${YELLOW}$ADMIN_PASSWORD${NC}"
    echo ""
    echo -e "${RED}⚠️  IMPORTANT: Change this password immediately after login!${NC}"
else
    echo -e "${RED}✗ Found $ERRORS error(s)${NC}"
    echo ""
    echo "Please fix the errors above before attempting login."
fi

if [ $FIXES_APPLIED -gt 0 ]; then
    echo ""
    echo -e "${GREEN}Applied $FIXES_APPLIED fix(es) automatically${NC}"
    echo ""
    echo "Restart the backend service for changes to take effect:"
    echo "  sudo systemctl restart iiko-backend"
    echo "  OR: docker-compose restart backend"
fi

echo ""
echo -e "${BLUE}========================================${NC}"

exit $ERRORS

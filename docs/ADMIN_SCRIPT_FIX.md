# Admin Login Script SQL Syntax Fix

## Problem
The `diagnose_and_fix_admin.sh` script was producing a PostgreSQL syntax error:
```
ERROR:  syntax error at or near ":"
LINE 1: ...LECT EXISTS (SELECT 1 FROM users WHERE username = :'username...
```

## Root Cause
The script was using psql's `-v` option to pass variables, then trying to reference them with `:'variable'` syntax in SQL queries passed via `-tAc`. This combination doesn't work correctly in psql.

**Incorrect syntax:**
```bash
psql -v username="$ADMIN_USERNAME" -tAc \
    "SELECT EXISTS (SELECT 1 FROM users WHERE username = :'username');"
```

## Solution
Changed to use heredoc syntax with psql's `\set` meta-command, which properly supports the `:'variable'` quoting syntax. Additionally, added proper escaping of shell variables before passing them to psql.

**Correct syntax:**
```bash
# Escape single quotes for SQL string literals
ADMIN_USERNAME_ESCAPED="${ADMIN_USERNAME//\'/\'\'}"

psql -tA <<EOF
\set username '$ADMIN_USERNAME_ESCAPED'
SELECT EXISTS (SELECT 1 FROM users WHERE username = :'username');
EOF
```

## Key Differences

1. **Variable Declaration:**
   - ❌ Old: `-v username="value"` (command-line option)
   - ✅ New: `\set username 'value'` (psql meta-command)

2. **SQL Execution:**
   - ❌ Old: `-tAc "SELECT..."` (single-line command)
   - ✅ New: Heredoc with `<<EOF ... EOF` (multi-line)

3. **Variable Reference:**
   - Both use `:'variable'` syntax
   - But `:'variable'` only works correctly with `\set`

## Benefits
- ✅ Eliminates SQL syntax errors
- ✅ Proper SQL injection protection with escaped variables
- ✅ Single quotes in usernames/emails are properly escaped
- ✅ Dollar signs in password hashes don't need escaping (protected by single quotes)
- ✅ Cleaner, more readable code
- ✅ Consistent with PostgreSQL best practices

## Files Changed
- `scripts/diagnose_and_fix_admin.sh` - Fixed all psql variable references in Tests 5, 6, and 7

## Testing
Run the script to verify it works without SQL errors:
```bash
./scripts/diagnose_and_fix_admin.sh
```

Expected output should show:
```
[5/10] Checking admin user...
✓ Admin user exists
```

Instead of the previous SQL syntax error.

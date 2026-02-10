-- Reset/Create admin user with default credentials
-- WARNING: This script resets the admin password to a known default value
-- Only run this if you need to recover admin access
-- After running this script, login and change the password immediately

-- Delete existing admin user (if any)
DELETE FROM users WHERE username = 'admin';

-- Create admin user with default credentials
INSERT INTO users (email, username, hashed_password, role, is_active, is_superuser)
VALUES (
    'admin@example.com',
    'admin',
    '$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa',
    'admin',
    TRUE,
    TRUE
);

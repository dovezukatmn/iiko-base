#!/usr/bin/env python3
"""
Test script to verify bcrypt password hashing and verification.
This helps diagnose password authentication issues.

SECURITY NOTE: This script uses default credentials for diagnostic purposes.
These are the same default credentials documented throughout the project.
Users should change the default password immediately after first login.
"""
import sys

try:
    from passlib.context import CryptContext
    print("✓ passlib imported successfully")
except ImportError:
    print("✗ passlib not installed")
    print("Install with: pip install passlib[bcrypt]==1.7.4 bcrypt==4.0.1")
    sys.exit(1)

# Initialize password context (same as in backend)
pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")
print("✓ CryptContext initialized")

# The documented default password and expected hash
# These are public/known defaults that should be changed after first login
password = "12101991Qq!"
expected_hash = "$2b$12$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa"

print("\n" + "="*70)
print(" Password Hash Verification Test")
print("="*70)

print(f"\nPassword: {password}")
print(f"Expected hash: {expected_hash}")

# Test 1: Verify the expected hash works
print("\n1. Testing expected hash verification...")
try:
    result = pwd_context.verify(password, expected_hash)
    if result:
        print(f"   ✓ PASS: Password '{password}' matches expected hash")
    else:
        print(f"   ✗ FAIL: Password '{password}' does NOT match expected hash")
except Exception as e:
    print(f"   ✗ ERROR: {e}")

# Test 2: Generate a new hash and verify it
print("\n2. Generating new hash for the same password...")
try:
    new_hash = pwd_context.hash(password)
    print(f"   New hash: {new_hash}")
    
    # Verify the new hash
    result = pwd_context.verify(password, new_hash)
    if result:
        print(f"   ✓ PASS: Newly generated hash works correctly")
    else:
        print(f"   ✗ FAIL: Newly generated hash verification failed")
except Exception as e:
    print(f"   ✗ ERROR: {e}")

# Test 3: Test wrong password
print("\n3. Testing with wrong password...")
try:
    wrong_password = "WrongPassword123!"
    result = pwd_context.verify(wrong_password, expected_hash)
    if not result:
        print(f"   ✓ PASS: Wrong password correctly rejected")
    else:
        print(f"   ✗ FAIL: Wrong password was incorrectly accepted")
except Exception as e:
    print(f"   ✗ ERROR: {e}")

# Test 4: Test with escaped hash (common issue)
print("\n4. Testing with incorrectly escaped hash...")
escaped_hash = "\\$2b\\$12\\$y4QVNPhuZfpLp1.xM6.NSeDnpD6I/wm.dSOXGrxV.HtXj6izHJLPa"
print(f"   Escaped hash: {escaped_hash}")
try:
    result = pwd_context.verify(password, escaped_hash)
    if not result:
        print(f"   ✓ PASS: Escaped hash correctly rejected (as expected)")
    else:
        print(f"   ✗ FAIL: Escaped hash was accepted (this is wrong!)")
except Exception as e:
    print(f"   ℹ Exception raised (expected): {e}")

print("\n" + "="*70)
print(" Summary")
print("="*70)
print("\nThe expected hash for password '12101991Qq!' is:")
print(expected_hash)
print("\nThis hash should be stored in the database WITHOUT any backslash escaping.")
print("If you see \\$ instead of $ in the database, that's the problem!")
print("\nTo fix: Run ./scripts/reset_admin_password.sh")
print("="*70)

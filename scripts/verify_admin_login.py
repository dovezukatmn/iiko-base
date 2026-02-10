#!/usr/bin/env python3
"""
Script to verify admin login credentials are working correctly.
This can help diagnose authentication issues.
"""
import sys
import os

# Add backend to path
backend_path = os.path.join(os.path.dirname(__file__), '..', 'backend')
sys.path.insert(0, backend_path)

try:
    from sqlalchemy import create_engine
    from sqlalchemy.orm import sessionmaker
    from database.models import User
    from app.auth import verify_password
    from config.settings import settings
except ImportError as e:
    print(f"Error importing modules: {e}")
    print("Make sure you're running this from the project root and dependencies are installed.")
    sys.exit(1)

def main():
    print("=" * 80)
    print(" ADMIN LOGIN VERIFICATION ".center(80))
    print("=" * 80)
    
    # Check settings
    print("\n1. Configuration Check:")
    print(f"   Database URL: {settings.DATABASE_URL[:60]}...")
    print(f"   Default admin username: {settings.DEFAULT_ADMIN_USERNAME}")
    print(f"   Default admin email: {settings.DEFAULT_ADMIN_EMAIL}")
    print(f"   Default admin password: ********")
    
    # Connect to database
    print("\n2. Database Connection:")
    try:
        engine = create_engine(settings.DATABASE_URL)
        Session = sessionmaker(bind=engine)
        session = Session()
        print("   ✅ Successfully connected to database")
    except Exception as e:
        print(f"   ❌ Failed to connect: {e}")
        print("\nTroubleshooting:")
        print("   - Is PostgreSQL running? (sudo systemctl status postgresql)")
        print("   - Is the database created? (psql -h localhost -U iiko_user -d iiko_db -c '\\l')")
        print("   - Are the credentials in .env correct?")
        return 1
    
    # Check if admin user exists
    print("\n3. Admin User Check:")
    try:
        admin = session.query(User).filter(User.username == settings.DEFAULT_ADMIN_USERNAME).first()
        if admin:
            print(f"   ✅ Admin user '{admin.username}' found in database")
            print(f"      - ID: {admin.id}")
            print(f"      - Email: {admin.email}")
            print(f"      - Role: {admin.role}")
            print(f"      - Active: {admin.is_active}")
            print(f"      - Superuser: {admin.is_superuser}")
            print(f"      - Password hash: {admin.hashed_password[:30]}...")
        else:
            print(f"   ❌ Admin user '{settings.DEFAULT_ADMIN_USERNAME}' NOT found")
            print("\nTroubleshooting:")
            print("   - Run: psql -h localhost -U iiko_user -d iiko_db -f database/schema.sql")
            print("   - Or run: ./scripts/reset_admin_password.sh")
            return 1
    except Exception as e:
        print(f"   ❌ Error querying database: {e}")
        return 1
    
    # Check if admin is active
    print("\n4. Admin Status Check:")
    if not admin.is_active:
        print("   ❌ Admin user is INACTIVE")
        print("\nTo activate the admin user, run:")
        print(f"   psql -h localhost -U iiko_user -d iiko_db -c \"UPDATE users SET is_active = TRUE WHERE username = '{settings.DEFAULT_ADMIN_USERNAME}';\"")
        return 1
    else:
        print("   ✅ Admin user is active")
    
    # Test password verification
    print("\n5. Password Verification Test:")
    default_password = str(settings.DEFAULT_ADMIN_PASSWORD)
    try:
        is_valid = verify_password(default_password, admin.hashed_password)
        if is_valid:
            print(f"   ✅ Default password MATCHES stored hash")
            print("   ✅ Login should work with default credentials!")
        else:
            print(f"   ❌ Default password does NOT match stored hash")
            print("\nThis means the password was changed or the hash is incorrect.")
            print("To reset to default password, run:")
            print("   ./scripts/reset_admin_password.sh")
            print("Or:")
            print("   psql -h localhost -U iiko_user -d iiko_db -f database/reset_admin.sql")
            return 1
    except Exception as e:
        print(f"   ❌ Error verifying password: {e}")
        return 1
    
    # Summary
    print("\n" + "=" * 80)
    print(" VERIFICATION COMPLETE ".center(80))
    print("=" * 80)
    print("\n✅ ALL CHECKS PASSED!")
    print("\nYou should be able to login with the default admin credentials.")
    print("Check the project documentation or settings for credential details.")
    print("\nIf login still fails, check:")
    print("   1. Backend is running: systemctl status iiko-backend")
    print("   2. Backend URL in frontend .env is correct")
    print("   3. No proxy/firewall blocking requests")
    print("   4. Check backend logs: journalctl -u iiko-backend -f")
    print()
    
    session.close()
    return 0

if __name__ == "__main__":
    sys.exit(main())

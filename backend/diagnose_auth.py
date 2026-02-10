#!/usr/bin/env python3
"""
Diagnostic script to check authentication setup
"""
import sys
import os

# Add parent directory to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker
from app.auth import verify_password, get_password_hash
from config.settings import settings
from database.models import User

def main():
    print("=" * 70)
    print("AUTHENTICATION DIAGNOSTICS")
    print("=" * 70)
    
    # Check settings
    print("\n1. Settings Check:")
    print(f"   DEFAULT_ADMIN_USERNAME: {settings.DEFAULT_ADMIN_USERNAME}")
    print(f"   DEFAULT_ADMIN_EMAIL: {settings.DEFAULT_ADMIN_EMAIL}")
    print(f"   DEFAULT_ADMIN_PASSWORD: ********")
    print(f"   DATABASE_URL: {settings.DATABASE_URL[:50]}...")
    
    # Connect to database
    print("\n2. Database Connection:")
    try:
        engine = create_engine(settings.DATABASE_URL)
        Session = sessionmaker(bind=engine)
        session = Session()
        print("   ✓ Connected to database successfully")
    except Exception as e:
        print(f"   ✗ Failed to connect to database: {e}")
        return
    
    # Check if admin user exists
    print("\n3. Admin User Check:")
    try:
        admin_user = session.query(User).filter(User.username == settings.DEFAULT_ADMIN_USERNAME).first()
        if admin_user:
            print(f"   ✓ Admin user exists in database")
            print(f"     - ID: {admin_user.id}")
            print(f"     - Username: {admin_user.username}")
            print(f"     - Email: {admin_user.email}")
            print(f"     - Role: {admin_user.role}")
            print(f"     - Is Active: {admin_user.is_active}")
            print(f"     - Is Superuser: {admin_user.is_superuser}")
            print(f"     - Hashed Password: {admin_user.hashed_password[:20]}...")
        else:
            print(f"   ✗ Admin user NOT found in database")
            return
    except Exception as e:
        print(f"   ✗ Failed to query admin user: {e}")
        return
    
    # Test password verification
    print("\n4. Password Verification Test:")
    try:
        default_password = str(settings.DEFAULT_ADMIN_PASSWORD)
        is_valid = verify_password(default_password, admin_user.hashed_password)
        if is_valid:
            print(f"   ✓ Default password verifies correctly!")
        else:
            print(f"   ✗ Default password does NOT verify!")
            print(f"     Expected password: {default_password}")
            print(f"     Stored hash: {admin_user.hashed_password}")
            
            # Try to generate a new hash
            new_hash = get_password_hash(default_password)
            print(f"     New hash for password: {new_hash}")
            print(f"     New hash verifies: {verify_password(default_password, new_hash)}")
    except Exception as e:
        print(f"   ✗ Failed to verify password: {e}")
        return
    
    # Test with wrong password
    print("\n5. Wrong Password Test:")
    try:
        wrong_password = "wrongpassword123"
        is_valid = verify_password(wrong_password, admin_user.hashed_password)
        if not is_valid:
            print(f"   ✓ Wrong password correctly rejected")
        else:
            print(f"   ✗ Wrong password was accepted (THIS IS A BUG!)")
    except Exception as e:
        print(f"   ✗ Failed to test wrong password: {e}")
    
    # Count total users
    print("\n6. User Statistics:")
    try:
        total_users = session.query(User).count()
        admin_users = session.query(User).filter(User.role == 'admin').count()
        print(f"   Total users: {total_users}")
        print(f"   Admin users: {admin_users}")
    except Exception as e:
        print(f"   ✗ Failed to get user statistics: {e}")
    
    print("\n" + "=" * 70)
    print("DIAGNOSTICS COMPLETE")
    print("=" * 70)
    
    session.close()

if __name__ == "__main__":
    main()

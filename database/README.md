# Database Schema Files

This directory contains SQL schema files for initializing the PostgreSQL database.

## Files

- **init.sql**: Initialization script that runs first when the PostgreSQL container is created. Sets up extensions and permissions.
- **schema.sql**: Main database schema with table definitions for users and menu items.

## Usage

### With Docker Compose

When using `docker-compose up`, these files are automatically executed in the PostgreSQL container during initial setup.

### Manual Setup

To manually initialize the database:

```bash
# Using the setup script (recommended)
./scripts/setup.sh

# Or manually with psql
PGPASSWORD=your_password psql -h localhost -U iiko_user -d iiko_db -f database/schema.sql
```

## Database Structure

The schema includes:

- **users**: User authentication and management table
- **menu_items**: Restaurant menu items table (example for iiko integration)

Both tables include:
- Auto-incrementing IDs
- Timestamps (created_at, updated_at)
- Indexes for common queries
- Triggers for automatic timestamp updates

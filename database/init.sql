-- Инициализация базы данных iiko-base
-- Этот скрипт запускается при создании нового контейнера PostgreSQL

-- Создание расширений
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- Создание схемы (если требуется)
-- CREATE SCHEMA IF NOT EXISTS iiko;

-- Настройка прав доступа (минимально необходимые права)
GRANT CONNECT ON DATABASE iiko_db TO iiko_user;
GRANT CREATE ON DATABASE iiko_db TO iiko_user;

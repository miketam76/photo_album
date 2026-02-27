-- Create a least-privilege runtime user for the Photo Album app.
-- Run this as an administrative MySQL/MariaDB account.
-- Update the password and database name before production use.

CREATE USER IF NOT EXISTS 'photo_album_app'@'localhost'
IDENTIFIED BY 'tempPass4Now';

-- Runtime app permissions (no schema changes).
GRANT SELECT, INSERT, UPDATE, DELETE
ON `photo_album`.*
TO 'photo_album_app'@'localhost';

FLUSH PRIVILEGES;

DROP DATABASE IF EXISTS photo_album;
CREATE DATABASE IF NOT EXISTS photo_album;

USE photo_album;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(32) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'user',
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  bio TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_uuid (uuid),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS albums (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(32) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_albums_uuid (uuid),
  KEY idx_albums_user_id (user_id),
  CONSTRAINT fk_albums_user_id
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(32) NOT NULL,
  album_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  file_path TEXT NOT NULL,
  original_name VARCHAR(255) NULL,
  mime VARCHAR(100) NULL,
  size_bytes BIGINT UNSIGNED NULL,
  description TEXT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_photos_uuid (uuid),
  KEY idx_photos_album_id (album_id),
  KEY idx_photos_user_id (user_id),
  CONSTRAINT fk_photos_album_id
    FOREIGN KEY (album_id) REFERENCES albums(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_photos_user_id
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add width and height columns to photos for PhotoSwipe sizing
ALTER TABLE photos
  ADD COLUMN width INT UNSIGNED NULL,
  ADD COLUMN height INT UNSIGNED NULL;

-- Store each user's selected UI theme
ALTER TABLE users
  ADD COLUMN theme VARCHAR(64) NOT NULL DEFAULT 'terracotta';

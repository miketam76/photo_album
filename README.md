# Photo Album App (PHP 8.4)

Minimal photo album scaffold for MySQL/MariaDB + Imagick.

Quick start

1. Copy `.env.example` to `.env` and adjust if needed.
2. Run initial MySQL setup script:

```bash
mysql -u <admin_user> -p < migrations/mysql/000_photo_album_setup.sql
```

3. Start a PHP dev server (from project root):

```bash
php -S localhost:8000
```

MySQL/MariaDB 8.x migrations

Equivalent SQL migrations are provided under `migrations/mysql/`:

```bash
mysql -u <admin_user> -p < migrations/mysql/000_create_app_user.sql
mysql -u <user> -p <database> < migrations/mysql/001_init.sql
mysql -u <user> -p <database> < migrations/mysql/002_add_dimensions.sql
mysql -u <user> -p <database> < migrations/mysql/003_add_user_theme.sql
```

Note: `000_photo_album_setup.sql` is the full bootstrap script for initial setup.

For MySQL/MariaDB app access, set env vars in `.env`:

```bash
DB_DSN=mysql:host=127.0.0.1;port=3306;dbname=photo_album;charset=utf8mb4
DB_USER=photo_album_app
DB_PASS=CHANGE_ME_STRONG_PASSWORD
```

Validation checks

Run free-text sanitization validation tests (caption and album name rules):

```bash
php scripts/validation_test.php
```

Run all checks

Run migrations bootstrap + validation + upload integration in one command:

```bash
php scripts/test_all.php
```

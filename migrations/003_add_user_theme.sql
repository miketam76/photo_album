PRAGMA foreign_keys = ON;

-- Store each user's selected UI theme
ALTER TABLE users ADD COLUMN theme TEXT DEFAULT 'terracotta';

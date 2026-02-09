PRAGMA foreign_keys = ON;

-- Add width and height columns to photos for PhotoSwipe sizing
ALTER TABLE photos ADD COLUMN width INTEGER;
ALTER TABLE photos ADD COLUMN height INTEGER;

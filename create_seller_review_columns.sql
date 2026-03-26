-- Add seller review / approval workflow fields
-- Run this once on your existing database.

ALTER TABLE users
  ADD COLUMN account_status ENUM('active','pending','rejected') NOT NULL DEFAULT 'active' AFTER user_type,
  ADD COLUMN review_email_last_sent_at TIMESTAMP NULL DEFAULT NULL AFTER created_at,
  ADD COLUMN review_email_sent_count INT NOT NULL DEFAULT 0 AFTER review_email_last_sent_at,
  ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER review_email_sent_count,
  ADD COLUMN reviewed_by INT NULL DEFAULT NULL AFTER reviewed_at;

-- Optional: if you want reviewed_by to reference admins in the same table
-- (Commented out by default to avoid FK issues on existing data.)
-- ALTER TABLE users
--   ADD CONSTRAINT users_reviewed_by_fk FOREIGN KEY (reviewed_by) REFERENCES users(id);


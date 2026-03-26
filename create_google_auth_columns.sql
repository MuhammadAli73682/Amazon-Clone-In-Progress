ALTER TABLE users
  ADD COLUMN google_id VARCHAR(191) DEFAULT NULL AFTER email,
  ADD COLUMN google_picture VARCHAR(255) DEFAULT NULL AFTER google_id,
  ADD UNIQUE KEY idx_users_google_id (google_id);

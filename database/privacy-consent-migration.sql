ALTER TABLE users
  ADD COLUMN privacy_accepted TINYINT(1) NOT NULL DEFAULT 0 AFTER age,
  ADD COLUMN privacy_accepted_at DATETIME NULL AFTER privacy_accepted,
  ADD COLUMN privacy_version VARCHAR(20) NULL AFTER privacy_accepted_at,
  ADD COLUMN contact_consent TINYINT(1) NOT NULL DEFAULT 0 AFTER privacy_version;
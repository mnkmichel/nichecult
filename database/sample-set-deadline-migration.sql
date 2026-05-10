ALTER TABLE sample_sets
  ADD COLUMN rating_deadline_at DATETIME NULL AFTER image_path;

ALTER TABLE user_sample_sets
  ADD COLUMN rating_deadline_at DATETIME NULL AFTER assigned_at,
  ADD INDEX idx_user_sample_sets_deadline (rating_deadline_at);

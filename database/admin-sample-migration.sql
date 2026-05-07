ALTER TABLE samples
  ADD COLUMN description TEXT NULL AFTER brand_name,
  ADD COLUMN image_path VARCHAR(255) NULL AFTER description;

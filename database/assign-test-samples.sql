INSERT INTO samples (code, perfume_name, brand_name, status)
VALUES
  ('NC-001', 'Amber Night', 'Nichecult', 'active'),
  ('NC-002', 'Velvet Citrus', 'Nichecult', 'active'),
  ('NC-003', 'Wood Noir', 'Nichecult', 'active')
ON DUPLICATE KEY UPDATE
  perfume_name = VALUES(perfume_name),
  brand_name = VALUES(brand_name),
  status = VALUES(status);

SET @user_id := (
  SELECT id
  FROM users
  WHERE email = 'mnk-michel@gmx.de'
  LIMIT 1
);

INSERT INTO user_samples (user_id, sample_id, sample_status)
SELECT @user_id, s.id, 'delivered'
FROM samples s
WHERE @user_id IS NOT NULL
  AND s.code IN ('NC-001', 'NC-002', 'NC-003')
ON DUPLICATE KEY UPDATE
  sample_status = VALUES(sample_status),
  rated_at = NULL;

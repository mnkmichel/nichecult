CREATE TABLE IF NOT EXISTS perfumes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  brand_name VARCHAR(190) NULL,
  description TEXT NULL,
  image_path VARCHAR(255) NULL,
  size_ml SMALLINT NULL,
  price_cents INT NOT NULL DEFAULT 0,
  discount_percent TINYINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE perfumes
  ADD COLUMN image_path VARCHAR(255) NULL AFTER description,
  ADD COLUMN size_ml SMALLINT NULL,
  ADD COLUMN price_cents INT NOT NULL DEFAULT 0,
  ADD COLUMN discount_percent TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS sample_sets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  image_path VARCHAR(255) NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sample_set_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sample_set_id BIGINT NOT NULL,
  perfume_id BIGINT NOT NULL,
  sort_order TINYINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sample_set_items_set FOREIGN KEY (sample_set_id) REFERENCES sample_sets(id) ON DELETE CASCADE,
  CONSTRAINT fk_sample_set_items_perfume FOREIGN KEY (perfume_id) REFERENCES perfumes(id) ON DELETE CASCADE,
  UNIQUE KEY uq_sample_set_perfume (sample_set_id, perfume_id),
  UNIQUE KEY uq_sample_set_order (sample_set_id, sort_order)
);

CREATE TABLE IF NOT EXISTS user_sample_sets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  sample_set_id BIGINT NOT NULL,
  set_status ENUM('assigned', 'delivered', 'completed') NOT NULL DEFAULT 'assigned',
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  CONSTRAINT fk_user_sample_sets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_sample_sets_set FOREIGN KEY (sample_set_id) REFERENCES sample_sets(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_sample_set (user_id, sample_set_id)
);

CREATE TABLE IF NOT EXISTS sample_set_perfume_ratings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  user_sample_set_id BIGINT NOT NULL,
  sample_set_id BIGINT NOT NULL,
  perfume_id BIGINT NOT NULL,
  overall_score TINYINT NULL,
  longevity_score TINYINT NULL,
  sillage_score TINYINT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sspr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sspr_user_sample_set FOREIGN KEY (user_sample_set_id) REFERENCES user_sample_sets(id) ON DELETE CASCADE,
  CONSTRAINT fk_sspr_sample_set FOREIGN KEY (sample_set_id) REFERENCES sample_sets(id) ON DELETE CASCADE,
  CONSTRAINT fk_sspr_perfume FOREIGN KEY (perfume_id) REFERENCES perfumes(id) ON DELETE CASCADE,
  UNIQUE KEY uq_sspr_user_set_perfume (user_sample_set_id, perfume_id)
);

CREATE TABLE IF NOT EXISTS sample_set_perfume_rating_answers (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  rating_id BIGINT NOT NULL,
  question_key VARCHAR(80) NOT NULL,
  answer_value TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sspr_answers_rating FOREIGN KEY (rating_id) REFERENCES sample_set_perfume_ratings(id) ON DELETE CASCADE,
  INDEX idx_sspr_answers_question (rating_id, question_key)
);

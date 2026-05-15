CREATE TABLE IF NOT EXISTS tester_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(120) NULL,
  last_name VARCHAR(120) NULL,
  age TINYINT UNSIGNED NULL,
  privacy_accepted TINYINT(1) NOT NULL DEFAULT 0,
  privacy_accepted_at DATETIME NULL,
  privacy_version VARCHAR(20) NULL,
  contact_consent TINYINT(1) NOT NULL DEFAULT 0,
  tester_group_id INT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_tester_group
    FOREIGN KEY (tester_group_id) REFERENCES tester_groups(id)
    ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS questionnaire_answers (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  step_key VARCHAR(80) NOT NULL,
  answer_value TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_questionnaire_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  INDEX idx_questionnaire_user_step (user_id, step_key)
);

CREATE TABLE IF NOT EXISTS samples (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  perfume_name VARCHAR(190) NOT NULL,
  brand_name VARCHAR(190) NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_samples (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  sample_id BIGINT NOT NULL,
  sample_status ENUM('assigned', 'delivered', 'rated') NOT NULL DEFAULT 'assigned',
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  rated_at TIMESTAMP NULL,
  CONSTRAINT fk_user_samples_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_user_samples_sample
    FOREIGN KEY (sample_id) REFERENCES samples(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_user_sample (user_id, sample_id)
);

CREATE TABLE IF NOT EXISTS sample_ratings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  sample_id BIGINT NOT NULL,
  overall_score TINYINT NULL,
  longevity_score TINYINT NULL,
  sillage_score TINYINT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sample_ratings_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_sample_ratings_sample
    FOREIGN KEY (sample_id) REFERENCES samples(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_rating_per_user_sample (user_id, sample_id)
);

CREATE TABLE IF NOT EXISTS sample_rating_answers (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  rating_id BIGINT NOT NULL,
  question_key VARCHAR(80) NOT NULL,
  answer_value TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rating_answers_rating
    FOREIGN KEY (rating_id) REFERENCES sample_ratings(id)
    ON DELETE CASCADE,
  INDEX idx_rating_question (rating_id, question_key)
);

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_password_reset_token_hash (token_hash),
  INDEX idx_password_resets_user (user_id),
  INDEX idx_password_resets_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS perfumes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  brand_name VARCHAR(190) NULL,
  description TEXT NULL,
  price_cents INT NOT NULL,
  discount_percent TINYINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO tester_groups (id, name) VALUES
  (1, 'Klassisch Floral'),
  (2, 'Frisch Zitrisch'),
  (3, 'Holzig Warm'),
  (4, 'Orientalisch Intensiv'),
  (5, 'Experimentell Nischig');

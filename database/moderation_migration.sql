-- Run once against the Aiven database before deploying the updated site.
-- Existing public ads remain active; all newly submitted ads require review.

DELIMITER $$
CREATE PROCEDURE of_apply_moderation_schema()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='ads' AND column_name='owner_id') THEN
    ALTER TABLE ads ADD COLUMN owner_id BIGINT UNSIGNED NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='status') THEN
    ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active';
  END IF;
  ALTER TABLE ads MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pending_review';
END$$
DELIMITER ;
CALL of_apply_moderation_schema();
DROP PROCEDURE of_apply_moderation_schema;

UPDATE ads SET status='active' WHERE status IS NULL OR status='';

CREATE TABLE IF NOT EXISTS ad_appeals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ad_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reason TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_appeals_ad_status (ad_id, status),
  INDEX idx_appeals_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promote the intended administrator after replacing the email below with
-- the account that has already registered on the site.
SET @offria_admin_email = 'replace-with-admin-email@example.com';
UPDATE users SET role='admin', status='active' WHERE email=@offria_admin_email;

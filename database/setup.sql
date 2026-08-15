-- Password reset security migration
-- Adds one-time expiry and consumption tracking.

ALTER TABLE password_resets
    ADD COLUMN expires_at DATETIME NULL AFTER token,
    ADD COLUMN used_at DATETIME NULL AFTER expires_at;

UPDATE password_resets
SET expires_at = DATE_ADD(created_at, INTERVAL 1 HOUR)
WHERE expires_at IS NULL;

ALTER TABLE password_resets
    MODIFY expires_at DATETIME NOT NULL,
    ADD INDEX idx_expires_at (expires_at),
    ADD INDEX idx_used_at (used_at);

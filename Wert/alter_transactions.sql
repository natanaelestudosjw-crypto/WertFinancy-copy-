USE wert;

ALTER TABLE transactions
ADD COLUMN due_date DATE NULL AFTER date,
ADD COLUMN urgency ENUM('baixa', 'media', 'alta') NOT NULL DEFAULT 'media' AFTER status;

-- Migration: Add Position Column to Employee Groups
-- Date: 2026-07-05

ALTER TABLE `employee_groups` ADD COLUMN `position` INT NOT NULL DEFAULT 0;

-- Initialize existing groups position with their ID to preserve current ordering
UPDATE `employee_groups` SET `position` = `id`;

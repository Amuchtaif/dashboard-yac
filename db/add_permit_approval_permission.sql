-- Migration: Add can_approve_permits column to positions table
-- Run this SQL in phpMyAdmin or MySQL CLI

ALTER TABLE `positions` 
ADD COLUMN `can_approve_permits` TINYINT(1) NOT NULL DEFAULT 0 
AFTER `can_create_meeting`;

-- Set default values: Typically level 1-2 (Directors, Managers) can approve
UPDATE `positions` SET `can_approve_permits` = 1 WHERE `level` <= 2;

-- Migration to drop legacy single-version unique keys on class_schedules
-- to support schedule effective dates and archiving (skema baru).

ALTER TABLE `class_schedules` DROP INDEX IF EXISTS `uq_class_schedule`;
ALTER TABLE `class_schedules` DROP INDEX IF EXISTS `uq_teacher_schedule`;

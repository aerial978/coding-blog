-- Issue #59: Prepare event tables for transactional user deletion.
-- Apply manually to existing databases after creating a backup.
-- Do not execute again on databases already migrated.

ALTER TABLE `email_events` ENGINE = InnoDB;
ALTER TABLE `registration_events` ENGINE = InnoDB;
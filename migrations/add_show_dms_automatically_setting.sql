-- Migration: Add show_dms_automatically setting to users table
-- This setting controls whether DMs should automatically appear when a new message arrives

ALTER TABLE users
ADD COLUMN IF NOT EXISTS show_dms_automatically BOOLEAN DEFAULT FALSE
COMMENT 'Whether to automatically show DM modal when new messages arrive';

-- Update the column to have a default value for existing users
UPDATE users
SET show_dms_automatically = FALSE
WHERE show_dms_automatically IS NULL;

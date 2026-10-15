-- Clean up orphaned group_content records with NULL entity_id
-- This must run BEFORE group_update_9203
DELETE FROM group_content WHERE entity_id IS NULL;

-- Clean up missing module schema entries
DELETE FROM key_value WHERE collection = 'system.schema' AND name IN ('ckeditor', 'ckeditor5_embedded_content', 'gmedia', 'jquery_ui_menu', 'variationcache');

-- Remove missing modules from core.extension config
-- We need to get the current config, modify it, and save it back
-- This is complex in SQL, so we'll handle it via a separate drush command if needed

-- ============================================================================
-- Notification & Messenger System - Foreign Key Constraints
-- ============================================================================
-- ONLY RUN THIS SCRIPT AFTER:
-- 1. notification_messenger_schema.sql has been executed successfully
-- 2. cis_users table exists in your database
-- 3. cis_users has a user_id column (INT PRIMARY KEY)
-- ============================================================================

-- Verify prerequisites before running
SET FOREIGN_KEY_CHECKS = 1;

-- Add foreign key constraints
ALTER TABLE notifications
ADD CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_notif_triggered_by FOREIGN KEY (triggered_by_user_id) REFERENCES cis_users(user_id) ON DELETE SET NULL;

ALTER TABLE notification_preferences
ADD CONSTRAINT fk_pref_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE notification_delivery_queue
ADD CONSTRAINT fk_delivery_notif FOREIGN KEY (notification_id) REFERENCES notifications(notification_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_delivery_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE chat_conversations
ADD CONSTRAINT fk_conv_created_by FOREIGN KEY (created_by_user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE chat_messages
ADD CONSTRAINT fk_msg_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_msg_sender FOREIGN KEY (sender_user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_msg_reply_to FOREIGN KEY (reply_to_message_id) REFERENCES chat_messages(message_id) ON DELETE SET NULL;

ALTER TABLE chat_message_read_receipts
ADD CONSTRAINT fk_receipt_message FOREIGN KEY (message_id) REFERENCES chat_messages(message_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_receipt_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE chat_group_members
ADD CONSTRAINT fk_member_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE chat_typing_indicators
ADD CONSTRAINT fk_typing_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_typing_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE chat_blocked_users
ADD CONSTRAINT fk_block_user FOREIGN KEY (user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_block_blocked_user FOREIGN KEY (blocked_user_id) REFERENCES cis_users(user_id) ON DELETE CASCADE;

ALTER TABLE notification_messenger_links
ADD CONSTRAINT fk_link_notif FOREIGN KEY (notification_id) REFERENCES notifications(notification_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_link_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id) ON DELETE SET NULL,
ADD CONSTRAINT fk_link_message FOREIGN KEY (message_id) REFERENCES chat_messages(message_id) ON DELETE SET NULL;

-- ============================================================================
-- VERIFICATION
-- ============================================================================
-- If you see this message, all foreign keys were added successfully!
-- Run these commands to verify:
--
--   SHOW CREATE TABLE notifications;
--   SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL;
-- ============================================================================

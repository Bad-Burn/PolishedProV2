-- Track webhook work separately from event receipt so failed work can be retried.
ALTER TABLE subscription_events
    ADD COLUMN processing_status ENUM('processing', 'processed', 'failed') NOT NULL DEFAULT 'processed' AFTER provider_event_id,
    ADD COLUMN processing_started_at DATETIME NULL AFTER processing_status,
    ADD COLUMN processed_at DATETIME NULL AFTER processing_started_at;

-- Older rows were treated as complete by the previous handler.
UPDATE subscription_events
   SET processing_status = 'processed', processed_at = created_at
 WHERE processed_at IS NULL;

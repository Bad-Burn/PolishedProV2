-- Add the low-cost monthly artist tier and a first-class PayPal payment ledger.
-- Existing subscription and payment rows are retained.

ALTER TABLE subscriptions
    ADD COLUMN paypal_plan_id VARCHAR(60) NULL AFTER paypal_subscription_id,
    ADD COLUMN last_successful_payment_at DATETIME NULL AFTER current_period_end;

UPDATE subscriptions s
  JOIN plans p ON p.id = s.plan_id
   SET s.paypal_plan_id = CASE
       WHEN s.billing_cycle = 'yearly' THEN p.paypal_plan_id_yearly
       ELSE p.paypal_plan_id_monthly
   END
 WHERE s.provider = 'paypal' AND s.paypal_plan_id IS NULL;

UPDATE subscriptions s
  JOIN (
       SELECT subscription_id, MAX(paid_at) AS last_paid_at
         FROM payments
        WHERE purpose = 'subscription' AND status = 'completed' AND paid_at IS NOT NULL
        GROUP BY subscription_id
  ) paid ON paid.subscription_id = s.id
   SET s.last_successful_payment_at = paid.last_paid_at
 WHERE s.last_successful_payment_at IS NULL;

INSERT INTO plans
    (code, name, tagline, price_monthly, price_yearly, currency,
     max_services, max_portfolio_items, featured_weight, badge,
     has_analytics, has_instant_book, highlights, is_active, sort_order)
VALUES
    ('premium', 'Premium', 'For independent artists ready to grow their bookings.', 149.00, 0.00, 'PHP',
     3, 6, 0, NULL, 0, 0,
     '["Up to 3 active services","6 portfolio photos","Standard search placement","Booking calendar and client messages","Reviews and ratings"]',
     1, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), tagline = VALUES(tagline),
    price_monthly = VALUES(price_monthly), price_yearly = VALUES(price_yearly),
    currency = VALUES(currency), max_services = VALUES(max_services),
    max_portfolio_items = VALUES(max_portfolio_items), featured_weight = VALUES(featured_weight),
    badge = VALUES(badge), has_analytics = VALUES(has_analytics),
    has_instant_book = VALUES(has_instant_book), highlights = VALUES(highlights),
    is_active = VALUES(is_active), sort_order = VALUES(sort_order);

ALTER TABLE payments
    MODIFY COLUMN status ENUM('pending','completed','failed','refunded','reversed') NOT NULL DEFAULT 'pending',
    ADD COLUMN provider_event_id VARCHAR(64) NULL AFTER provider_txn_id,
    ADD UNIQUE KEY uq_payment_event (provider_event_id),
    ADD KEY idx_payments_subscription (subscription_id, created_at);

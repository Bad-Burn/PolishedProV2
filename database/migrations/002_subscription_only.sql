-- ---------------------------------------------------------------------------
-- Move from a three-tier model with a free Starter plan to subscription-only,
-- with a time-limited trial for new artists.
--
-- Before: an artist with no subscription silently fell back to the free
-- Starter plan and could trade on it forever. After: every artist is either
-- inside their trial window or holding a paid subscription. Anything else is
-- "lapsed" — hidden from search and unable to take new bookings.
-- ---------------------------------------------------------------------------

-- When the artist's free trial runs out. NULL for clients, and for artists who
-- registered before this migration and are handled by the backfill below.
ALTER TABLE users
    ADD COLUMN trial_ends_at DATETIME NULL AFTER approved_at,
    ADD KEY idx_users_trial (trial_ends_at);

-- Starter is retired. The row stays so existing subscriptions keep their
-- foreign key, but it is inactive so it never appears in the plan chooser,
-- and renamed so nobody mistakes it for something still on sale.
UPDATE plans
   SET is_active = 0,
       name      = 'Starter (retired)',
       tagline   = 'Replaced by the free trial. Kept for historical subscriptions.'
 WHERE code = 'starter';

-- Existing artists sitting on a free Starter subscription are not customers —
-- they were never charged. Expire those rows and give them a trial instead, so
-- they get a fair window to subscribe rather than going dark overnight.
UPDATE subscriptions s
  JOIN plans p ON p.id = s.plan_id
   SET s.status = 'expired'
 WHERE p.code = 'starter'
   AND s.status IN ('active', 'past_due', 'pending');

-- Every artist without a live paid subscription starts a trial from today.
-- Artists who already pay are untouched.
UPDATE users u
   SET u.trial_ends_at = DATE_ADD(NOW(), INTERVAL 14 DAY)
 WHERE u.role = 'artist'
   AND u.trial_ends_at IS NULL
   AND NOT EXISTS (
       SELECT 1
         FROM subscriptions s
         JOIN plans p ON p.id = s.plan_id
        WHERE s.user_id = u.id
          AND s.status IN ('active', 'past_due')
          AND p.price_monthly > 0
          AND (s.current_period_end IS NULL OR s.current_period_end > NOW())
   );

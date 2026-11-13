-- ---------------------------------------------------------------------------
-- Email goes live: appointment reminders, trial-ending warnings and email
-- confirmation before booking.
-- ---------------------------------------------------------------------------

-- Set when the day-before reminder goes out, so an hourly job sends it once.
ALTER TABLE bookings
    ADD COLUMN reminder_sent_at DATETIME NULL AFTER cancelled_at;

-- Set when the "your trial ends soon" warning goes out.
ALTER TABLE users
    ADD COLUMN trial_warned_at DATETIME NULL AFTER trial_ends_at;

-- Booking now requires a confirmed email address. Accounts that existed before
-- confirmation emails did are trusted as they are, rather than locked out of
-- booking until they find a link nobody ever sent them.
UPDATE users
   SET email_verified_at = created_at
 WHERE email_verified_at IS NULL;

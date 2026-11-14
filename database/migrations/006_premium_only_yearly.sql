-- Keep the old paid plan rows for subscription history, but stop offering them
-- to new artists. Premium is the sole current tier, with monthly and yearly
-- billing options.
UPDATE plans
   SET is_active = 0
 WHERE code IN ('studio', 'signature');

UPDATE plans
   SET price_yearly = 1490.00,
       is_active = 1
 WHERE code = 'premium';

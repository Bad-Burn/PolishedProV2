<?php
declare(strict_types=1);

layout('app');
?>
<div class="container container--narrow section">
    <p class="eyebrow mb-3">Legal</p>
    <h1 class="display-1 mb-4">Privacy policy</h1>
    <p class="muted mb-10">Last updated <?= e(date('F Y')) ?>.</p>

    <div class="alert alert--warning mb-10">
        <?= icon('alert') ?>
        <span>
            <strong>This is a placeholder policy.</strong>
            It accurately describes what this software does with data, but it has not been reviewed
            against the Philippine Data Privacy Act or any other law. Have it reviewed before launch.
        </span>
    </div>

    <div class="stack-lg">
        <section>
            <h2 class="heading-md mb-3">What we store</h2>
            <p class="muted">
                Your name, email, optional phone number and profile photo. For artists, everything on
                your public profile plus your service menu and working hours. For everyone: your
                bookings, reviews and notifications.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">What we deliberately do not store</h2>
            <p class="muted">
                Card numbers, CVVs or bank details — none of it, ever. Subscription payments go through
                PayPal, who hold the payment instrument; we only keep their subscription reference and
                whether it is active. Passwords are stored as Argon2id hashes, which cannot be reversed
                into the original password.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">Who can see what</h2>
            <p class="muted">
                Your artist profile, service menu, portfolio and reviews are public. Your email and
                phone number are not — they become visible to the other party only once a booking is
                confirmed, so you can actually reach each other. Studio addresses are shown only to
                clients with a confirmed appointment.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">Security</h2>
            <p class="muted">
                Sessions are bound to your browser and expire after inactivity. Uploaded images are
                re-encoded, which strips the GPS coordinates most phones embed in photos. Administrative
                actions are logged. Optional two-step verification is available to every account.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">Retention and deletion</h2>
            <p class="muted">
                You can delete your account from Security settings once you have no upcoming
                appointments. Deletion removes your profile, bookings and reviews. Audit log entries
                recording administrative actions are retained without your personal details attached.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">Cookies</h2>
            <p class="muted">
                One session cookie, and one optional "keep me signed in" cookie if you tick that box.
                Both are marked HttpOnly and SameSite. No advertising or tracking cookies, and no
                third-party analytics.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">Contact</h2>
            <p class="muted">
                Questions or a data request:
                <a href="mailto:<?= e($supportEmail ?? 'support@polishedpro.test') ?>"><?= e($supportEmail ?? 'support@polishedpro.test') ?></a>.
            </p>
        </section>
    </div>
</div>

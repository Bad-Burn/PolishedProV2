<?php
declare(strict_types=1);

layout('app');
?>
<div class="container container--narrow section">
    <p class="eyebrow mb-3">Legal</p>
    <h1 class="display-1 mb-4">Terms of service</h1>
    <p class="muted mb-10">Last updated <?= e(date('F Y')) ?>.</p>

    <div class="alert alert--warning mb-10">
        <?= icon('alert') ?>
        <span>
            <strong>These are placeholder terms.</strong>
            They describe how the platform is built and intended to work, but they have not been
            reviewed by a lawyer. Replace this page with terms drafted for your jurisdiction before
            you take real money or real bookings.
        </span>
    </div>

    <div class="stack-lg">
        <section>
            <h2 class="heading-md mb-3">1. What PolishedPro is</h2>
            <p class="muted">
                PolishedPro is a directory and booking tool. We connect clients with independent beauty
                professionals. We are not a party to the appointment itself: the service is provided by
                the artist, paid to the artist, and any dispute about the work is between the client
                and the artist.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">2. Accounts</h2>
            <p class="muted">
                You are responsible for what happens under your account, so keep your password to
                yourself and turn on two-step verification. Give accurate information — particularly
                artists, whose service descriptions, durations and prices clients rely on. We may
                suspend accounts that misrepresent themselves or harm other users.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">3. Bookings and cancellations</h2>
            <p class="muted">
                A booking is a request until the artist confirms it, unless that artist has instant
                booking enabled. Each artist sets their own cancellation policy, shown on their profile
                and on every booking. Payment is arranged directly between client and artist; we do not
                process, hold or refund it.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">4. Reviews</h2>
            <p class="muted">
                Only a client who completed an appointment may review it, once. Artists may reply
                publicly. We remove reviews that contain personal data, threats, or content unrelated
                to the service received — and we log every such removal.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">5. Subscriptions</h2>
            <p class="muted">
                Artist subscriptions renew automatically until cancelled, and are billed through PayPal.
                Cancelling stops the next renewal; you keep the plan's benefits until the end of the
                period you already paid for. We do not offer partial refunds for unused time. We take no
                commission on bookings on any plan.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">6. Acceptable use</h2>
            <p class="muted">
                Do not use the platform to harass anyone, scrape it, attempt to access accounts that are
                not yours, or upload content you do not have the rights to. Automated access and
                security testing require our written permission first.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">7. Liability</h2>
            <p class="muted">
                The platform is provided as-is. We do our best to keep it running and to verify the
                artists listed, but we cannot guarantee the quality or outcome of any appointment
                booked through it.
            </p>
        </section>

        <section>
            <h2 class="heading-md mb-3">8. Changes</h2>
            <p class="muted">
                We will post material changes to these terms on this page and note the date above.
                Questions go to
                <a href="mailto:<?= e($supportEmail ?? 'support@polishedpro.test') ?>"><?= e($supportEmail ?? 'support@polishedpro.test') ?></a>.
            </p>
        </section>
    </div>
</div>

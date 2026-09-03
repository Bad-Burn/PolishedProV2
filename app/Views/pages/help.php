<?php
declare(strict_types=1);

layout('app');
?>
<div class="container container--narrow section">
    <p class="eyebrow mb-3">Help centre</p>
    <h1 class="display-1 mb-4">How PolishedPro works</h1>
    <p class="lede mb-12">
        If your answer is not here, email
        <a href="mailto:<?= e($supportEmail ?? 'support@polishedpro.test') ?>"><?= e($supportEmail ?? 'support@polishedpro.test') ?></a>
        and a person will reply.
    </p>

    <section class="mb-12">
        <h2 class="display-2 mb-6">For clients</h2>

        <div class="stack-lg">
            <div>
                <h3 class="heading-sm mb-2">How do I book?</h3>
                <p class="muted">
                    Find an artist, pick a service from their menu, then choose a date and time from
                    the slots shown. Those slots are their real availability — if you can select it,
                    it is free.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">When is my booking confirmed?</h3>
                <p class="muted">
                    Most artists confirm manually, usually within a few hours. Some have instant
                    booking switched on, in which case you are confirmed straight away. Either way
                    you get a notification and can check the status on your bookings page.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">How do I pay?</h3>
                <p class="muted">
                    Directly to your artist at the appointment. There is no booking fee and no card
                    required to reserve a slot.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">Can I cancel?</h3>
                <p class="muted">
                    Yes, from the booking page. Most artists offer free cancellation up to 24 hours
                    beforehand — their exact policy is on their profile and on your booking. Cancelling
                    later than that is still possible here, but message them as a courtesy.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">Why can I not leave a review?</h3>
                <p class="muted">
                    Reviews are only possible on appointments that were actually completed, and only
                    one per booking. It is the reason the ratings on this site are worth reading.
                </p>
            </div>
        </div>
    </section>

    <section class="mb-12">
        <h2 class="display-2 mb-6">For artists</h2>

        <div class="stack-lg">
            <div>
                <h3 class="heading-sm mb-2">Why am I not showing up in search?</h3>
                <p class="muted">
                    New artist profiles are reviewed by an admin before going live. You also need at
                    least one active service. Your dashboard shows a checklist of anything outstanding.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">How do I stop double bookings?</h3>
                <p class="muted">
                    You do not have to do anything. Set your working hours and clean-up time per
                    service, and the system will not offer a slot that overlaps an existing
                    appointment or falls outside your hours.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">What does a paid plan get me?</h3>
                <p class="muted">
                    More services you can publish at once, more portfolio photos, higher placement on
                    the default search sort, a badge, analytics and instant booking. It never changes
                    your reviews or your rating. Full breakdown on the
                    <a href="<?= e(url('/pricing')) ?>">pricing page</a>.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">Do you take commission?</h3>
                <p class="muted">
                    No. Not on the trial, not on any plan.
                </p>
            </div>
        </div>
    </section>

    <section>
        <h2 class="display-2 mb-6">Your account</h2>

        <div class="stack-lg">
            <div>
                <h3 class="heading-sm mb-2">I forgot my password</h3>
                <p class="muted">
                    Use the <a href="<?= e(url('/forgot-password')) ?>">reset link</a> on the sign-in
                    page. Links expire after an hour and work once.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">How do I make my account more secure?</h3>
                <p class="muted">
                    Turn on two-step verification in
                    <a href="<?= e(url('/settings/security')) ?>">Security settings</a>. It works with
                    any authenticator app and takes about a minute to set up. Save the recovery codes
                    somewhere other than your phone.
                </p>
            </div>

            <div>
                <h3 class="heading-sm mb-2">Can I delete my account?</h3>
                <p class="muted">
                    Yes, from Security settings, once you have no upcoming appointments. It is permanent.
                </p>
            </div>
        </div>
    </section>
</div>

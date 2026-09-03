<?php
/** @var array<int, array<string, mixed>> $plans */

declare(strict_types=1);

layout('app');
?>
<section class="hero">
    <div class="container">
        <div class="hero__grid">
            <div>
                <p class="eyebrow mb-4">For professionals</p>

                <h1 class="display-1 hero__title">
                    Stop running your<br>diary out of <em>Messenger</em>.
                </h1>

                <p class="lede hero__lede">
                    Publish your price list, set your working hours once, and let people book the slots
                    you are actually free. You keep every peso your clients pay you.
                </p>

                <div class="hero__actions">
                    <a class="btn btn--primary btn--lg" href="<?= e(url('/register', ['as' => 'artist'])) ?>">
                        Start listing free
                    </a>
                    <a class="btn btn--secondary btn--lg" href="<?= e(url('/pricing')) ?>">See the plans</a>
                </div>

                <dl class="hero__proof">
                    <div><dt>Commission on bookings</dt><dd>0%</dd></div>
                    <div><dt>Free services</dt><dd>3</dd></div>
                    <div><dt>Contract length</dt><dd>None</dd></div>
                </dl>
            </div>

            <?php
            $tiles = [
                ['name' => 'menu',     'label' => 'Your menu',   'tint' => 'linear-gradient(155deg,#efe0d6,#d9bcae)'],
                ['name' => 'studio',   'label' => 'Your hours',  'tint' => 'linear-gradient(155deg,#f0e4e4,#d9c0c4)'],
                ['name' => 'clients',  'label' => 'Your clients','tint' => 'linear-gradient(155deg,#e7dfd0,#c9b493)'],
                ['name' => 'products', 'label' => 'Your prices', 'tint' => 'linear-gradient(155deg,#e2e6dd,#b9c3b3)'],
            ];
            ?>
            <div class="hero__collage">
                <?php foreach ($tiles as $i => $tile): ?>
                    <?= view_partial('showcase-tile', $tile + ['eager' => $i < 2]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="section section--sunk">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow mb-2">What you get</p>
                <h2 class="display-2">Built around how you actually work</h2>
            </div>
        </div>

        <div class="grid grid--3">
            <article class="card">
                <span class="avatar avatar--lg mb-4" style="background:var(--accent-soft); color:var(--accent);">
                    <?= icon('clock') ?>
                </span>
                <h3 class="heading-sm mb-3">A calendar that respects your time</h3>
                <p class="muted">
                    Set your working hours per day, add split shifts for a lunch break, and block off
                    holidays. Clean-up time after each service is reserved automatically, so a
                    two-hour set never gets a client booked ten minutes after it ends.
                </p>
            </article>

            <article class="card">
                <span class="avatar avatar--lg mb-4" style="background:var(--accent-soft); color:var(--accent);">
                    <?= icon('shield') ?>
                </span>
                <h3 class="heading-sm mb-3">No double bookings</h3>
                <p class="muted">
                    Two people clicking the same slot at the same moment cannot both get it — the
                    booking is locked at the database level, not just hidden in the interface.
                </p>
            </article>

            <article class="card">
                <span class="avatar avatar--lg mb-4" style="background:var(--accent-soft); color:var(--accent);">
                    <?= icon('star') ?>
                </span>
                <h3 class="heading-sm mb-3">Reviews that mean something</h3>
                <p class="muted">
                    Only a client who completed an appointment with you can leave a review, and only
                    one per booking. You can reply publicly to any of them.
                </p>
            </article>

            <article class="card">
                <span class="avatar avatar--lg mb-4" style="background:var(--accent-soft); color:var(--accent);">
                    <?= icon('wallet') ?>
                </span>
                <h3 class="heading-sm mb-3">Zero commission</h3>
                <p class="muted">
                    Your clients pay you directly, in cash or however you arrange it. We are not in the
                    middle of that. The optional subscription is the only money we ever take.
                </p>
            </article>

            <article class="card">
                <span class="avatar avatar--lg mb-4" style="background:var(--accent-soft); color:var(--accent);">
                    <?= icon('users') ?>
                </span>
                <h3 class="heading-sm mb-3">Your client list is yours</h3>
                <p class="muted">
                    Visit history, spend and contact details for everyone who has booked you. We do not
                    email your clients on your behalf, and we do not sell this to anyone.
                </p>
            </article>

            <article class="card">
                <span class="avatar avatar--lg mb-4" style="background:var(--accent-soft); color:var(--accent);">
                    <?= icon('image') ?>
                </span>
                <h3 class="heading-sm mb-3">A portfolio that loads fast</h3>
                <p class="muted">
                    Upload your work and we resize it, strip the location data out of the file, and
                    serve it properly. Six photos free, more on a paid plan.
                </p>
            </article>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow mb-2">Getting started</p>
                <h2 class="display-2">Live by tomorrow</h2>
            </div>
        </div>

        <div class="grid grid--3 steps">
            <div class="step">
                <h3 class="heading-sm">Create your account</h3>
                <p>Name, email, city. Two minutes, no card.</p>
            </div>
            <div class="step">
                <h3 class="heading-sm">Add services and hours</h3>
                <p>Your price list and your working week. This is the part that matters — be honest about durations.</p>
            </div>
            <div class="step">
                <h3 class="heading-sm">Get approved</h3>
                <p>We check every artist before they appear in search. It usually takes under a day.</p>
            </div>
        </div>

        <div class="text-center mt-10">
            <a class="btn btn--primary btn--lg" href="<?= e(url('/register', ['as' => 'artist'])) ?>">
                Create your artist account
            </a>
        </div>
    </div>
</section>

<?php
/**
 * @var array<int, array<string, mixed>> $featured
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, int> $favoriteIds
 * @var int $artistCount
 * @var int $bookingCount
 * @var array<int, array<string, mixed>> $cities
 */

declare(strict_types=1);

layout('app');

/* Hero collage. The tint is what shows while the photo downloads, so each one
   is sampled from its own image — the tile never flashes the wrong colour. */
$swatches = [
    ['name' => 'nails',  'label' => 'Nails',   'tint' => 'linear-gradient(155deg,#f0dcd4,#dcb2ad)'],
    ['name' => 'hair',   'label' => 'Hair',    'tint' => 'linear-gradient(155deg,#f2dfe0,#d9a9b3)'],
    ['name' => 'makeup', 'label' => 'Make-up', 'tint' => 'linear-gradient(155deg,#efe0e4,#cfa9b6)'],
    ['name' => 'skin',   'label' => 'Skin',    'tint' => 'linear-gradient(155deg,#e9ddd2,#c9ac97)'],
];
?>

<!-- ============================= Hero ============================= -->
<section class="hero">
    <div class="container">
        <div class="hero__grid">
            <div>
                <p class="eyebrow mb-4">Metro Manila &amp; nearby</p>

                <h1 class="display-1 hero__title">
                    Book the artist,<br>not the <em>waiting list</em>.
                </h1>

                <p class="lede hero__lede">
                    See real availability from verified nail artists, hairstylists, make-up artists and
                    barbers — then book the slot you actually want. No back-and-forth on Messenger.
                </p>

                <div class="hero__actions">
                    <a class="btn btn--primary btn--lg" href="<?= e(url('/artists')) ?>">Find an artist</a>
                    <a class="btn btn--secondary btn--lg" href="<?= e(url('/for-artists')) ?>">I'm an artist</a>
                </div>

                <dl class="hero__proof">
                    <div>
                        <dt>Artists listed</dt>
                        <dd><?= e(number_format($artistCount)) ?></dd>
                    </div>
                    <div>
                        <dt>Appointments completed</dt>
                        <dd><?= e(number_format($bookingCount)) ?></dd>
                    </div>
                    <div>
                        <dt>Booking fee for clients</dt>
                        <dd>₱0</dd>
                    </div>
                </dl>
            </div>

            <div class="hero__collage">
                <?php foreach ($swatches as $i => $swatch): ?>
                    <?= view_partial('showcase-tile', [
                        'name'  => $swatch['name'],
                        'label' => $swatch['label'],
                        'tint'  => $swatch['tint'],
                        // The first two are above the fold on a phone.
                        'eager' => $i < 2,
                    ]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================= Search ============================= -->
<section class="section--tight">
    <div class="container">
        <form class="card card--raised" method="get" action="<?= e(url('/artists')) ?>">
            <div class="row row--nowrap" style="gap: var(--sp-3); align-items: flex-end; flex-wrap: wrap;">
                <div class="field filter-bar__grow" style="margin-bottom:0;">
                    <label class="field__label" for="home-q">What are you looking for?</label>
                    <div class="input-group">
                        <?= icon('search') ?>
                        <input class="input" type="search" id="home-q" name="q"
                               placeholder="Gel extensions, balayage, bridal make-up…">
                    </div>
                </div>

                <div class="field" style="margin-bottom:0; flex: 0 1 12rem;">
                    <label class="field__label" for="home-city">Where</label>
                    <select class="select" id="home-city" name="city">
                        <option value="">Anywhere</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= e($city['city']) ?>"><?= e($city['city']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="btn btn--primary" type="submit" style="height: 2.75rem;">Search</button>
            </div>
        </form>
    </div>
</section>

<!-- ============================= Categories ============================= -->
<?php if ($categories !== []): ?>
<section class="section--tight">
    <div class="container">
        <div class="category-rail">
            <?php foreach ($categories as $category): ?>
                <a class="category-chip" href="<?= e(url('/artists', ['category' => $category['slug']])) ?>">
                    <?= e($category['name']) ?>
                    <?php if ((int) $category['artist_count'] > 0): ?>
                        <span class="category-chip__count"><?= (int) $category['artist_count'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================= Featured artists ============================= -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow mb-2">Highly rated</p>
                <h2 class="display-2">Artists people go back to</h2>
                <p>Ranked by real reviews from completed appointments — not by who paid the most.</p>
            </div>
            <a class="link-arrow" href="<?= e(url('/artists')) ?>">See all artists</a>
        </div>

        <?php if ($featured === []): ?>
            <?= view_partial('empty', [
                'icon'    => 'sparkle',
                'title'   => 'No artists listed yet',
                'message' => 'Be the first to list your services on PolishedPro.',
                'actionLabel' => 'List your services',
                'actionUrl'   => url('/register', ['as' => 'artist']),
            ]) ?>
        <?php else: ?>
            <div class="grid grid--auto">
                <?php foreach ($featured as $artist): ?>
                    <?= view_partial('artist-card', ['artist' => $artist, 'favoriteIds' => $favoriteIds]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================= How it works ============================= -->
<section class="section section--sunk">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow mb-2">How it works</p>
                <h2 class="display-2">Three steps, no phone tag</h2>
            </div>
        </div>

        <div class="grid grid--3 steps">
            <div class="step">
                <h3 class="heading-sm">Find someone whose work you like</h3>
                <p>Browse by service, area or price. Every profile shows a real portfolio, an honest price list, and reviews that can only be left after a completed appointment.</p>
            </div>
            <div class="step">
                <h3 class="heading-sm">Pick a slot that is genuinely free</h3>
                <p>You see the artist's actual calendar — their working hours, their days off, and the slots already taken. If you can click it, you can have it.</p>
            </div>
            <div class="step">
                <h3 class="heading-sm">Turn up and pay the artist</h3>
                <p>Settle up directly with your artist in cash or however they prefer. We do not take a cut of your appointment and there is no booking fee.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================= Artist CTA ============================= -->
<section class="section">
    <div class="container">
        <div class="cta-band">
            <div>
                <p class="eyebrow mb-3">For professionals</p>
                <h2 class="display-2 mb-4">Your calendar, your prices, your clients.</h2>
                <p class="lede mb-6">
                    List up to three services free, forever. Set your working hours once and let people
                    book around them. Upgrade only when you need more listings or better placement.
                </p>
                <div class="row">
                    <a class="btn btn--primary btn--lg" href="<?= e(url('/register', ['as' => 'artist'])) ?>">Start listing free</a>
                    <a class="btn btn--ghost" href="<?= e(url('/pricing')) ?>">Compare plans</a>
                </div>
            </div>

            <ul class="plan__features" style="margin:0;">
                <li><?= icon('check') ?><span>No commission on your bookings</span></li>
                <li><?= icon('check') ?><span>Free plan with three active services</span></li>
                <li><?= icon('check') ?><span>Cancel a paid plan any time</span></li>
                <li><?= icon('check') ?><span>Your client list stays yours</span></li>
            </ul>
        </div>
    </div>
</section>

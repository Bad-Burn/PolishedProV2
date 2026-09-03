<?php
/**
 * Public artist profile: identity, service menu, portfolio, reviews, and a
 * sticky booking panel.
 *
 * @var array<string, mixed> $artist
 * @var array<int, array<string, mixed>> $services
 * @var array<int, array<string, mixed>> $portfolio
 * @var array<int, array<string, mixed>> $reviews
 * @var array<int, int> $distribution
 * @var array<int, array<string, mixed>> $schedule
 * @var string|null $nextAvailable
 * @var bool $isFavorite
 * @var int $reviewCount
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Models\Artist;

layout('app');

$artistId  = (int) $artist['id'];
$rating    = (float) $artist['rating_avg'];
$photoCount = (int) ($artist['photo_count'] ?? 0);
$portfolioUrl = url('/artists/' . $artist['slug'] . '/portfolio');
$location = trim(($artist['area'] ?? '') . (($artist['area'] && $artist['city']) ? ', ' : '') . ($artist['city'] ?? ''), ', ');
?>
<div class="container">
    <div class="profile-hero">
        <!-- ------------------------- Identity ------------------------- -->
        <div>
            <a class="link-back small muted mb-6" href="<?= e(url('/artists')) ?>">
                <?= icon('arrow-left') ?> Back to artists
            </a>

            <div class="profile-id mt-4">
                <?= view_partial('avatar', ['user' => $artist, 'size' => '2xl']) ?>

                <div>
                    <div class="row row--tight mb-2">
                        <?php if (!empty($artist['is_verified'])): ?>
                            <span class="badge badge--positive"><?= icon('badge-check') ?> Verified</span>
                        <?php endif; ?>
                        <?php if (!empty($artist['plan_badge'])): ?>
                            <span class="plan-badge"><?= e($artist['plan_badge']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($artist['instant_book'])): ?>
                            <span class="badge badge--accent">Instant booking</span>
                        <?php endif; ?>
                    </div>

                    <h1 class="display-2 profile-id__name"><?= e($artist['display_name']) ?></h1>

                    <?php if (!empty($artist['headline'])): ?>
                        <p class="lede mb-4" style="max-width:52ch;"><?= e($artist['headline']) ?></p>
                    <?php endif; ?>

                    <div class="profile-meta">
                        <?php if ($reviewCount > 0): ?>
                            <span><?= stars($rating) ?> <span class="muted">(<?= e(plural($reviewCount, 'review')) ?>)</span></span>
                        <?php else: ?>
                            <span class="muted">No reviews yet</span>
                        <?php endif; ?>

                        <?php if ($location !== ''): ?>
                            <span><?= icon('pin') ?> <?= e($location) ?></span>
                        <?php endif; ?>

                        <?php if ((int) $artist['years_experience'] > 0): ?>
                            <span><?= icon('sparkle') ?> <?= e(plural((int) $artist['years_experience'], 'year')) ?> experience</span>
                        <?php endif; ?>

                        <?php if ((int) $artist['completed_bookings'] > 0): ?>
                            <span><?= icon('check-circle') ?> <?= e(number_format((int) $artist['completed_bookings'])) ?> appointments</span>
                        <?php endif; ?>
                    </div>

                    <div class="row mt-6">
                        <?php if (Auth::guest() || Auth::isCustomer()): ?>
                            <form method="post" action="<?= e(url('/artists/' . $artistId . '/favorite')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn--secondary btn--sm <?= $isFavorite ? 'is-on' : '' ?>"
                                        type="submit"
                                        data-favorite="<?= e(url('/artists/' . $artistId . '/favorite')) ?>"
                                        data-login-url="<?= e(url('/login')) ?>"
                                        aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>">
                                    <?= icon('heart') ?> <?= $isFavorite ? 'Saved' : 'Save artist' ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if (!empty($artist['instagram'])): ?>
                            <a class="btn btn--ghost btn--sm" rel="noopener noreferrer nofollow" target="_blank"
                               href="https://instagram.com/<?= e(ltrim((string) $artist['instagram'], '@')) ?>">
                                <?= icon('external') ?> @<?= e(ltrim((string) $artist['instagram'], '@')) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <nav class="profile-jump mt-8" aria-label="Sections of this profile">
                <a href="#services">
                    <?= icon('file') ?> Services
                    <?php if ($services !== []): ?><span><?= count($services) ?></span><?php endif; ?>
                </a>
                <a href="#portfolio">
                    <?= icon('image') ?> Portfolio
                    <?php if ($photoCount > 0): ?><span><?= (int) $photoCount ?></span><?php endif; ?>
                </a>
                <a href="#reviews">
                    <?= icon('star') ?> Reviews
                    <?php if ($reviewCount > 0): ?><span><?= (int) $reviewCount ?></span><?php endif; ?>
                </a>
            </nav>

            <?php if (!empty($artist['bio'])): ?>
                <section class="mt-10">
                    <h2 class="heading-md mb-4">About</h2>
                    <p class="profile-bio"><?= e($artist['bio']) ?></p>
                </section>
            <?php endif; ?>
        </div>

        <!-- ------------------------- Booking panel ------------------------- -->
        <aside class="booking-panel">
            <p class="eyebrow mb-3">Book an appointment</p>

            <?php if ($services === []): ?>
                <p class="muted small">This artist has not published a service menu yet.</p>
            <?php else: ?>
                <?php $cheapest = min(array_map(static fn ($s) => (float) $s['price'], $services)); ?>

                <p class="mb-1"><span class="muted small">Services from</span></p>
                <p class="display-2" style="font-size:2rem; margin-bottom: var(--sp-4);"><?= e(money($cheapest)) ?></p>

                <?php if ($nextAvailable !== null): ?>
                    <p class="small mb-5">
                        <span class="badge badge--positive badge--dot">Next opening</span>
                        <span class="muted"><?= e(date_label($nextAvailable, 'l, M j')) ?></span>
                    </p>
                <?php else: ?>
                    <p class="small muted mb-5">No openings in the next few weeks.</p>
                <?php endif; ?>

                <a class="btn btn--primary btn--block btn--lg" href="#services">Choose a service</a>

                <?php if ($photoCount > 0): ?>
                    <a class="btn btn--ghost btn--block mt-2" href="<?= e($portfolioUrl) ?>">
                        <?= icon('image') ?> See their work (<?= (int) $photoCount ?>)
                    </a>
                <?php endif; ?>

                <?php if (!empty($artist['cancellation_policy'])): ?>
                    <p class="tiny muted mt-4"><?= e($artist['cancellation_policy']) ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Working hours -->
            <div class="divider mt-6" style="padding-top: var(--sp-5);">
                <p class="eyebrow mb-3">Working hours</p>
                <dl class="stack-sm">
                    <?php foreach ($schedule as $day): ?>
                        <div class="summary-line" style="padding: var(--sp-1) 0;">
                            <dt><?= e($day['short']) ?></dt>
                            <dd class="<?= $day['closed'] ? 'muted' : '' ?>">
                                <?= $day['closed'] ? 'Closed' : e(implode(', ', $day['windows'])) ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </aside>
    </div>
</div>

<!-- ============================= Service menu ============================= -->
<section class="section--tight" id="services">
    <div class="container">
        <div class="section-head" style="margin-bottom: var(--sp-5);">
            <h2 class="heading-lg">Service menu</h2>
        </div>

        <?php if ($services === []): ?>
            <?= view_partial('empty', [
                'icon'    => 'file',
                'title'   => 'No services listed',
                'message' => 'This artist has not added any bookable services yet.',
            ]) ?>
        <?php else: ?>
            <div class="card">
                <?php foreach ($services as $service): ?>
                    <div class="service-row">
                        <div>
                            <p class="service-row__name"><?= e($service['name']) ?></p>
                            <?php if (!empty($service['description'])): ?>
                                <p class="service-row__desc"><?= e($service['description']) ?></p>
                            <?php endif; ?>
                            <p class="service-row__meta">
                                <?= e(duration_label((int) $service['duration_min'])) ?>
                                <?php if (!empty($service['category_name'])): ?>
                                    &middot; <?= e($service['category_name']) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="service-row__side">
                            <span class="service-row__price"><?= e(money((float) $service['price'])) ?></span>
                            <a class="btn btn--secondary btn--sm"
                               href="<?= e(url('/book/' . (int) $service['id'])) ?>">Book</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================= Portfolio ============================= -->
<section class="section--tight" id="portfolio">
    <div class="container">
        <div class="section-head" style="margin-bottom: var(--sp-5);">
            <h2 class="heading-lg">Portfolio</h2>
            <?php if ($photoCount > count($portfolio)): ?>
                <a class="link-arrow" href="<?= e($portfolioUrl) ?>">
                    See all <?= (int) $photoCount ?> photos
                </a>
            <?php elseif ($photoCount > 0): ?>
                <a class="link-arrow" href="<?= e($portfolioUrl) ?>">Open the gallery</a>
            <?php endif; ?>
        </div>

        <?php if ($portfolio === []): ?>
            <?= view_partial('empty', [
                'icon'    => 'image',
                'title'   => 'No photos yet',
                'message' => 'This artist has not uploaded any work yet. Their service menu and reviews are below.',
            ]) ?>
        <?php else: ?>
            <div class="gallery">
                <?php foreach ($portfolio as $item): ?>
                    <?php $full = upload_url($item['image_path']); ?>
                    <figure class="gallery__item">
                        <?php /* Plain link first: the photo opens even with JavaScript off. */ ?>
                        <a href="<?= e($full) ?>" target="_blank" rel="noopener"
                           data-lightbox
                           data-caption="<?= e($item['caption'] ?? '') ?>"
                           aria-label="View this photo full size">
                            <img src="<?= e($full) ?>"
                                 alt="<?= e($item['caption'] ?: 'Work by ' . $artist['display_name']) ?>"
                                 loading="lazy" decoding="async">
                        </a>
                        <?php if (!empty($item['caption'])): ?>
                            <figcaption class="gallery__caption"><?= e($item['caption']) ?></figcaption>
                        <?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================= Reviews ============================= -->
<section class="section" id="reviews">
    <div class="container">
        <div class="section-head" style="margin-bottom: var(--sp-6);">
            <h2 class="heading-lg">Reviews</h2>
            <?php if ($reviewCount > count($reviews)): ?>
                <a class="link-arrow" href="<?= e(url('/artists/' . $artist['slug'] . '/reviews')) ?>">
                    Read all <?= (int) $reviewCount ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($reviewCount === 0): ?>
            <?= view_partial('empty', [
                'icon'    => 'star',
                'title'   => 'No reviews yet',
                'message' => 'Reviews appear here once clients complete an appointment. Only people who actually booked can leave one.',
            ]) ?>
        <?php else: ?>
            <div class="card mb-6">
                <div class="rating-summary">
                    <div class="text-center">
                        <p class="rating-summary__score"><?= e(number_format($rating, 1)) ?></p>
                        <?= stars($rating, false) ?>
                        <p class="tiny muted mt-2"><?= e(plural($reviewCount, 'review')) ?></p>
                    </div>

                    <div>
                        <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                            <?php
                            $count   = $distribution[$star] ?? 0;
                            $percent = $reviewCount > 0 ? round(($count / $reviewCount) * 100) : 0;
                            ?>
                            <div class="rating-bar">
                                <span class="muted"><?= $star ?>★</span>
                                <span class="rating-bar__track">
                                    <span class="rating-bar__fill" style="width: <?= (int) $percent ?>%;"></span>
                                </span>
                                <span class="muted numeric"><?= (int) $count ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <?php foreach ($reviews as $review): ?>
                    <?= view_partial('review', ['review' => $review]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

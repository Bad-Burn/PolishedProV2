<?php
/**
 * Artist result card, used on the home page, discover page and saved list.
 *
 * @var array<string, mixed> $artist
 * @var array<int, int> $favoriteIds
 * @var bool $showFavorite
 */

declare(strict_types=1);

use App\Models\Artist;

$artist       = $artist ?? [];
$favoriteIds  = $favoriteIds ?? [];
$showFavorite = $showFavorite ?? true;

$artistId  = (int) ($artist['id'] ?? 0);
$isFav     = in_array($artistId, $favoriteIds, true);
$profile   = url('/artists/' . ($artist['slug'] ?? ''));
$rating    = (float) ($artist['rating_avg'] ?? 0);
$reviews   = (int) ($artist['rating_count'] ?? 0);
$priceFrom = $artist['price_from'] ?? null;
$photos    = (int) ($artist['photo_count'] ?? 0);

/* Prefer the cover, but a portfolio photo beats an initials tile: a client
   scanning the directory should see actual work wherever there is any. */
$media = $artist['cover_path'] ?? null;
if (empty($media)) {
    $media = $artist['first_photo'] ?? null;
}

/* Deterministic tint per artist so cards without a photo still look composed
   and each artist keeps the same colour between visits. */
$hues  = [['#f2e3d5', '#e6ccd2'], ['#e4e6d8', '#d5dfd8'], ['#efe0e4', '#ddd2e4'],
          ['#f4e6d2', '#e8d5c4'], ['#dfe4ea', '#d3dbe6'], ['#eee2dc', '#e0cfc8']];
$tint  = $hues[$artistId % count($hues)];
?>
<article class="artist-card">
    <div class="artist-card__media">
        <?php if (!empty($media)): ?>
            <img src="<?= e(upload_url($media)) ?>" alt="" loading="lazy" decoding="async">
        <?php else: ?>
            <div class="artist-card__media--placeholder"
                 style="--tint: linear-gradient(150deg, <?= e($tint[0]) ?>, <?= e($tint[1]) ?>);">
                <?= e(initials($artist['display_name'] ?? $artist['full_name'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <div class="artist-card__flags">
            <?php if (!empty($artist['is_verified'])): ?>
                <span class="badge"><?= icon('badge-check') ?> Verified</span>
            <?php endif; ?>
            <?php if (!empty($artist['plan_badge'])): ?>
                <span class="badge"><?= e($artist['plan_badge']) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($showFavorite): ?>
            <button type="button"
                    class="artist-card__fav <?= $isFav ? 'is-on' : '' ?>"
                    style="position:absolute; z-index:2;"
                    data-favorite="<?= e(url('/artists/' . $artistId . '/favorite')) ?>"
                    data-login-url="<?= e(url('/login')) ?>"
                    aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                    aria-label="<?= $isFav ? 'Remove from saved artists' : 'Save this artist' ?>">
                <?= icon('heart') ?>
            </button>
        <?php endif; ?>
    </div>

    <div class="artist-card__body">
        <h3 class="artist-card__name">
            <a href="<?= e($profile) ?>"><?= e($artist['display_name'] ?? $artist['full_name'] ?? 'Artist') ?></a>
        </h3>

        <?php if (!empty($artist['headline'])): ?>
            <p class="artist-card__meta"><?= e(excerpt($artist['headline'], 68)) ?></p>
        <?php endif; ?>

        <p class="artist-card__meta">
            <?= e(trim(($artist['area'] ?? '') . ($artist['area'] && $artist['city'] ? ', ' : '') . ($artist['city'] ?? ''), ', ')) ?: 'Metro Manila' ?>
        </p>

        <?php if ($photos > 0 && !empty($artist['slug'])): ?>
            <a class="artist-card__portfolio"
               href="<?= e(url('/artists/' . $artist['slug'] . '/portfolio')) ?>">
                <?= icon('image') ?> <?= e(plural($photos, 'photo')) ?> of their work
            </a>
        <?php endif; ?>

        <div class="artist-card__foot">
            <span class="row row--tight">
                <?php if ($reviews > 0): ?>
                    <?= stars($rating) ?>
                    <span class="tiny muted">(<?= (int) $reviews ?>)</span>
                <?php else: ?>
                    <span class="tiny muted">New to PolishedPro</span>
                <?php endif; ?>
            </span>

            <?php if ($priceFrom !== null): ?>
                <span class="artist-card__price"><small>from</small> <?= e(money((float) $priceFrom)) ?></span>
            <?php endif; ?>
        </div>
    </div>
</article>

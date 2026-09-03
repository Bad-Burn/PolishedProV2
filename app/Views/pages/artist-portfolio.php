<?php
/**
 * Public portfolio gallery for one artist.
 *
 * The profile page carries a preview; this is the whole thing, on a URL a
 * client can bookmark or share. Every photo that is linked to a service
 * carries a Book button, because "I want that one" is the whole point of
 * looking at a portfolio.
 *
 * @var array<string, mixed> $artist
 * @var array<int, array<string, mixed>> $portfolio
 */

declare(strict_types=1);

layout('app');

$profileUrl = url('/artists/' . $artist['slug']);
$location   = trim(($artist['area'] ?? '') . (($artist['area'] && $artist['city']) ? ', ' : '') . ($artist['city'] ?? ''), ', ');
?>
<div class="container section--tight">
    <a class="link-back small muted" href="<?= e($profileUrl) ?>">
        <?= icon('arrow-left') ?> Back to <?= e($artist['display_name']) ?>
    </a>

    <div class="section-head mt-6" style="margin-bottom: var(--sp-6);">
        <div class="row" style="align-items:center; gap: var(--sp-4); min-width:0;">
            <?= view_partial('avatar', ['user' => $artist, 'size' => 'lg']) ?>
            <div style="min-width:0;">
                <h1 class="heading-lg"><?= e($artist['display_name']) ?>&rsquo;s portfolio</h1>
                <p class="muted small">
                    <?= e(plural(count($portfolio), 'photo')) ?>
                    <?php if ($location !== ''): ?>
                        &middot; <?= e($location) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <a class="btn btn--primary" href="<?= e($profileUrl . '#services') ?>">Book an appointment</a>
    </div>

    <?php if ($portfolio === []): ?>
        <?= view_partial('empty', [
            'icon'    => 'image',
            'title'   => 'No photos yet',
            'message' => 'This artist has not uploaded any work yet. Their service menu and reviews are still on their profile.',
        ]) ?>
    <?php else: ?>
        <div class="gallery gallery--lg">
            <?php foreach ($portfolio as $item): ?>
                <?php $full = upload_url($item['image_path']); ?>
                <figure class="gallery__item">
                    <?php /* A plain link, so the full photo opens even with JavaScript off. */ ?>
                    <a href="<?= e($full) ?>" target="_blank" rel="noopener"
                       data-lightbox
                       data-caption="<?= e($item['caption'] ?? '') ?>"
                       aria-label="View this photo full size">
                        <img src="<?= e($full) ?>"
                             alt="<?= e($item['caption'] ?: 'Work by ' . $artist['display_name']) ?>"
                             loading="lazy" decoding="async">
                    </a>

                    <?php if (!empty($item['caption']) || !empty($item['service_name'])): ?>
                        <figcaption class="gallery__caption">
                            <?php if (!empty($item['caption'])): ?>
                                <span><?= e($item['caption']) ?></span>
                            <?php endif; ?>

                            <?php if (!empty($item['service_name']) && (int) $item['service_active'] === 1): ?>
                                <a class="gallery__book"
                                   href="<?= e(url('/book/' . (int) $item['service_id'])) ?>">
                                    Book <?= e(excerpt($item['service_name'], 28)) ?>
                                    &middot; <?= e(money((float) $item['service_price'])) ?>
                                </a>
                            <?php elseif (!empty($item['service_name'])): ?>
                                <span class="tiny"><?= e($item['service_name']) ?></span>
                            <?php endif; ?>
                        </figcaption>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>

        <p class="muted small mt-6 text-center">
            Photos are uploaded by the artist and show their own work.
            <a href="<?= e($profileUrl) ?>">See their service menu and reviews</a>.
        </p>
    <?php endif; ?>
</div>

<?php
/**
 * A single review, with the artist's reply if there is one.
 *
 * @var array<string, mixed> $review
 */

declare(strict_types=1);

$review = $review ?? [];
?>
<article class="review">
    <header class="review__head">
        <?= view_partial('avatar', [
            'user' => ['full_name' => $review['customer_name'] ?? '', 'avatar_path' => $review['customer_avatar'] ?? null],
            'size' => 'sm',
        ]) ?>

        <div style="min-width:0;">
            <p class="strong small"><?= e($review['customer_name'] ?? 'Client') ?></p>
            <p class="tiny muted">
                <?= e(relative_time($review['created_at'] ?? null)) ?>
                <?php if (!empty($review['service_name'])): ?>
                    &middot; <?= e($review['service_name']) ?>
                <?php endif; ?>
            </p>
        </div>

        <span class="spacer"></span>
        <?= stars((float) ($review['rating'] ?? 0), false) ?>
    </header>

    <?php if (!empty($review['comment'])): ?>
        <p class="review__body"><?= e($review['comment']) ?></p>
    <?php endif; ?>

    <?php if (!empty($review['artist_reply'])): ?>
        <div class="review__reply">
            <p class="tiny eyebrow mb-2">Reply from the artist</p>
            <p><?= e($review['artist_reply']) ?></p>
        </div>
    <?php endif; ?>
</article>

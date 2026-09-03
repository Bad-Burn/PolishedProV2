<?php
/**
 * @var array<int, array<string, mixed>> $reviews
 * @var int $savedCount
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-customer', ['savedCount' => $savedCount]);
end_section();
?>
<header class="dash__head">
    <div>
        <h1>My reviews</h1>
        <p>Reviews you have left after completed appointments.</p>
    </div>
</header>

<?php if ($reviews === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'star',
        'title'   => 'No reviews yet',
        'message' => 'After an appointment is marked complete you can rate it from the booking page.',
        'actionLabel' => 'View bookings',
        'actionUrl'   => url('/account'),
    ]) ?>
<?php else: ?>
    <div class="card">
        <?php foreach ($reviews as $review): ?>
            <article class="review">
                <header class="review__head">
                    <div style="min-width:0;">
                        <p class="strong small">
                            <a href="<?= e(url('/artists/' . $review['slug'])) ?>"><?= e($review['display_name']) ?></a>
                        </p>
                        <p class="tiny muted">
                            <?= e($review['service_name']) ?> &middot; <?= e(date_label($review['starts_at'])) ?>
                        </p>
                    </div>
                    <span class="spacer"></span>
                    <?= stars((float) $review['rating'], false) ?>
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
        <?php endforeach; ?>
    </div>
<?php endif; ?>

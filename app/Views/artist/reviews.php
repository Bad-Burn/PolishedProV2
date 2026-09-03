<?php
/**
 * @var array<int, array<string, mixed>> $reviews
 * @var array<int, int> $distribution
 * @var array<string, mixed> $profile, $plan
 * @var int $usedSlots
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();

$total = array_sum($distribution);
?>
<header class="dash__head">
    <div>
        <h1>Reviews</h1>
        <p>Only clients who completed an appointment can leave one.</p>
    </div>
    <?php if ($total > 0): ?>
        <span class="row row--tight">
            <?= stars((float) $profile['rating_avg']) ?>
            <span class="muted small">(<?= (int) $total ?>)</span>
        </span>
    <?php endif; ?>
</header>

<?php if ($reviews === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'star',
        'title'   => 'No reviews yet',
        'message' => 'Complete a few appointments and your clients will be prompted to rate them.',
    ]) ?>
<?php else: ?>
    <div class="card">
        <?php foreach ($reviews as $review): ?>
            <article class="review">
                <header class="review__head">
                    <?= view_partial('avatar', [
                        'user' => ['full_name' => $review['customer_name'], 'avatar_path' => $review['customer_avatar']],
                        'size' => 'sm',
                    ]) ?>

                    <div style="min-width:0;">
                        <p class="strong small"><?= e($review['customer_name']) ?></p>
                        <p class="tiny muted">
                            <?= e($review['service_name']) ?> &middot; <?= e(relative_time($review['created_at'])) ?>
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
                        <p class="tiny eyebrow mb-2">Your reply &middot; <?= e(relative_time($review['replied_at'])) ?></p>
                        <p><?= e($review['artist_reply']) ?></p>
                    </div>
                <?php else: ?>
                    <details class="mt-4">
                        <summary class="btn btn--ghost btn--sm" style="cursor:pointer; list-style:none;">
                            Reply publicly
                        </summary>

                        <form class="mt-3" method="post"
                              action="<?= e(url('/artist/reviews/' . (int) $review['id'] . '/reply')) ?>" data-once>
                            <?= csrf_field() ?>

                            <div class="field">
                                <label class="sr-only" for="reply-<?= (int) $review['id'] ?>">Your reply</label>
                                <textarea class="textarea" id="reply-<?= (int) $review['id'] ?>" name="reply"
                                          maxlength="800" required style="min-height:5rem;"
                                          placeholder="Thanks for coming in — see you next time."></textarea>
                                <p class="field__hint">Your reply is public and cannot be edited afterwards. Keep it gracious, especially with a critical review.</p>
                            </div>

                            <button class="btn btn--primary btn--sm" type="submit">Post reply</button>
                        </form>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

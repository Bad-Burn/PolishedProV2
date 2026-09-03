<?php
/**
 * @var array<string, mixed> $artist
 * @var array<int, array<string, mixed>> $reviews
 * @var array<int, int> $distribution
 * @var int $total, $page, $pages
 */

declare(strict_types=1);

layout('app');
?>
<div class="container container--narrow section--tight">
    <a class="link-back small muted mb-6" href="<?= e(url('/artists/' . $artist['slug'])) ?>">
        <?= icon('arrow-left') ?> Back to profile
    </a>

    <h1 class="display-2 mt-4 mb-2">Reviews for <?= e($artist['display_name']) ?></h1>
    <p class="muted mb-8"><?= e(plural($total, 'review')) ?> from completed appointments.</p>

    <?php if ($reviews === []): ?>
        <?= view_partial('empty', [
            'icon'    => 'star',
            'title'   => 'No reviews yet',
            'message' => 'Once clients complete an appointment they can leave a review here.',
        ]) ?>
    <?php else: ?>
        <div class="card">
            <?php foreach ($reviews as $review): ?>
                <?= view_partial('review', ['review' => $review]) ?>
            <?php endforeach; ?>
        </div>

        <?= view_partial('pagination', ['page' => $page, 'pages' => $pages]) ?>
    <?php endif; ?>
</div>

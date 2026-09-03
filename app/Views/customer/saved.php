<?php
/**
 * @var array<int, array<string, mixed>> $artists
 * @var array<int, int> $favoriteIds
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
        <h1>Saved artists</h1>
        <p>The people you keep coming back to.</p>
    </div>
    <a class="btn btn--secondary" href="<?= e(url('/artists')) ?>">Find more</a>
</header>

<?php if ($artists === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'heart',
        'title'   => 'Nothing saved yet',
        'message' => 'Tap the heart on any artist to keep them here for later.',
        'actionLabel' => 'Browse artists',
        'actionUrl'   => url('/artists'),
    ]) ?>
<?php else: ?>
    <div class="grid grid--auto">
        <?php foreach ($artists as $artist): ?>
            <?= view_partial('artist-card', ['artist' => $artist, 'favoriteIds' => $favoriteIds]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

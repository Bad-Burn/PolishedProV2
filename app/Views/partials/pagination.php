<?php
/**
 * Page links that preserve the current query string.
 *
 * @var int $page
 * @var int $pages
 */

declare(strict_types=1);

use App\Core\Url;

$page  = max(1, (int) ($page ?? 1));
$pages = max(1, (int) ($pages ?? 1));

if ($pages <= 1) {
    return;
}

// Show a window around the current page rather than every number.
$window = 2;
$from   = max(1, $page - $window);
$to     = min($pages, $page + $window);
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="<?= e(Url::withQuery(['page' => $page - 1])) ?>" rel="prev" aria-label="Previous page">‹</a>
    <?php else: ?>
        <span class="is-disabled" aria-hidden="true">‹</span>
    <?php endif; ?>

    <?php if ($from > 1): ?>
        <a href="<?= e(Url::withQuery(['page' => 1])) ?>">1</a>
        <?php if ($from > 2): ?><span class="pagination__gap">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $from; $i <= $to; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="is-current" aria-current="page"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= e(Url::withQuery(['page' => $i])) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($to < $pages): ?>
        <?php if ($to < $pages - 1): ?><span class="pagination__gap">…</span><?php endif; ?>
        <a href="<?= e(Url::withQuery(['page' => $pages])) ?>"><?= $pages ?></a>
    <?php endif; ?>

    <?php if ($page < $pages): ?>
        <a href="<?= e(Url::withQuery(['page' => $page + 1])) ?>" rel="next" aria-label="Next page">›</a>
    <?php else: ?>
        <span class="is-disabled" aria-hidden="true">›</span>
    <?php endif; ?>
</nav>

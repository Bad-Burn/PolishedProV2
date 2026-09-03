<?php
/**
 * @var array<int, array<string, mixed>> $reviews
 * @var int $pendingArtists
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-admin', ['pendingArtists' => $pendingArtists]);
end_section();
?>
<header class="dash__head">
    <div>
        <h1>Reviews</h1>
        <p>Hiding a review removes it from the artist's public rating. It is not deleted.</p>
    </div>
</header>

<?php if ($reviews === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'star',
        'title'   => 'No reviews yet',
        'message' => 'Reviews appear once clients complete appointments.',
    ]) ?>
<?php else: ?>
    <div class="table-wrap">
        <div class="scroll-x">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Rating</th>
                        <th scope="col">Review</th>
                        <th scope="col">Client</th>
                        <th scope="col">Artist</th>
                        <th scope="col">Posted</th>
                        <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $review): ?>
                        <tr style="<?= (int) $review['is_visible'] === 0 ? 'opacity:.55;' : '' ?>">
                            <td class="nowrap" data-label="Rating"><?= stars((float) $review['rating'], false) ?></td>
                            <td style="max-width: 24rem;" data-label="Review">
                                <p class="table__sub"><?= e(excerpt($review['comment'], 160) ?: '— no comment —') ?></p>
                                <?php if ((int) $review['is_visible'] === 0): ?>
                                    <span class="badge badge--critical badge--dot mt-2">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap" data-label="Client"><?= e($review['customer_name']) ?></td>
                            <td class="nowrap" data-label="Artist">
                                <?php if (!empty($review['slug'])): ?>
                                    <a href="<?= e(url('/artists/' . $review['slug'])) ?>"><?= e($review['artist_name']) ?></a>
                                <?php else: ?>
                                    <?= e($review['artist_name'] ?? '—') ?>
                                <?php endif; ?>
                            </td>
                            <td class="muted nowrap" data-label="Posted"><?= e(date_label($review['created_at'])) ?></td>
                            <td class="table__actions">
                                <form method="post" action="<?= e(url('/admin/reviews/' . (int) $review['id'] . '/visibility')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn--ghost btn--sm" type="submit">
                                        <?= (int) $review['is_visible'] === 1 ? 'Hide' : 'Restore' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

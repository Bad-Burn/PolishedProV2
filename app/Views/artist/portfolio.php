<?php
/**
 * @var array<int, array<string, mixed>> $items, $services
 * @var array{allowed:bool, used:int, limit:?int, message:?string} $allowance
 * @var array<string, mixed> $plan
 * @var int $usedSlots
 * @var string|null $publicUrl        public gallery, or null until approved
 * @var string|null $publicProfileUrl public profile, or null until approved
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();
?>
<header class="dash__head">
    <div>
        <h1>Portfolio</h1>
        <p>Your work, in your own photos. This is what convinces people to book.</p>
    </div>
    <span class="badge <?= $allowance['allowed'] ? '' : 'badge--caution' ?>">
        <?= (int) $allowance['used'] ?> / <?= $allowance['limit'] === null ? 'unlimited' : (int) $allowance['limit'] ?> photos
    </span>
</header>

<?php /* Artists kept asking where these photos end up. Now the page says so. */ ?>
<div class="alert mb-6">
    <?= icon('image') ?>
    <span>
        <?php if ($publicUrl !== null): ?>
            <strong>Clients see these on your public profile.</strong>
            Every photo you add here appears under <em>Portfolio</em> on your profile, in your
            gallery page, and as the picture on your card in search results.
            <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Open your public gallery</a>
            or <a href="<?= e($publicProfileUrl) ?>" target="_blank" rel="noopener">view your profile</a>
            to see exactly what they see.
        <?php else: ?>
            <strong>These photos go live once an admin approves your account.</strong>
            After that they appear under <em>Portfolio</em> on your public profile, on your own
            gallery page, and as the picture on your card in search results. Upload them now so
            your profile is ready.
        <?php endif; ?>
    </span>
</div>

<?php if (!$allowance['allowed']): ?>
    <div class="alert alert--warning mb-6">
        <?= icon('alert') ?>
        <span>
            <?= e($allowance['message']) ?>
            <a href="<?= e(url('/artist/subscription')) ?>">Compare plans</a>.
        </span>
    </div>
<?php endif; ?>

<div class="split">
    <div>
        <?php if ($items === []): ?>
            <?= view_partial('empty', [
                'icon'    => 'image',
                'title'   => 'No photos yet',
                'message' => 'Upload a few of your best results. Clear, well-lit photos of finished work do the most work.',
            ]) ?>
        <?php else: ?>
            <div class="gallery">
                <?php foreach ($items as $item): ?>
                    <?php $full = upload_url($item['image_path']); ?>
                    <figure class="gallery__item">
                        <a href="<?= e($full) ?>" target="_blank" rel="noopener"
                           data-lightbox data-caption="<?= e($item['caption'] ?? '') ?>"
                           aria-label="View this photo full size">
                            <img src="<?= e($full) ?>"
                                 alt="<?= e($item['caption'] ?: 'Portfolio photo') ?>"
                                 loading="lazy" decoding="async">
                        </a>

                        <?php if (!empty($item['caption'])): ?>
                            <figcaption class="gallery__caption">
                                <?= e($item['caption']) ?>
                                <?php if (!empty($item['service_name'])): ?>
                                    <span class="tiny">&middot; <?= e($item['service_name']) ?></span>
                                <?php endif; ?>
                            </figcaption>
                        <?php elseif (!empty($item['service_name'])): ?>
                            <figcaption class="gallery__caption">
                                <span class="tiny"><?= e($item['service_name']) ?></span>
                            </figcaption>
                        <?php endif; ?>

                        <form method="post" action="<?= e(url('/artist/portfolio/' . (int) $item['id'] . '/delete')) ?>"
                              style="position:absolute; top:.5rem; right:.5rem;">
                            <?= csrf_field() ?>
                            <button class="artist-card__fav" type="submit" aria-label="Delete this photo"
                                    style="position:static;">
                                <?= icon('trash') ?>
                            </button>
                        </form>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <aside class="card split__aside">
        <h2 class="heading-md mb-5">Add a photo</h2>

        <form method="post" action="<?= e(url('/artist/portfolio')) ?>" enctype="multipart/form-data" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="field__label" for="image">Photo</label>
                <input class="input" type="file" id="image" name="image"
                       accept="image/jpeg,image/png,image/webp" required
                       <?= has_error('image') ? 'aria-invalid="true"' : '' ?>>
                <p class="field__hint">JPG, PNG or WebP, up to 5&nbsp;MB. We resize and strip location data automatically.</p>
                <?= field_error('image') ?>
            </div>

            <div class="field">
                <label class="field__label" for="caption">Caption <span class="optional">optional</span></label>
                <input class="input" type="text" id="caption" name="caption" maxlength="180"
                       placeholder="Chrome French on almond tips">
            </div>

            <div class="field">
                <label class="field__label" for="service_id">Related service <span class="optional">optional</span></label>
                <select class="select" id="service_id" name="service_id">
                    <option value="">Not linked</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= (int) $service['id'] ?>"><?= e($service['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn btn--primary btn--block" type="submit"
                    data-busy-text="Uploading…" <?= $allowance['allowed'] ? '' : 'disabled' ?>>
                Upload photo
            </button>
        </form>
    </aside>
</div>

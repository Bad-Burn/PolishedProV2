<?php
/**
 * A hero collage tile: a photograph with a labelled caption.
 *
 * The tint gradient stays underneath the photo, so the tile is already the
 * right colour while the image is still downloading — which on a phone over
 * mobile data is most of the time you actually see this page.
 *
 * WebP first, JPEG for anything that cannot take it; the browser picks the
 * 480w file on a phone and the 960w on a wide screen, so nobody downloads a
 * desktop-sized photo onto a handset.
 *
 * @var string $name  base filename under assets/img/showcase, e.g. "nails"
 * @var string $label caption shown on the tile
 * @var string $tint  CSS gradient used until the photo paints
 * @var bool   $eager load immediately (use for tiles above the fold)
 */

declare(strict_types=1);

$name  = $name  ?? '';
$label = $label ?? '';
$tint  = $tint  ?? 'linear-gradient(160deg,#efe2d8,#e3cdd2)';
$eager = $eager ?? false;

$base = 'assets/img/showcase/' . $name;
?>
<figure class="swatch" style="--tint: <?= e($tint) ?>;">
    <picture>
        <source type="image/webp"
                srcset="<?= e(asset($base . '-480.webp')) ?> 480w, <?= e(asset($base . '-960.webp')) ?> 960w"
                sizes="(max-width: 60rem) 45vw, 22rem">
        <img class="swatch__img"
             src="<?= e(asset($base . '-480.jpg')) ?>"
             srcset="<?= e(asset($base . '-480.jpg')) ?> 480w, <?= e(asset($base . '-960.jpg')) ?> 960w"
             sizes="(max-width: 60rem) 45vw, 22rem"
             alt=""
             width="480" height="640"
             decoding="async"
             <?= $eager ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
    </picture>

    <figcaption class="swatch__label"><?= e($label) ?></figcaption>
</figure>

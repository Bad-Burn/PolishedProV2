<?php
/**
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, array<string, mixed>> $trending
 */

declare(strict_types=1);

layout('app');
?>
<section class="section--tight">
    <div class="container">
        <header class="mb-10">
            <p class="eyebrow mb-3">Browse</p>
            <h1 class="display-1 mb-4">What are you after?</h1>
            <p class="lede">Pick a service and we will show you the artists who do it.</p>
        </header>

        <div class="grid grid--3">
            <?php foreach ($categories as $category): ?>
                <a class="card" href="<?= e(url('/artists', ['category' => $category['slug']])) ?>"
                   style="text-decoration:none; color:inherit; display:block;">
                    <div class="row row--between mb-3">
                        <h2 class="heading-md"><?= e($category['name']) ?></h2>
                        <span class="badge"><?= (int) $category['artist_count'] ?></span>
                    </div>
                    <p class="muted small"><?= e($category['description'] ?? '') ?></p>
                    <p class="link-arrow small mt-4">Browse <?= e(strtolower($category['name'])) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($trending !== []): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow mb-2">Popular right now</p>
                <h2 class="display-2">Most booked services</h2>
            </div>
        </div>

        <div class="card">
            <?php foreach ($trending as $service): ?>
                <div class="service-row">
                    <div>
                        <p class="service-row__name"><?= e($service['name']) ?></p>
                        <p class="service-row__desc">
                            <a href="<?= e(url('/artists/' . $service['slug'])) ?>"><?= e($service['display_name']) ?></a>
                            <?php if (!empty($service['city'])): ?>
                                &middot; <?= e($service['city']) ?>
                            <?php endif; ?>
                        </p>
                        <p class="service-row__meta">
                            <?= e(duration_label((int) $service['duration_min'])) ?>
                            <?php if (!empty($service['category_name'])): ?>
                                &middot; <?= e($service['category_name']) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="service-row__side">
                        <span class="service-row__price"><?= e(money((float) $service['price'])) ?></span>
                        <a class="btn btn--secondary btn--sm" href="<?= e(url('/book/' . (int) $service['id'])) ?>">Book</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

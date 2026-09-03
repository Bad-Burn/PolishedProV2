<?php
/**
 * Discovery page: filter sidebar plus a results grid.
 *
 * @var array{rows:array, total:int, pages:int, page:int} $results
 * @var array<string, mixed> $filters
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, array<string, mixed>> $cities
 * @var array<int, int> $favoriteIds
 * @var bool $hasFilters
 */

declare(strict_types=1);

use App\Core\Url;

layout('app');

$sorts = [
    'recommended' => 'Recommended',
    'rating'      => 'Highest rated',
    'reviews'     => 'Most reviewed',
    'price_low'   => 'Price: low to high',
    'price_high'  => 'Price: high to low',
    'newest'      => 'Newest first',
];
?>
<div class="container section--tight">
    <header class="mb-8">
        <h1 class="display-2 mb-2">
            <?php if ($filters['q'] !== ''): ?>
                Results for &ldquo;<?= e($filters['q']) ?>&rdquo;
            <?php elseif ($filters['category'] !== ''): ?>
                <?= e(ucfirst(str_replace('-', ' ', $filters['category']))) ?>
            <?php else: ?>
                Browse artists
            <?php endif; ?>
        </h1>
        <p class="muted"><?= e(plural($results['total'], 'artist')) ?> available<?= $filters['city'] !== '' ? ' in ' . e($filters['city']) : '' ?>.</p>
    </header>

    <div class="discover">
        <!-- ------------------------- Filters ------------------------- -->
        <aside class="filters">
            <form method="get" action="<?= e(url('/artists')) ?>" data-autosubmit>
                <?php if ($filters['sort'] !== 'recommended'): ?>
                    <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
                <?php endif; ?>

                <div class="filters__group">
                    <label class="field__label" for="f-q">Search</label>
                    <div class="input-group">
                        <?= icon('search') ?>
                        <input class="input" type="search" id="f-q" name="q"
                               value="<?= e($filters['q']) ?>" placeholder="Service or artist"
                               data-search-input>
                    </div>
                </div>

                <div class="filters__group">
                    <p class="filters__title">Service</p>
                    <select class="select" name="category" aria-label="Service category">
                        <option value="">All services</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filters__group">
                    <p class="filters__title">Location</p>
                    <select class="select" name="city" aria-label="City">
                        <option value="">Anywhere</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= e($city['city']) ?>" <?= $filters['city'] === $city['city'] ? 'selected' : '' ?>>
                                <?= e($city['city']) ?> (<?= (int) $city['total'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filters__group">
                    <p class="filters__title">Minimum rating</p>
                    <select class="select" name="min_rating" aria-label="Minimum rating">
                        <option value="">Any rating</option>
                        <?php foreach ([4.5, 4.0, 3.5] as $value): ?>
                            <option value="<?= e((string) $value) ?>" <?= (float) $filters['min_rating'] === $value ? 'selected' : '' ?>>
                                <?= e(number_format($value, 1)) ?> and above
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filters__group">
                    <p class="filters__title">Budget</p>
                    <select class="select" name="max_price" aria-label="Maximum price">
                        <option value="">Any price</option>
                        <?php foreach ([500, 1000, 2000, 5000] as $value): ?>
                            <option value="<?= $value ?>" <?= (int) $filters['max_price'] === $value ? 'selected' : '' ?>>
                                Has a service under <?= e(money($value, true)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filters__group">
                    <p class="filters__title">Options</p>
                    <div class="stack-sm">
                        <label class="checkbox">
                            <input type="checkbox" name="verified" value="1" <?= $filters['verified'] ? 'checked' : '' ?>>
                            <span>Verified artists only</span>
                        </label>
                        <label class="checkbox">
                            <input type="checkbox" name="instant" value="1" <?= $filters['instant'] ? 'checked' : '' ?>>
                            <span>Instant booking</span>
                        </label>
                    </div>
                </div>

                <noscript>
                    <button class="btn btn--primary btn--block mt-4" type="submit">Apply filters</button>
                </noscript>
            </form>

            <?php if ($hasFilters): ?>
                <a class="chip-clear mt-4" href="<?= e(url('/artists')) ?>">
                    <?= icon('x') ?> Clear all filters
                </a>
            <?php endif; ?>
        </aside>

        <!-- ------------------------- Results ------------------------- -->
        <div>
            <div class="result-bar">
                <p class="small muted numeric">
                    <?php
                    $perPage = 12;
                    $from = $results['total'] === 0 ? 0 : (($results['page'] - 1) * $perPage) + 1;
                    $to   = min($results['page'] * $perPage, $results['total']);
                    ?>
                    Showing <?= (int) $from ?>–<?= (int) $to ?> of <?= (int) $results['total'] ?>
                </p>

                <form method="get" data-autosubmit>
                    <?php foreach (['q', 'category', 'city', 'min_rating', 'max_price'] as $carry): ?>
                        <?php if (!empty($filters[$carry])): ?>
                            <input type="hidden" name="<?= e($carry) ?>" value="<?= e((string) $filters[$carry]) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($filters['verified']): ?><input type="hidden" name="verified" value="1"><?php endif; ?>
                    <?php if ($filters['instant']): ?><input type="hidden" name="instant" value="1"><?php endif; ?>

                    <label class="sr-only" for="sort">Sort results</label>
                    <select class="select" id="sort" name="sort" style="width:auto;">
                        <?php foreach ($sorts as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($results['rows'] === []): ?>
                <?= view_partial('empty', [
                    'icon'    => 'search',
                    'title'   => 'No artists match those filters',
                    'message' => 'Try widening your search — remove the location or budget filter and see what comes up.',
                    'actionLabel' => $hasFilters ? 'Clear filters' : null,
                    'actionUrl'   => $hasFilters ? url('/artists') : null,
                ]) ?>
            <?php else: ?>
                <div class="grid grid--auto">
                    <?php foreach ($results['rows'] as $artist): ?>
                        <?= view_partial('artist-card', ['artist' => $artist, 'favoriteIds' => $favoriteIds]) ?>
                    <?php endforeach; ?>
                </div>

                <?= view_partial('pagination', ['page' => $results['page'], 'pages' => $results['pages']]) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

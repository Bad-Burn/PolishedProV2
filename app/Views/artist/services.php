<?php
/**
 * @var array<int, array<string, mixed>> $services, $categories
 * @var array{allowed:bool, used:int, limit:?int, message:?string} $allowance
 * @var array<string, mixed>|null $editing
 * @var array<string, mixed> $plan
 * @var int $usedSlots
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();

$isEditing = $editing !== null;
$action    = $isEditing ? url('/artist/services/' . (int) $editing['id']) : url('/artist/services');
?>
<header class="dash__head">
    <div>
        <h1>Services</h1>
        <p>Your price list. Clients book these directly, so keep the durations honest.</p>
    </div>
    <span class="badge <?= $allowance['allowed'] ? '' : 'badge--caution' ?>">
        <?= (int) $allowance['used'] ?> / <?= $allowance['limit'] === null ? 'unlimited' : (int) $allowance['limit'] ?> active
    </span>
</header>

<?php if (!$allowance['allowed']): ?>
    <div class="alert alert--warning mb-6">
        <?= icon('alert') ?>
        <span>
            <strong>You have used every active service slot on your plan.</strong>
            <?= e($allowance['message']) ?>
            <a href="<?= e(url('/artist/subscription')) ?>">Compare plans</a>.
        </span>
    </div>
<?php endif; ?>

<div class="split">
    <!-- ------------------------- List ------------------------- -->
    <div>
        <?php if ($services === []): ?>
            <?= view_partial('empty', [
                'icon'    => 'scissors',
                'title'   => 'No services yet',
                'message' => 'Add your first service using the form. Clients cannot book you until you have at least one.',
            ]) ?>
        <?php else: ?>
            <div class="table-wrap">
                <div class="scroll-x">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Service</th>
                                <th scope="col">Duration</th>
                                <th scope="col" class="table__num">Price</th>
                                <th scope="col" class="table__num">Booked</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td data-label="Service">
                                        <p class="table__primary"><?= e($service['name']) ?></p>
                                        <?php if (!empty($service['category_name'])): ?>
                                            <p class="table__sub"><?= e($service['category_name']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="nowrap" data-label="Duration">
                                        <?= e(duration_label((int) $service['duration_min'])) ?>
                                        <?php if ((int) $service['buffer_min'] > 0): ?>
                                            <span class="table__sub">+<?= (int) $service['buffer_min'] ?>m buffer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table__num" data-label="Price"><?= e(money((float) $service['price'])) ?></td>
                                    <td class="table__num" data-label="Booked"><?= (int) $service['bookings_count'] ?></td>
                                    <td data-label="Status">
                                        <?php if ((int) $service['is_active'] === 1): ?>
                                            <span class="badge badge--positive badge--dot">Live</span>
                                        <?php else: ?>
                                            <span class="badge badge--dot">Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table__actions">
                                        <div class="row row--tight row--end row--nowrap">
                                            <a class="btn btn--ghost btn--sm"
                                               href="<?= e(url('/artist/services', ['edit' => (int) $service['id']])) ?>">Edit</a>

                                            <form method="post" action="<?= e(url('/artist/services/' . (int) $service['id'] . '/toggle')) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn--ghost btn--sm" type="submit">
                                                    <?= (int) $service['is_active'] === 1 ? 'Hide' : 'Publish' ?>
                                                </button>
                                            </form>

                                            <details class="confirm">
                                                <summary class="btn btn--ghost btn--sm" style="color: var(--critical);">Delete</summary>
                                                <div class="confirm__panel">
                                                    <p>Delete &ldquo;<?= e($service['name']) ?>&rdquo;? Past bookings keep their details.</p>
                                                    <form method="post" action="<?= e(url('/artist/services/' . (int) $service['id'] . '/delete')) ?>">
                                                        <?= csrf_field() ?>
                                                        <button class="btn btn--danger btn--block btn--sm" type="submit">Yes, delete it</button>
                                                    </form>
                                                </div>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ------------------------- Form ------------------------- -->
    <aside class="card split__aside">
        <h2 class="heading-md mb-5"><?= $isEditing ? 'Edit service' : 'Add a service' ?></h2>

        <form method="post" action="<?= e($action) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="field__label" for="name">Name</label>
                <input class="input" type="text" id="name" name="name" maxlength="140" required
                       placeholder="Structured gel manicure"
                       value="<?= old('name', $editing['name'] ?? '') ?>"
                       <?= has_error('name') ? 'aria-invalid="true"' : '' ?>>
                <?= field_error('name') ?>
            </div>

            <div class="field">
                <label class="field__label" for="description">Description <span class="optional">optional</span></label>
                <textarea class="textarea" id="description" name="description" maxlength="2000"
                          placeholder="What is included, what to expect, anything the client should bring."><?= old('description', $editing['description'] ?? '') ?></textarea>
                <p class="field__hint"><span data-counter-for="description"></span></p>
                <?= field_error('description') ?>
            </div>

            <div class="field">
                <label class="field__label" for="category_id">Category</label>
                <select class="select" id="category_id" name="category_id">
                    <option value="">Uncategorised</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= (int) old_raw('category_id', $editing['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field__hint">Helps clients find you when they filter by service.</p>
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="field__label" for="duration_min">Duration (min)</label>
                    <input class="input" type="number" id="duration_min" name="duration_min"
                           min="5" max="600" step="5" required
                           value="<?= old('duration_min', $editing['duration_min'] ?? 60) ?>"
                           <?= has_error('duration_min') ? 'aria-invalid="true"' : '' ?>>
                    <?= field_error('duration_min') ?>
                </div>

                <div class="field">
                    <label class="field__label" for="buffer_min">Clean-up (min)</label>
                    <input class="input" type="number" id="buffer_min" name="buffer_min"
                           min="0" max="180" step="5"
                           value="<?= old('buffer_min', $editing['buffer_min'] ?? 0) ?>">
                    <p class="field__hint">Blocked after each appointment.</p>
                    <?= field_error('buffer_min') ?>
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="price">Price</label>
                <div class="input-prefix">
                    <span class="input-prefix__label">₱</span>
                    <input class="input" type="number" id="price" name="price"
                           min="0" step="0.01" required
                           value="<?= old('price', $editing['price'] ?? '') ?>"
                           <?= has_error('price') ? 'aria-invalid="true"' : '' ?>>
                </div>
                <p class="field__hint">You keep all of it. We take no commission.</p>
                <?= field_error('price') ?>
            </div>

            <label class="checkbox mb-5">
                <input type="checkbox" name="is_active" value="1"
                    <?= ((int) old_raw('is_active', $editing['is_active'] ?? 1)) === 1 ? 'checked' : '' ?>>
                <span>
                    <strong>Show on my profile</strong>
                    Only live services count towards your plan limit.
                </span>
            </label>

            <div class="row">
                <button class="btn btn--primary" type="submit" data-busy-text="Saving…">
                    <?= $isEditing ? 'Save changes' : 'Add service' ?>
                </button>
                <?php if ($isEditing): ?>
                    <a class="btn btn--ghost" href="<?= e(url('/artist/services')) ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </aside>
</div>

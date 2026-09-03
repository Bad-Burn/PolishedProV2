<?php
/**
 * @var array{rows:array, total:int, pages:int, page:int} $results
 * @var array<string, mixed> $filters
 * @var string $status
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
        <h1>Bookings</h1>
        <p>
            <?= e(number_format($results['total'])) ?> matching appointments.
            <span class="muted">Prices are between the artist and their client and are not shown here.</span>
        </p>
    </div>
</header>

<form class="filter-bar" method="get" action="<?= e(url('/admin/bookings')) ?>">
    <div class="field filter-bar__grow">
        <label class="field__label" for="q">Search</label>
        <div class="input-group">
            <?= icon('search') ?>
            <input class="input" type="search" id="q" name="q" value="<?= e($filters['search']) ?>"
                   placeholder="Reference, client, artist or service">
        </div>
    </div>

    <div class="field">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">Any status</option>
            <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'declined', 'no_show'] as $value): ?>
                <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>>
                    <?= e(ucfirst(str_replace('_', ' ', $value))) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label class="field__label" for="from">From</label>
        <input class="input" type="date" id="from" name="from" value="<?= e($filters['from']) ?>">
    </div>

    <div class="field">
        <label class="field__label" for="to">To</label>
        <input class="input" type="date" id="to" name="to" value="<?= e($filters['to']) ?>">
    </div>

    <button class="btn btn--secondary" type="submit">Filter</button>
    <a class="btn btn--ghost" href="<?= e(url('/admin/bookings')) ?>">Clear</a>
</form>

<?php if ($results['rows'] === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'calendar',
        'title'   => 'No bookings match',
        'message' => 'Try widening the date range or clearing the status filter.',
    ]) ?>
<?php else: ?>
    <div class="table-wrap">
        <div class="scroll-x">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">When</th>
                        <th scope="col">Client</th>
                        <th scope="col">Artist</th>
                        <th scope="col">Service</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results['rows'] as $booking): ?>
                        <tr>
                            <td class="numeric table__primary nowrap" data-label="Reference"><?= e($booking['reference']) ?></td>
                            <td class="nowrap" data-label="When">
                                <p><?= e(date_label($booking['starts_at'], 'M j, Y')) ?></p>
                                <p class="table__sub"><?= e(time_label($booking['starts_at'])) ?></p>
                            </td>
                            <td data-label="Client"><?= e($booking['customer_name']) ?></td>
                            <td data-label="Artist"><?= e($booking['display_name'] ?: $booking['artist_name']) ?></td>
                            <td class="muted" data-label="Service"><?= e(excerpt($booking['service_name'], 32)) ?></td>
                            <td data-label="Status"><?= view_partial('status-badge', ['status' => $booking['status']]) ?></td>
                            <td class="table__actions">
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('/bookings/' . (int) $booking['id'])) ?>">
                                    Open
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= view_partial('pagination', ['page' => $results['page'], 'pages' => $results['pages']]) ?>
<?php endif; ?>

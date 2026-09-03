<?php
/**
 * @var array{rows:array, total:int, pages:int, page:int} $results
 * @var array<string, mixed> $filters
 * @var array<int, string> $actions
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
        <h1>Sign-in activity</h1>
        <p>
            Append-only record of every sign-in and sign-out.
            <?= e(number_format($results['total'])) ?> entries.
        </p>
    </div>
</header>

<div class="alert mb-6">
    <?= icon('shield') ?>
    <span>
        This log covers account access only. What people do once they are signed in —
        bookings, payments, reviews — is between them and the artist, and is not reported here.
    </span>
</div>

<form class="filter-bar" method="get" action="<?= e(url('/admin/audit-log')) ?>">
    <div class="field filter-bar__grow">
        <label class="field__label" for="q">Search</label>
        <div class="input-group">
            <?= icon('search') ?>
            <input class="input" type="search" id="q" name="q" value="<?= e($filters['search']) ?>"
                   placeholder="Name of the person who signed in">
        </div>
    </div>

    <div class="field">
        <label class="field__label" for="action">Event</label>
        <select class="select" id="action" name="action">
            <option value="">Sign-ins and sign-outs</option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>>
                    <?= $action === 'user.signed_in' ? 'Signed in' : 'Signed out' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button class="btn btn--secondary" type="submit">Filter</button>
    <a class="btn btn--ghost" href="<?= e(url('/admin/audit-log')) ?>">Clear</a>
</form>

<?php if ($results['rows'] === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'shield',
        'title'   => 'No sign-ins logged',
        'message' => 'Sign-ins and sign-outs are recorded here as they happen.',
    ]) ?>
<?php else: ?>
    <div class="table-wrap">
        <div class="scroll-x">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Who</th>
                        <th scope="col">Event</th>
                        <th scope="col">Method</th>
                        <th scope="col">IP address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results['rows'] as $entry): ?>
                        <tr>
                            <td class="muted nowrap" data-label="When">
                                <p><?= e(date_label($entry['created_at'], 'M j, Y')) ?></p>
                                <p class="table__sub"><?= e(time_label($entry['created_at'])) ?></p>
                            </td>
                            <td data-label="Who">
                                <p class="table__primary"><?= e($entry['actor_name'] ?? 'System') ?></p>
                                <?php if (!empty($entry['actor_role'])): ?>
                                    <p class="table__sub"><?= e($entry['actor_role']) ?></p>
                                <?php endif; ?>
                            </td>
                            <?php $signedIn = $entry['action'] === 'user.signed_in'; ?>
                            <td data-label="Event">
                                <span class="badge <?= $signedIn ? 'badge--positive' : 'badge--outline' ?>">
                                    <?= $signedIn ? 'Signed in' : 'Signed out' ?>
                                </span>
                            </td>
                            <td class="table__sub nowrap" data-label="Method">
                                <?= str_contains((string) ($entry['meta'] ?? ''), 'two_factor')
                                    ? 'Password + 2FA'
                                    : ($signedIn ? 'Password' : '—') ?>
                            </td>
                            <td class="table__sub numeric nowrap" data-label="IP address"><?= e($entry['ip_address'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= view_partial('pagination', ['page' => $results['page'], 'pages' => $results['pages']]) ?>
<?php endif; ?>

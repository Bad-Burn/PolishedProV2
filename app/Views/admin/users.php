<?php
/**
 * @var array{rows:array, total:int, pages:int, page:int} $results
 * @var array<string, mixed> $filters
 * @var int $pendingArtists
 */

declare(strict_types=1);

use App\Core\Auth;

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-admin', ['pendingArtists' => $pendingArtists]);
end_section();
?>
<header class="dash__head">
    <div>
        <h1>Users</h1>
        <p><?= e(number_format($results['total'])) ?> accounts.</p>
    </div>

    <?php if ($pendingArtists > 0 && !$filters['awaiting_approval']): ?>
        <a class="btn btn--primary" href="<?= e(url('/admin/users', ['awaiting' => 1])) ?>">
            Review <?= (int) $pendingArtists ?> pending
        </a>
    <?php endif; ?>
</header>

<form class="filter-bar" method="get" action="<?= e(url('/admin/users')) ?>">
    <div class="field filter-bar__grow">
        <label class="field__label" for="q">Search</label>
        <div class="input-group">
            <?= icon('search') ?>
            <input class="input" type="search" id="q" name="q" value="<?= e($filters['search']) ?>"
                   placeholder="Name or email">
        </div>
    </div>

    <div class="field">
        <label class="field__label" for="role">Role</label>
        <select class="select" id="role" name="role">
            <option value="">All roles</option>
            <?php foreach (['customer' => 'Clients', 'artist' => 'Artists', 'admin' => 'Admins'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label class="field__label" for="status">Status</label>
        <select class="select" id="status" name="status">
            <option value="">Any status</option>
            <?php foreach (['active' => 'Active', 'suspended' => 'Suspended'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($filters['awaiting_approval']): ?>
        <input type="hidden" name="awaiting" value="1">
    <?php endif; ?>

    <button class="btn btn--secondary" type="submit">Filter</button>

    <?php if ($filters['search'] !== '' || $filters['role'] !== '' || $filters['status'] !== '' || $filters['awaiting_approval']): ?>
        <a class="btn btn--ghost" href="<?= e(url('/admin/users')) ?>">Clear</a>
    <?php endif; ?>
</form>

<?php if ($results['rows'] === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'users',
        'title'   => 'No users match',
        'message' => 'Try clearing the filters.',
    ]) ?>
<?php else: ?>
    <div class="table-wrap">
        <div class="scroll-x">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">User</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Joined</th>
                        <th scope="col">Last seen</th>
                        <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results['rows'] as $user): ?>
                        <?php
                        $isArtist  = $user['role'] === 'artist';
                        $isPending = $isArtist && empty($user['approved_at']);
                        $isSelf    = (int) $user['id'] === Auth::id();
                        ?>
                        <tr>
                            <td data-label="User">
                                <div class="person">
                                    <?= view_partial('avatar', ['user' => $user, 'size' => 'sm']) ?>
                                    <div class="person__text">
                                        <p class="person__name">
                                            <?= e($user['display_name'] ?: $user['full_name']) ?>
                                            <?php if (!empty($user['is_verified'])): ?>
                                                <span class="badge badge--positive"><?= icon('badge-check') ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="person__meta"><?= e($user['email']) ?></p>
                                    </div>
                                </div>
                            </td>

                            <td data-label="Role">
                                <span class="badge badge--outline"><?= e(ucfirst((string) $user['role'])) ?></span>
                            </td>

                            <td data-label="Status">
                                <?php if ($isPending): ?>
                                    <span class="badge badge--caution badge--dot">Awaiting approval</span>
                                <?php else: ?>
                                    <?= view_partial('status-badge', ['status' => $user['status']]) ?>
                                <?php endif; ?>
                            </td>

                            <td class="muted nowrap" data-label="Joined"><?= e(date_label($user['created_at'])) ?></td>
                            <td class="muted nowrap" data-label="Last seen"><?= e($user['last_login_at'] ? relative_time($user['last_login_at']) : 'Never') ?></td>

                            <td class="table__actions">
                                <div class="row row--tight row--end row--nowrap">
                                    <?php if ($isPending): ?>
                                        <form method="post" action="<?= e(url('/admin/users/' . (int) $user['id'] . '/approve')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--primary btn--sm" type="submit">Approve</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isArtist && !$isPending): ?>
                                        <form method="post" action="<?= e(url('/admin/users/' . (int) $user['id'] . '/verify')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--ghost btn--sm" type="submit">
                                                <?= !empty($user['is_verified']) ? 'Unverify' : 'Verify' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!$isSelf && $user['role'] !== 'admin'): ?>
                                        <form method="post" action="<?= e(url('/admin/users/' . (int) $user['id'] . '/status')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="status"
                                                   value="<?= $user['status'] === 'active' ? 'suspended' : 'active' ?>">
                                            <button class="btn btn--ghost btn--sm" type="submit">
                                                <?= $user['status'] === 'active' ? 'Suspend' : 'Reactivate' ?>
                                            </button>
                                        </form>

                                        <details class="confirm">
                                            <summary class="btn btn--ghost btn--sm" style="color: var(--critical);">Delete</summary>
                                            <div class="confirm__panel">
                                                <p>
                                                    Permanently delete <strong><?= e($user['full_name']) ?></strong>?
                                                    Their bookings, services and reviews go too. This cannot be undone.
                                                </p>
                                                <form method="post" action="<?= e(url('/admin/users/' . (int) $user['id'] . '/delete')) ?>">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn--danger btn--block btn--sm" type="submit">
                                                        Yes, delete this account
                                                    </button>
                                                </form>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?= view_partial('pagination', ['page' => $results['page'], 'pages' => $results['pages']]) ?>
<?php endif; ?>

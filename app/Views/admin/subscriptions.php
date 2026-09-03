<?php
/**
 * @var array{rows:array, total:int, pages:int, page:int} $results
 * @var array<string, mixed> $filters
 * @var array<int, array<string, mixed>> $plans, $planMix
 * @var float $mrr
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
        <h1>Subscriptions</h1>
        <p><?= e(number_format($results['total'])) ?> records.</p>
    </div>
</header>

<div class="stats">
    <div class="stat">
        <p class="stat__label"><?= icon('wallet') ?> Monthly recurring revenue</p>
        <p class="stat__value"><?= e(money_compact($mrr)) ?></p>
        <p class="stat__delta muted">Active PayPal subscriptions only</p>
    </div>

    <?php foreach ($planMix as $row): ?>
        <div class="stat">
            <p class="stat__label"><?= icon('sparkle') ?> <?= e($row['name']) ?></p>
            <p class="stat__value"><?= (int) $row['subscribers'] ?></p>
            <p class="stat__delta muted">
                <?= (float) $row['price_monthly'] > 0 ? e(money((float) $row['price_monthly'])) . ' / month' : 'Free tier' ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>

<div class="split">
    <div>
        <form class="filter-bar" method="get" action="<?= e(url('/admin/subscriptions')) ?>">
            <div class="field">
                <label class="field__label" for="status">Status</label>
                <select class="select" id="status" name="status">
                    <option value="">Any status</option>
                    <?php foreach (['active', 'pending', 'past_due', 'cancelled', 'expired'] as $value): ?>
                        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>>
                            <?= e(ucfirst(str_replace('_', ' ', $value))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="field__label" for="plan_id">Plan</label>
                <select class="select" id="plan_id" name="plan_id">
                    <option value="">All plans</option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= (int) $plan['id'] ?>" <?= (int) $filters['plan_id'] === (int) $plan['id'] ? 'selected' : '' ?>>
                            <?= e($plan['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn btn--secondary" type="submit">Filter</button>
            <a class="btn btn--ghost" href="<?= e(url('/admin/subscriptions')) ?>">Clear</a>
        </form>

        <?php if ($results['rows'] === []): ?>
            <?= view_partial('empty', [
                'icon'    => 'wallet',
                'title'   => 'No subscriptions match',
                'message' => 'Try clearing the filters.',
            ]) ?>
        <?php else: ?>
            <div class="table-wrap">
                <div class="scroll-x">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Artist</th>
                                <th scope="col">Plan</th>
                                <th scope="col">Source</th>
                                <th scope="col">Period ends</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['rows'] as $row): ?>
                                <tr>
                                    <td data-label="Artist">
                                        <p class="table__primary"><?= e($row['display_name'] ?: $row['full_name']) ?></p>
                                        <p class="table__sub"><?= e($row['email']) ?></p>
                                    </td>
                                    <td data-label="Plan">
                                        <span class="badge badge--outline"><?= e($row['plan_name']) ?></span>
                                        <p class="table__sub"><?= e(ucfirst((string) $row['billing_cycle'])) ?></p>
                                    </td>
                                    <td class="muted" data-label="Source"><?= e(ucfirst((string) $row['provider'])) ?></td>
                                    <td class="muted nowrap" data-label="Period ends">
                                        <?= e(date_label($row['current_period_end'])) ?>
                                        <?php if ((int) $row['cancel_at_period_end'] === 1): ?>
                                            <p class="table__sub text-caution">Cancelling</p>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status"><?= view_partial('status-badge', ['status' => $row['status']]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?= view_partial('pagination', ['page' => $results['page'], 'pages' => $results['pages']]) ?>
        <?php endif; ?>
    </div>

    <!-- Manual grant -->
    <aside class="card split__aside">
        <h2 class="heading-md mb-2">Grant a plan</h2>
        <p class="muted small mb-5">
            For comped accounts or artists who paid you another way. Recorded in the audit log
            as a manual grant, so it is distinguishable from a PayPal subscription.
        </p>

        <form method="post" action="<?= e(url('/admin/subscriptions/grant')) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="field__label" for="user_id">Artist user ID</label>
                <input class="input" type="number" id="user_id" name="user_id" min="1" required>
                <p class="field__hint">Find it in the Users list.</p>
            </div>

            <div class="field">
                <label class="field__label" for="grant_plan">Plan</label>
                <select class="select" id="grant_plan" name="plan_id" required>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= (int) $plan['id'] ?>"><?= e($plan['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="field__label" for="months">Duration (months)</label>
                <input class="input" type="number" id="months" name="months" min="1" max="24" value="1" required>
            </div>

            <button class="btn btn--primary btn--block" type="submit">Grant plan</button>
        </form>
    </aside>
</div>

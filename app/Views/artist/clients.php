<?php
/**
 * @var array<int, array<string, mixed>> $clients
 * @var array<string, mixed> $plan
 * @var int $usedSlots
 */

declare(strict_types=1);

layout('dashboard');

section('sidebar');
echo view_partial('sidebar-artist', ['plan' => $plan, 'usedSlots' => $usedSlots]);
end_section();
?>
<header class="dash__head">
    <div>
        <h1>Clients</h1>
        <p>Everyone who has booked with you, most frequent first. Open a client to see their
           full booking history.</p>
    </div>
</header>

<?php if ($clients === []): ?>
    <?= view_partial('empty', [
        'icon'    => 'users',
        'title'   => 'No clients yet',
        'message' => 'Once someone books with you they will appear here, along with their visit history.',
    ]) ?>
<?php else: ?>
    <div class="table-wrap">
        <div class="scroll-x">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Client</th>
                        <th scope="col" class="table__num">Visits</th>
                        <th scope="col" class="table__num">Total spent</th>
                        <th scope="col">Last visit</th>
                        <th scope="col">Contact</th>
                        <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td data-label="Client">
                                <div class="person">
                                    <?= view_partial('avatar', ['user' => $client, 'size' => 'sm']) ?>
                                    <div class="person__text">
                                        <p class="person__name">
                                            <a href="<?= e(url('/artist/clients/' . (int) $client['id'])) ?>">
                                                <?= e($client['full_name']) ?>
                                            </a>
                                        </p>
                                        <?php if ((int) $client['visits'] >= 3): ?>
                                            <p class="person__meta">Regular</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="table__num" data-label="Visits"><?= (int) $client['visits'] ?></td>
                            <td class="table__num" data-label="Total spent"><?= e(money((float) $client['total_spent'])) ?></td>
                            <td class="nowrap muted" data-label="Last visit"><?= e(date_label($client['last_visit'])) ?></td>
                            <td data-label="Contact">
                                <p class="table__sub"><?= e($client['email']) ?></p>
                                <?php if (!empty($client['phone'])): ?>
                                    <p class="table__sub"><?= e($client['phone']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="table__actions">
                                <a class="btn btn--secondary btn--sm"
                                   href="<?= e(url('/artist/clients/' . (int) $client['id'])) ?>">
                                    <?= icon('eye') ?> View details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="tiny muted mt-4">
        This list is yours. We never contact your clients on your behalf or sell this data.
    </p>
<?php endif; ?>

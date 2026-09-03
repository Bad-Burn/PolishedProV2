<?php
/**
 * @var array<int, array<int, array<string, mixed>>> $week
 * @var array<int, string> $weekdays
 * @var array<int, array<string, mixed>> $blocks
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
        <h1>Schedule</h1>
        <p>Clients can only book inside these hours — and never over an existing appointment.</p>
    </div>
</header>

<!-- ------------------------- Weekly hours ------------------------- -->
<section class="card mb-8">
    <h2 class="heading-md mb-1">Working hours</h2>
    <p class="muted small mb-6">
        Leave a day empty to close it. Add a second window for a split shift — say, a lunch break in between.
    </p>

    <form method="post" action="<?= e(url('/artist/schedule')) ?>" data-once>
        <?= csrf_field() ?>

        <?php foreach ($weekdays as $index => $name): ?>
            <?php $windows = $week[$index] ?? []; ?>
            <div class="schedule-day">
                <p class="schedule-day__name"><?= e($name) ?></p>

                <div>
                    <div class="schedule-day__windows" data-windows="<?= (int) $index ?>">
                        <?php if ($windows === []): ?>
                            <?php /* One empty row so the day can be opened without JavaScript. */ ?>
                            <div class="time-range">
                                <input class="input" type="time" name="hours[<?= (int) $index ?>][0][start]"
                                       aria-label="<?= e($name) ?> opening time">
                                <span class="time-range__sep">to</span>
                                <input class="input" type="time" name="hours[<?= (int) $index ?>][0][end]"
                                       aria-label="<?= e($name) ?> closing time">
                                <button class="btn btn--ghost btn--icon btn--sm" type="button"
                                        data-remove-window aria-label="Remove this window">
                                    <?= icon('x') ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($windows as $i => $window): ?>
                                <div class="time-range">
                                    <input class="input" type="time" name="hours[<?= (int) $index ?>][<?= $i ?>][start]"
                                           value="<?= e(substr((string) $window['start_time'], 0, 5)) ?>"
                                           aria-label="<?= e($name) ?> opening time">
                                    <span class="time-range__sep">to</span>
                                    <input class="input" type="time" name="hours[<?= (int) $index ?>][<?= $i ?>][end]"
                                           value="<?= e(substr((string) $window['end_time'], 0, 5)) ?>"
                                           aria-label="<?= e($name) ?> closing time">
                                    <button class="btn btn--ghost btn--icon btn--sm" type="button"
                                            data-remove-window aria-label="Remove this window">
                                        <?= icon('x') ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn--ghost btn--sm mt-2" type="button" data-add-window="<?= (int) $index ?>">
                        <?= icon('plus') ?> Add another window
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <button class="btn btn--primary mt-6" type="submit" data-busy-text="Saving…">Save working hours</button>
    </form>
</section>

<!-- ------------------------- Time off ------------------------- -->
<section class="split">
    <div>
        <h2 class="heading-md mb-1">Time off</h2>
        <p class="muted small mb-5">Holidays, sick days, or an afternoon you need back.</p>

        <?php if ($blocks === []): ?>
            <?= view_partial('empty', [
                'icon'    => 'calendar',
                'title'   => 'No time off booked',
                'message' => 'Add a date to close it off, and nobody can book you then.',
            ]) ?>
        <?php else: ?>
            <div class="table-wrap">
                <div class="scroll-x">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Hours</th>
                                <th scope="col">Reason</th>
                                <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocks as $block): ?>
                                <tr>
                                    <td class="table__primary nowrap"><?= e(date_label($block['block_date'], 'D, M j')) ?></td>
                                    <td class="nowrap">
                                        <?php if ($block['start_time'] === null): ?>
                                            <span class="badge">All day</span>
                                        <?php else: ?>
                                            <?= e(date('g:i A', strtotime((string) $block['start_time']))) ?>
                                            &ndash;
                                            <?= e(date('g:i A', strtotime((string) $block['end_time']))) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="muted"><?= e($block['reason'] ?: '—') ?></td>
                                    <td class="table__actions">
                                        <form method="post" action="<?= e(url('/artist/schedule/blocks/' . (int) $block['id'] . '/delete')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--ghost btn--sm" type="submit">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <aside class="card split__aside">
        <h2 class="heading-md mb-5">Add time off</h2>

        <form method="post" action="<?= e(url('/artist/schedule/blocks')) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="field__label" for="block_date">Date</label>
                <input class="input" type="date" id="block_date" name="block_date"
                       min="<?= e(date('Y-m-d')) ?>" required
                       <?= has_error('block_date') ? 'aria-invalid="true"' : '' ?>>
                <?= field_error('block_date') ?>
            </div>

            <label class="checkbox mb-4">
                <input type="checkbox" name="full_day" value="1" checked>
                <span>Closed all day</span>
            </label>

            <div class="field-row">
                <div class="field">
                    <label class="field__label" for="start_time">From</label>
                    <input class="input" type="time" id="start_time" name="start_time">
                </div>
                <div class="field">
                    <label class="field__label" for="end_time">To</label>
                    <input class="input" type="time" id="end_time" name="end_time">
                </div>
            </div>
            <p class="field__hint mb-4">Only used when &ldquo;closed all day&rdquo; is unticked.</p>

            <div class="field">
                <label class="field__label" for="reason">Reason <span class="optional">optional</span></label>
                <input class="input" type="text" id="reason" name="reason" maxlength="160"
                       placeholder="Public holiday">
            </div>

            <button class="btn btn--primary btn--block" type="submit">Add time off</button>
        </form>
    </aside>
</section>

<!-- Template cloned by app.js when adding a window. -->
<template id="window-template">
    <div class="time-range">
        <input class="input" type="time" name="hours[__DAY__][99][start]" aria-label="Opening time">
        <span class="time-range__sep">to</span>
        <input class="input" type="time" name="hours[__DAY__][99][end]" aria-label="Closing time">
        <button class="btn btn--ghost btn--icon btn--sm" type="button" data-remove-window aria-label="Remove this window">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18"/>
            </svg>
        </button>
    </div>
</template>

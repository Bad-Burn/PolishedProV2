<?php
/**
 * Booking / subscription status pill.
 *
 * @var string $status
 */

declare(strict_types=1);

$status = (string) ($status ?? 'pending');

$labels = [
    'pending'   => 'Awaiting confirmation',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'declined'  => 'Declined',
    'no_show'   => 'No-show',
    'active'    => 'Active',
    'past_due'  => 'Payment overdue',
    'expired'   => 'Expired',
    'suspended' => 'Suspended',
];
?>
<span class="badge badge--dot badge--<?= e(status_tone($status)) ?>">
    <?= e($labels[$status] ?? ucfirst(str_replace('_', ' ', $status))) ?>
</span>

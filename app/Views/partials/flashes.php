<?php
/**
 * Toast-style flash messages. Errors persist until dismissed; the rest fade.
 *
 * @var array<int, array{type:string, message:string}> $flashes
 */

declare(strict_types=1);

$flashes = $flashes ?? [];

if ($flashes === []) {
    return;
}

$iconFor = [
    'success' => 'check-circle',
    'error'   => 'alert',
    'warning' => 'alert',
    'info'    => 'info',
];
?>
<div class="flash-stack" role="status" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
        <?php
        $type = in_array($flash['type'] ?? 'info', ['success', 'error', 'warning', 'info'], true)
            ? $flash['type']
            : 'info';
        ?>
        <div class="alert alert--<?= e($type) ?>">
            <?= icon($iconFor[$type]) ?>
            <span><?= e($flash['message']) ?></span>
            <button class="flash__close" type="button" data-dismiss aria-label="Dismiss">&times;</button>
        </div>
    <?php endforeach; ?>
</div>

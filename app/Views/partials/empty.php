<?php
/**
 * Empty state with an optional call to action.
 *
 * @var string $icon
 * @var string $title
 * @var string $message
 * @var string|null $actionLabel
 * @var string|null $actionUrl
 */

declare(strict_types=1);
?>
<div class="empty">
    <div class="empty__icon"><?= icon($icon ?? 'inbox') ?></div>
    <h3 class="heading-sm"><?= e($title ?? 'Nothing here yet') ?></h3>
    <p><?= e($message ?? '') ?></p>

    <?php if (!empty($actionUrl) && !empty($actionLabel)): ?>
        <a class="btn btn--primary" href="<?= e($actionUrl) ?>"><?= e($actionLabel) ?></a>
    <?php endif; ?>
</div>

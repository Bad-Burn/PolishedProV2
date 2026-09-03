<?php
/**
 * @var int $status
 * @var string $message
 */

declare(strict_types=1);

layout('app');

$title = $status . ' — ' . match ($status) {
    404 => 'Page not found',
    403 => 'Not allowed',
    419 => 'Session expired',
    429 => 'Slow down',
    default => 'Something went wrong',
};
?>
<div class="container error-page">
    <p class="eyebrow">Error</p>
    <p class="error-page__code"><?= (int) $status ?></p>

    <h1 class="display-2 mb-4"><?php
        echo e(match ($status) {
            404 => 'We looked everywhere.',
            403 => 'That area is off limits.',
            419 => 'Your session expired.',
            429 => 'Too many attempts.',
            405 => 'That will not work here.',
            default => 'Something broke on our end.',
        });
    ?></h1>

    <p class="lede mb-8" style="margin-inline:auto;"><?= e($message) ?></p>

    <div class="row" style="justify-content:center;">
        <a class="btn btn--primary" href="<?= e(url('/')) ?>">Back to home</a>
        <a class="btn btn--secondary" href="<?= e(url('/artists')) ?>">Browse artists</a>
    </div>
</div>

<?php
/**
 * Signed-in layout: customer, artist and admin areas.
 *
 * The sidebar contents come from the `sidebar` section so each area declares
 * its own navigation without a second layout file.
 *
 * @var string $content
 * @var string|null $title
 */

declare(strict_types=1);

use App\Core\View;
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? 'Dashboard') . ' · ' . $appName) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="color-scheme" content="light dark">

<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..700&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/dashboard.css')) ?>">
<?= View::section('head') ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<?= view_partial('header') ?>

<div class="dash">
    <aside class="dash__sidebar">
        <?= View::section('sidebar') ?>
    </aside>

    <div class="dash__main" id="main">
        <?= view_partial('subscription-banner', [
            'access'      => View::shared('subscriptionAccess'),
            'currentPlan' => View::shared('currentPlan'),
        ]) ?>

        <?= $content ?>
    </div>
</div>

<?= view_partial('flashes') ?>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<?= View::section('scripts') ?>
</body>
</html>

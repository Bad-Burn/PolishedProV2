<?php
/**
 * Public-facing layout: marketing pages, discovery, artist profiles, auth.
 *
 * @var string $content
 * @var string|null $title
 * @var string|null $metaDescription
 */

declare(strict_types=1);

use App\Core\View;

$title = isset($title) ? $title . ' · ' . $appName : $appName . ' — book beauty professionals you can trust';
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($metaDescription ?? 'Find and book verified nail artists, hairstylists, make-up artists and more. Real availability, honest reviews, no phone tag.') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#fbf8f5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#141110" media="(prefers-color-scheme: dark)">

<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..700&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<?= View::section('head') ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<?= view_partial('header') ?>

<main id="main">
<?= $content ?>
</main>

<?= view_partial('footer') ?>
<?= view_partial('flashes') ?>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<?= View::section('scripts') ?>
</body>
</html>

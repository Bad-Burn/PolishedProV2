<?php
/**
 * Public-facing layout: marketing pages, discovery, artist profiles, auth.
 *
 * @var string $content
 * @var string|null $title
 * @var string|null $metaDescription
 * @var string|null $ogImage app-relative image URL for link previews
 */

declare(strict_types=1);

use App\Core\Url;
use App\Core\View;

$title       = isset($title) ? $title . ' · ' . $appName : $appName . ' — book beauty professionals you can trust';
$description = $metaDescription ?? 'Find and book verified nail artists, hairstylists, make-up artists and more. Real availability, honest reviews, no phone tag.';

// Link previews in Messenger, Instagram and Viber read these. The canonical
// URL drops the query string so filtered and tracked links fold into one page.
$canonical = Url::absoluteLink((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
$shareImage = Url::absoluteLink($ogImage ?? Url::to('/assets/img/showcase/studio-960.jpg'));
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e($appName) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($shareImage) ?>">
<meta property="og:locale" content="en_PH">
<meta name="twitter:card" content="summary_large_image">
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

<?php
/**
 * Avatar with an initials fallback — no broken image icons, no placeholder
 * service, and the initials tile stays legible in both themes.
 *
 * @var array<string, mixed> $user
 * @var string $size  '' | 'xs' | 'sm' | 'lg' | 'xl' | '2xl'
 */

declare(strict_types=1);

$user = $user ?? [];
$size = $size ?? '';

$name = (string) ($user['display_name'] ?? $user['full_name'] ?? $user['customer_name'] ?? '');
$path = $user['avatar_path'] ?? $user['customer_avatar'] ?? $user['artist_avatar'] ?? null;
$class = 'avatar' . ($size !== '' ? ' avatar--' . $size : '');
?>
<?php if (!empty($path)): ?>
    <img class="<?= e($class) ?>" src="<?= e(upload_url($path)) ?>" alt="" loading="lazy" decoding="async" width="80" height="80">
<?php else: ?>
    <span class="<?= e($class) ?>" aria-hidden="true"><?= e(initials($name)) ?></span>
<?php endif; ?>

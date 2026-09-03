<?php
/**
 * Picks the right dashboard navigation for the signed-in role, so shared
 * pages (settings, notifications) do not need to know who is looking.
 *
 * @var array<string, mixed>|null $currentUser
 */

declare(strict_types=1);

use App\Models\Favorite;
use App\Models\Subscription;
use App\Models\User;

$role = $currentUser['role'] ?? 'customer';
$id   = (int) ($currentUser['id'] ?? 0);

echo match ($role) {
    'admin'  => view_partial('sidebar-admin', [
        'pendingArtists' => User::countPendingArtists(),
    ]),
    'artist' => view_partial('sidebar-artist', [
        'plan'      => Subscription::planFor($id),
        'usedSlots' => \App\Models\Service::countActive($id),
    ]),
    default  => view_partial('sidebar-customer', [
        'savedCount' => Favorite::countFor($id),
    ]),
};

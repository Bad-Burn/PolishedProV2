<?php
/**
 * "Confirm your email" prompt with a resend button. Renders nothing for
 * guests and for accounts that are already confirmed, so it can be dropped
 * into any page that needs a confirmed address.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Services\EmailVerification;

$user = Auth::user();

if ($user === null || EmailVerification::isVerified($user)) {
    return;
}
?>
<div class="alert alert--warning mb-6">
    <?= icon('alert') ?>
    <span>
        <strong>Confirm your email address.</strong>
        We sent a link to <?= e($user['email']) ?>. You need to confirm it before you can book.
        <form method="post" action="<?= e(url('/verify-email/resend')) ?>" style="display:inline;" data-once>
            <?= csrf_field() ?>
            <button class="btn btn--ghost btn--sm" type="submit">Send it again</button>
        </form>
    </span>
</div>

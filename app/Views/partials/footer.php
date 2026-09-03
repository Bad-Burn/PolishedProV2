<?php
declare(strict_types=1);

use App\Models\Service;

$categories = Service::categories();
?>
<footer class="site-footer">
    <div class="container">
        <div class="site-footer__grid">
            <div>
                <a class="brand" href="<?= e(url('/')) ?>">Polished<em>Pro</em></a>
                <p class="muted mt-3" style="max-width: 30ch;">
                    A booking platform for independent beauty professionals in the Philippines.
                    Real availability, verified artists, no phone tag.
                </p>
            </div>

            <div>
                <h4>Browse</h4>
                <ul>
                    <li><a href="<?= e(url('/artists')) ?>">All artists</a></li>
                    <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                        <li><a href="<?= e(url('/artists', ['category' => $category['slug']])) ?>"><?= e($category['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <h4>For artists</h4>
                <ul>
                    <li><a href="<?= e(url('/for-artists')) ?>">Why PolishedPro</a></li>
                    <li><a href="<?= e(url('/pricing')) ?>">Plans &amp; pricing</a></li>
                    <li><a href="<?= e(url('/register', ['as' => 'artist'])) ?>">List your services</a></li>
                </ul>
            </div>

            <div>
                <h4>Support</h4>
                <ul>
                    <li><a href="<?= e(url('/help')) ?>">Help centre</a></li>
                    <li><a href="mailto:<?= e($supportEmail ?? 'support@polishedpro.test') ?>">Contact us</a></li>
                    <li><a href="<?= e(url('/terms')) ?>">Terms</a></li>
                    <li><a href="<?= e(url('/privacy')) ?>">Privacy</a></li>
                </ul>
            </div>
        </div>

        <div class="site-footer__base">
            <span>&copy; <?= date('Y') ?> <?= e($appName ?? 'PolishedPro') ?>. All rights reserved.</span>
            <span>Made in Metro Manila.</span>
        </div>
    </div>
</footer>

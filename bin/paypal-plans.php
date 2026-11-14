<?php

declare(strict_types=1);

/**
 * Create the PayPal billing plans for every paid tier and store their ids.
 *
 *   php bin/paypal-plans.php
 *
 * Run this once after filling in PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET.
 * PayPal plans are immutable, so changing a price later means running this
 * again to create new plans — existing subscribers keep their agreed price
 * until they cancel and re-subscribe.
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be run from the command line.');
}

require __DIR__ . '/_cli.php';

use App\Core\Config;
use App\Models\Plan;
use App\Services\PayPalClient;

cli_heading('PayPal billing plans');

if (!PayPalClient::isConfigured()) {
    cli_error('PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET are not set in .env');
    exit(1);
}

cli_line('Mode:     ' . PayPalClient::mode());
cli_line('Currency: ' . Config::get('paypal.currency', 'PHP'));
cli_line('');

if (PayPalClient::mode() === 'live') {
    cli_warn('You are pointed at LIVE PayPal. Real plans will be created.');
    if (!cli_confirm('Type "yes" to continue: ')) {
        cli_line('Aborted.');
        exit(0);
    }
}

try {
    cli_line('Creating the product…');
    $productId = PayPalClient::ensureProduct();
    cli_ok('Product: ' . $productId);
} catch (Throwable $e) {
    cli_error($e->getMessage());
    exit(1);
}

$created = 0;

foreach (Plan::all(true) as $plan) {
    if (!Plan::isPaid($plan)) {
        cli_line('Skipping ' . $plan['name'] . ' (free tier)');
        continue;
    }

    cli_line('');
    cli_line('Plan: ' . $plan['name']);

    $monthlyId = $plan['paypal_plan_id_monthly'] ?? null;
    $yearlyId  = $plan['paypal_plan_id_yearly'] ?? null;

    try {
        if (empty($monthlyId)) {
            $monthlyId = PayPalClient::createPlan(
                $productId,
                $plan['name'] . ' — Monthly',
                (string) ($plan['tagline'] ?: $plan['name'] . ' subscription'),
                (float) $plan['price_monthly'],
                'monthly'
            );
            cli_ok('  monthly: ' . $monthlyId);
            $created++;
        } else {
            cli_line('  monthly: already set (' . $monthlyId . ')');
        }

        if (empty($yearlyId) && (float) $plan['price_yearly'] > 0) {
            $yearlyId = PayPalClient::createPlan(
                $productId,
                $plan['name'] . ' — Yearly',
                (string) ($plan['tagline'] ?: $plan['name'] . ' subscription'),
                (float) $plan['price_yearly'],
                'yearly'
            );
            cli_ok('  yearly:  ' . $yearlyId);
            $created++;
        } else {
            cli_line('  yearly:  ' . ($yearlyId ? 'already set (' . $yearlyId . ')' : 'skipped, no yearly price'));
        }

        Plan::setPaypalPlanIds((int) $plan['id'], $monthlyId, $yearlyId);
    } catch (Throwable $e) {
        cli_error('  ' . $e->getMessage());
    }
}

cli_line('');
cli_ok($created === 0 ? 'Nothing to do — all plans already have PayPal ids.' : "Created {$created} PayPal plan(s).");

cli_line('');
cli_heading('Next step: the webhook');
cli_line('1. Go to developer.paypal.com → your app → Webhooks → Add webhook');
cli_line('2. URL:  ' . (Config::get('app.url') ?: 'https://yoursite') . '/webhooks/paypal');
cli_line('3. Subscribe to these events:');
foreach ((array) Config::get('paypal.events', []) as $event) {
    cli_line('     - ' . $event);
}
cli_line('4. Copy the webhook ID into PAYPAL_WEBHOOK_ID in your .env');
cli_line('');
cli_warn('Without PAYPAL_WEBHOOK_ID the app rejects every webhook, and renewals will not register.');

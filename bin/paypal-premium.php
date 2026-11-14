<?php

declare(strict_types=1);

/**
 * Create or reuse PolishedProV2's PHP 149/month PayPal Premium plan in Sandbox.
 * Usage: php bin/paypal-premium.php
 */

if (PHP_SAPI !== 'cli') {
    exit('This script may only be run from the command line.');
}

require __DIR__ . '/_cli.php';

use App\Core\Config;
use App\Models\Plan;
use App\Services\PayPalClient;

cli_heading('PolishedProV2 Premium · PayPal Sandbox');

try {
    PayPalClient::requireSandboxMode();
    $configuredPrice = trim((string) Config::get('paypal.monthly_price', '149.00'));
    if ($configuredPrice !== '149.00') {
        throw new RuntimeException('PAYPAL_MONTHLY_PRICE must be 149.00 for the requested Premium plan.');
    }
    $price = $configuredPrice;

    $plan = Plan::findByCode('premium');
    if ($plan === null || (int) ($plan['is_active'] ?? 0) !== 1) {
        throw new RuntimeException('Run php bin/migrate.php first. The active Premium plan is missing from the database.');
    }
    if (number_format((float) $plan['price_monthly'], 2, '.', '') !== $price
        || strtoupper((string) ($plan['currency'] ?? '')) !== 'PHP'
    ) {
        throw new RuntimeException('The local Premium plan must match PHP 149.00 before PayPal setup can continue.');
    }

    cli_line('Environment: Sandbox');
    cli_line('Plan:        PolishedProV2 Monthly Premium');
    cli_line('Price:       PHP ' . $price . ' / month');
    cli_line('');

    cli_line('Checking for the Sandbox product…');
    $productId = PayPalClient::ensurePremiumSandboxProduct();
    cli_ok('Product ID: ' . $productId);

    cli_line('Checking for an exact active plan…');
    $paypalPlanId = PayPalClient::ensurePremiumSandboxPlan($productId, $price);
    cli_ok('Active Plan ID: ' . $paypalPlanId);

    Plan::setPaypalMonthlyPlanId((int) $plan['id'], $paypalPlanId);
    $saved = write_paypal_env([
        'PAYPAL_PRODUCT_ID' => $productId,
        'PAYPAL_PLAN_ID' => $paypalPlanId,
        'PAYPAL_MONTHLY_PRICE' => $price,
    ]);

    cli_ok('The Premium plan ID is stored on the local plan row.');
    cli_ok($saved ? 'The product ID, plan ID, and price were saved to .env.' : 'IDs were not written to .env; add them using the instructions below.');
    cli_line('');
    cli_line('Product ID: ' . $productId);
    cli_line('Plan ID:    ' . $paypalPlanId);
    cli_line('');
    cli_warn('No Live resources were created. This command refuses to run unless PAYPAL_MODE=sandbox.');
    cli_line('Next: add the Sandbox webhook over HTTPS and set PAYPAL_WEBHOOK_ID in .env.');
} catch (Throwable $e) {
    cli_error($e->getMessage());
    exit(1);
}

/** Update only the named non-secret settings, preserving every other .env line. */
function write_paypal_env(array $values): bool
{
    $path = PP_ROOT . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($path) || !is_writable($path)) {
        return false;
    }

    foreach ($values as $key => $value) {
        if (preg_match('/^[A-Z0-9_-]+$/', (string) $value) !== 1
            && !($key === 'PAYPAL_MONTHLY_PRICE' && preg_match('/^\d+\.\d{2}$/', (string) $value) === 1)
        ) {
            throw new RuntimeException('PayPal returned an invalid identifier; .env was not changed.');
        }
    }

    $contents = (string) file_get_contents($path);
    foreach ($values as $key => $value) {
        $line = $key . '=' . $value;
        $pattern = '/^\s*' . preg_quote($key, '/') . '\s*=.*$/m';
        if (preg_match($pattern, $contents) === 1) {
            $contents = (string) preg_replace($pattern, $line, $contents, 1);
        } else {
            $contents = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
        }
    }

    return file_put_contents($path, $contents, LOCK_EX) !== false;
}

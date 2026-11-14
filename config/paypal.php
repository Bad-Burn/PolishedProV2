<?php

declare(strict_types=1);

use App\Core\Config;

/**
 * PayPal Subscriptions configuration.
 *
 * A sandbox account is free: create one at developer.paypal.com, make a REST
 * app, and paste the client id/secret into .env. Nothing here is required for
 * the rest of the app to run — when credentials are absent the billing screens
 * fall back to a clearly-labelled manual/offline activation flow.
 */
return [
    'mode' => Config::env('PAYPAL_MODE', 'sandbox'), // 'sandbox' | 'live'

    'client_id'     => Config::env('PAYPAL_CLIENT_ID', ''),
    'client_secret' => Config::env('PAYPAL_CLIENT_SECRET', ''),

    // From the webhook you create in the PayPal developer dashboard.
    'webhook_id' => Config::env('PAYPAL_WEBHOOK_ID', ''),

    // The dedicated Premium Sandbox product and plan. Product/plan IDs are
    // identifiers, not credentials; secrets stay in the server environment.
    'product_id' => Config::env('PAYPAL_PRODUCT_ID', ''),
    'plan_id'    => Config::env('PAYPAL_PLAN_ID', ''),
    'monthly_price' => Config::env('PAYPAL_MONTHLY_PRICE', '149.00'),
    'yearly_price' => Config::env('PAYPAL_YEARLY_PRICE', '1490.00'),

    'currency' => Config::env('PAYPAL_CURRENCY', 'PHP'),

    'brand_name' => Config::env('APP_NAME', 'PolishedPro'),

    'api_base' => Config::env('PAYPAL_MODE', 'sandbox') === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com',

    // Webhook events the app acts on.
    'events' => [
        'BILLING.SUBSCRIPTION.ACTIVATED',
        'BILLING.SUBSCRIPTION.CANCELLED',
        'BILLING.SUBSCRIPTION.SUSPENDED',
        'BILLING.SUBSCRIPTION.EXPIRED',
        'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
        'PAYMENT.SALE.COMPLETED',
        'PAYMENT.SALE.REFUNDED',
        'PAYMENT.SALE.REVERSED',
    ],
];

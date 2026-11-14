<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;

/**
 * Minimal PayPal REST client for the Subscriptions API.
 *
 * Written against the raw HTTP API with cURL rather than the official SDK so
 * the project stays Composer-free. Only the handful of endpoints the app
 * actually needs are implemented.
 *
 * A free sandbox account at developer.paypal.com is enough to exercise the
 * whole flow end to end.
 */
final class PayPalClient
{
    private static ?string $token = null;
    private static int $tokenExpiresAt = 0;

    /** True when credentials are present; the UI degrades gracefully when not. */
    public static function isConfigured(): bool
    {
        return (string) Config::get('paypal.client_id', '') !== ''
            && (string) Config::get('paypal.client_secret', '') !== '';
    }

    public static function mode(): string
    {
        return (string) Config::get('paypal.mode', 'sandbox');
    }

    public static function isSandbox(): bool
    {
        return self::mode() === 'sandbox';
    }

    public static function clientId(): string
    {
        return (string) Config::get('paypal.client_id', '');
    }

    private static function apiBase(): string
    {
        return rtrim((string) Config::get('paypal.api_base', 'https://api-m.sandbox.paypal.com'), '/');
    }

    /** The dedicated Premium setup command is intentionally incapable of Live calls. */
    public static function requireSandboxMode(): void
    {
        if (self::mode() !== 'sandbox') {
            throw new \RuntimeException('Premium setup is Sandbox-only. Set PAYPAL_MODE=sandbox before running it.');
        }

        if (!self::isConfigured()) {
            throw new \RuntimeException('PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET must be configured in .env.');
        }
    }

    /**
     * OAuth2 client-credentials token, cached in-process until shortly before
     * it expires.
     */
    private static function accessToken(): string
    {
        if (self::$token !== null && time() < self::$tokenExpiresAt) {
            return self::$token;
        }

        $response = self::request(
            'POST',
            '/v1/oauth2/token',
            'grant_type=client_credentials',
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode(self::clientId() . ':' . Config::get('paypal.client_secret')),
            ],
            false
        );

        if (!isset($response['body']['access_token'])) {
            throw new \RuntimeException('PayPal authentication failed. Check PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET.');
        }

        self::$token = (string) $response['body']['access_token'];
        // Renew a minute early to avoid racing the expiry.
        self::$tokenExpiresAt = time() + max(60, (int) ($response['body']['expires_in'] ?? 3600) - 60);

        return self::$token;
    }

    /**
     * Create (or reuse) the product all subscription plans hang off.
     */
    public static function ensureProduct(): string
    {
        $response = self::authedRequest('POST', '/v1/catalogs/products', [
            'name'        => Config::get('paypal.brand_name', 'PolishedPro') . ' Artist Subscription',
            'description' => 'Subscription plans for artists listing services on PolishedPro.',
            'type'        => 'SERVICE',
            'category'    => 'SOFTWARE',
        ], ['PayPal-Request-Id: pp-product-' . substr(hash('sha256', (string) Config::get('app.key')), 0, 20)]);

        if (isset($response['body']['id'])) {
            return (string) $response['body']['id'];
        }

        throw new \RuntimeException('Could not create the PayPal product: ' . json_encode($response['body']));
    }

    /** Reuse the exact Premium Sandbox product before creating another one. */
    public static function ensurePremiumSandboxProduct(): string
    {
        self::requireSandboxMode();
        $expectedName = 'PolishedProV2 Premium';
        $expectedDescription = 'Monthly subscription to PolishedProV2, an online platform for managing appointments, bookings, customers, and business operations.';
        $configuredId = trim((string) Config::get('paypal.product_id', ''));

        if ($configuredId !== '') {
            try {
                $existing = self::getProduct($configuredId);
                if (($existing['name'] ?? '') === $expectedName && ($existing['type'] ?? '') === 'SERVICE') {
                    return self::updatePremiumProductDescription($configuredId, $existing, $expectedDescription);
                }
            } catch (\Throwable) {
                // Search for the named resource before deciding to create one.
            }
        }

        foreach (self::listProducts() as $product) {
            if (($product['name'] ?? '') === $expectedName && ($product['type'] ?? '') === 'SERVICE') {
                $id = (string) ($product['id'] ?? '');
                if ($id !== '') {
                    return self::updatePremiumProductDescription($id, $product, $expectedDescription);
                }
            }
        }

        $response = self::authedRequest('POST', '/v1/catalogs/products', [
            'name' => $expectedName,
            'description' => $expectedDescription,
            'type' => 'SERVICE',
            'category' => 'SOFTWARE',
        ], ['PayPal-Request-Id: ' . substr(hash('sha256', 'polishedprov2-premium-product'), 0, 32)]);

        self::requireSuccess($response, [200, 201]);
        $id = (string) ($response['body']['id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('PayPal did not return a product ID.');
        }

        return $id;
    }

    /** @param array<string, mixed> $product */
    private static function updatePremiumProductDescription(string $productId, array $product, string $description): string
    {
        if (($product['description'] ?? '') === $description) {
            return $productId;
        }

        $response = self::authedRequest('PATCH', '/v1/catalogs/products/' . rawurlencode($productId), [[
            'op' => 'replace',
            'path' => '/description',
            'value' => $description,
        ]]);
        self::requireSuccess($response, [200, 204]);

        $confirmed = self::getProduct($productId);
        if (($confirmed['name'] ?? '') !== 'PolishedProV2 Premium'
            || ($confirmed['type'] ?? '') !== 'SERVICE'
            || ($confirmed['description'] ?? '') !== $description
        ) {
            throw new \RuntimeException('The existing Sandbox product could not be updated to the requested details.');
        }

        return $productId;
    }

    /** Create or reuse the exact PHP 149/month Sandbox plan and confirm ACTIVE. */
    public static function ensurePremiumSandboxPlan(string $productId, string $price): string
    {
        self::requireSandboxMode();
        if (number_format((float) $price, 2, '.', '') !== '149.00') {
            throw new \RuntimeException('PAYPAL_MONTHLY_PRICE must remain 149.00 for this Premium plan.');
        }

        $configuredId = trim((string) Config::get('paypal.plan_id', ''));
        if ($configuredId !== '') {
            try {
                $existing = self::getPlan($configuredId);
                if (self::isPremiumPlan($existing, $productId, $price)) {
                    return self::activateAndConfirmPlan($configuredId, $existing);
                }
            } catch (\Throwable) {
                // Search for the exact named plan before deciding to create one.
            }
        }

        foreach (self::listPlans($productId) as $plan) {
            if (($plan['name'] ?? '') === 'PolishedProV2 Monthly Premium'
                && ($plan['product_id'] ?? '') === $productId
                && !empty($plan['id'])
            ) {
                $details = self::getPlan((string) $plan['id']);
                if (self::isPremiumPlan($details, $productId, $price)) {
                    return self::activateAndConfirmPlan((string) $plan['id'], $details);
                }
            }
        }

        $response = self::authedRequest('POST', '/v1/billing/plans', [
            'product_id' => $productId,
            'name' => 'PolishedProV2 Monthly Premium',
            'description' => 'Monthly Premium subscription for PolishedProV2.',
            'status' => 'ACTIVE',
            'billing_cycles' => [[
                'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0,
                'pricing_scheme' => ['fixed_price' => ['value' => '149.00', 'currency_code' => 'PHP']],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee' => ['value' => '0.00', 'currency_code' => 'PHP'],
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 1,
            ],
        ], ['PayPal-Request-Id: ' . substr(hash('sha256', 'polishedprov2-premium-plan-149-php'), 0, 32)]);

        self::requireSuccess($response, [200, 201]);
        $id = (string) ($response['body']['id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('PayPal did not return a plan ID.');
        }

        return self::activateAndConfirmPlan($id, $response['body']);
    }

    /** @return array<string, mixed> */
    public static function getProduct(string $productId): array
    {
        $response = self::authedRequest('GET', '/v1/catalogs/products/' . rawurlencode($productId));
        self::requireSuccess($response, [200]);
        return $response['body'];
    }

    /** @return array<string, mixed> */
    public static function getPlan(string $planId): array
    {
        $response = self::authedRequest('GET', '/v1/billing/plans/' . rawurlencode($planId));
        self::requireSuccess($response, [200]);
        return $response['body'];
    }

    /** @return array<int, array<string, mixed>> */
    private static function listProducts(): array
    {
        return self::listResources('/v1/catalogs/products', []);
    }

    /** @return array<int, array<string, mixed>> */
    private static function listPlans(string $productId): array
    {
        return self::listResources('/v1/billing/plans', ['product_id' => $productId]);
    }

    /** @return array<int, array<string, mixed>> */
    private static function listResources(string $path, array $filters): array
    {
        $items = [];
        for ($page = 1; $page <= 100; $page++) {
            $query = http_build_query($filters + ['page_size' => 20, 'page' => $page, 'total_required' => 'true']);
            $response = self::authedRequest('GET', $path . '?' . $query);
            self::requireSuccess($response, [200]);
            $pageItems = $response['body']['products'] ?? $response['body']['plans'] ?? [];
            if (!is_array($pageItems) || $pageItems === []) {
                break;
            }
            array_push($items, ...$pageItems);
            if (count($pageItems) < 20) {
                break;
            }
        }

        return $items;
    }

    /** @param array<string, mixed> $plan */
    private static function isPremiumPlan(array $plan, string $productId, string $price): bool
    {
        $cycle = $plan['billing_cycles'][0] ?? [];
        $frequency = $cycle['frequency'] ?? [];
        $fixedPrice = $cycle['pricing_scheme']['fixed_price'] ?? [];
        $preferences = $plan['payment_preferences'] ?? [];
        $setupFee = $preferences['setup_fee'] ?? ['value' => '0.00', 'currency_code' => 'PHP'];

        return count($plan['billing_cycles'] ?? []) === 1
            && ($plan['product_id'] ?? '') === $productId
            && ($plan['name'] ?? '') === 'PolishedProV2 Monthly Premium'
            && ($plan['status'] ?? '') !== 'DEACTIVATED'
            && ($frequency['interval_unit'] ?? '') === 'MONTH'
            && (int) ($frequency['interval_count'] ?? 0) === 1
            && ($cycle['tenure_type'] ?? '') === 'REGULAR'
            && (int) ($cycle['total_cycles'] ?? -1) === 0
            && (string) ($fixedPrice['value'] ?? '') === number_format((float) $price, 2, '.', '')
            && ($fixedPrice['currency_code'] ?? '') === 'PHP'
            && !empty($preferences['auto_bill_outstanding'])
            && (int) ($preferences['payment_failure_threshold'] ?? 0) === 1
            && (float) ($setupFee['value'] ?? 0) === 0.0
            && ($setupFee['currency_code'] ?? 'PHP') === 'PHP';
    }

    /** @param array<string, mixed> $plan */
    private static function activateAndConfirmPlan(string $planId, array $plan): string
    {
        if (($plan['status'] ?? '') !== 'ACTIVE') {
            $response = self::authedRequest('POST', '/v1/billing/plans/' . rawurlencode($planId) . '/activate');
            self::requireSuccess($response, [200, 204]);
        }

        $confirmed = self::getPlan($planId);
        if (($confirmed['status'] ?? '') !== 'ACTIVE') {
            throw new \RuntimeException('PayPal plan did not reach ACTIVE status; checkout remains disabled.');
        }

        return $planId;
    }

    /** @param array{status:int, body:array<string, mixed>} $response @param array<int, int> $allowed */
    private static function requireSuccess(array $response, array $allowed): void
    {
        if (!in_array($response['status'], $allowed, true)) {
            throw new \RuntimeException('PayPal API request failed with HTTP ' . $response['status'] . '. Check server logs for the safe error details.');
        }
    }

    /**
     * Create a billing plan. Returns the PayPal plan id to store on our row.
     */
    public static function createPlan(string $productId, string $name, string $description, float $price, string $cycle): string
    {
        $interval = $cycle === 'yearly' ? 'YEAR' : 'MONTH';

        $response = self::authedRequest('POST', '/v1/billing/plans', [
            'product_id'  => $productId,
            'name'        => substr($name, 0, 127),
            'description' => substr($description, 0, 127),
            'status'      => 'ACTIVE',
            'billing_cycles' => [[
                'frequency' => [
                    'interval_unit'  => $interval,
                    'interval_count' => 1,
                ],
                'tenure_type'  => 'REGULAR',
                'sequence'     => 1,
                'total_cycles' => 0, // bill until cancelled
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value'         => number_format($price, 2, '.', ''),
                        'currency_code' => (string) Config::get('paypal.currency', 'PHP'),
                    ],
                ],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding'     => true,
                'setup_fee_failure_action'  => 'CONTINUE',
                'payment_failure_threshold' => 3,
            ],
        ]);

        if (isset($response['body']['id'])) {
            return (string) $response['body']['id'];
        }

        throw new \RuntimeException('Could not create the PayPal plan: ' . json_encode($response['body']));
    }

    /**
     * Start a subscription and hand back the approval URL to redirect to.
     *
     * `$startAt` (local time) defers the first charge — used so an artist who
     * subscribes mid-trial, or re-subscribes before a cancelled plan runs out,
     * is not billed for days they already have.
     *
     * @return array{id:string, approve_url:string}
     */
    public static function createSubscription(string $paypalPlanId, array $subscriber, string $returnUrl, string $cancelUrl, ?string $startAt = null, ?string $customId = null): array
    {
        $payload = [
            'plan_id'    => $paypalPlanId,
            'subscriber' => [
                'name' => [
                    'given_name' => $subscriber['first_name'] ?? 'Artist',
                    'surname'    => $subscriber['last_name'] ?? '',
                ],
                'email_address' => $subscriber['email'] ?? null,
            ],
            'application_context' => [
                'brand_name'  => (string) Config::get('paypal.brand_name', 'PolishedPro'),
                'locale'      => 'en-US',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'payment_method' => [
                    'payer_selected'  => 'PAYPAL',
                    'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                ],
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];
        if ($customId !== null && $customId !== '') {
            $payload['custom_id'] = substr($customId, 0, 127);
        }

        // PayPal wants a future UTC instant; a start in the next few minutes
        // is just "now", which is the default anyway.
        $startTs = $startAt === null ? false : strtotime($startAt);
        if ($startTs !== false && $startTs > time() + 300) {
            $payload['start_time'] = gmdate('Y-m-d\TH:i:s\Z', $startTs);
        }

        $response = self::authedRequest('POST', '/v1/billing/subscriptions', $payload);

        $body = $response['body'];

        if (!isset($body['id'])) {
            throw new \RuntimeException('Could not start the PayPal subscription: ' . json_encode($body));
        }

        $approveUrl = '';
        foreach ($body['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approveUrl = (string) $link['href'];
                break;
            }
        }

        if ($approveUrl === '') {
            throw new \RuntimeException('PayPal did not return an approval link.');
        }

        return ['id' => (string) $body['id'], 'approve_url' => $approveUrl];
    }

    /** @return array<string, mixed> */
    public static function getSubscription(string $subscriptionId): array
    {
        $response = self::authedRequest('GET', '/v1/billing/subscriptions/' . rawurlencode($subscriptionId));
        self::requireSuccess($response, [200]);
        return $response['body'];
    }

    /** @return array<int, array<string, mixed>> */
    public static function subscriptionTransactions(string $subscriptionId, string $startTime, string $endTime): array
    {
        $query = http_build_query(['start_time' => $startTime, 'end_time' => $endTime]);
        $response = self::authedRequest(
            'GET',
            '/v1/billing/subscriptions/' . rawurlencode($subscriptionId) . '/transactions?' . $query
        );
        self::requireSuccess($response, [200]);
        $transactions = $response['body']['transactions'] ?? [];
        return is_array($transactions) ? $transactions : [];
    }

    public static function cancelSubscription(string $subscriptionId, string $reason = 'Cancelled by the artist'): bool
    {
        $response = self::authedRequest(
            'POST',
            '/v1/billing/subscriptions/' . rawurlencode($subscriptionId) . '/cancel',
            ['reason' => substr($reason, 0, 127)]
        );

        // 204 No Content is the success case here.
        return $response['status'] === 204 || $response['status'] === 200;
    }

    public static function suspendSubscription(string $subscriptionId, string $reason = 'Paused'): bool
    {
        $response = self::authedRequest(
            'POST',
            '/v1/billing/subscriptions/' . rawurlencode($subscriptionId) . '/suspend',
            ['reason' => substr($reason, 0, 127)]
        );

        return $response['status'] === 204 || $response['status'] === 200;
    }

    /**
     * Ask PayPal to verify a webhook signature.
     *
     * This is the only thing standing between the billing tables and anyone who
     * can POST to the webhook URL, so a failure here must reject the request.
     */
    public static function verifyWebhookSignature(array $headers, string $rawBody): bool
    {
        $webhookId = (string) Config::get('paypal.webhook_id', '');

        if ($webhookId === '') {
            Logger::security('PayPal webhook received but PAYPAL_WEBHOOK_ID is not configured — rejected.');
            return false;
        }

        $required = [
            'transmission_id'   => $headers['paypal-transmission-id'] ?? null,
            'transmission_time' => $headers['paypal-transmission-time'] ?? null,
            'cert_url'          => $headers['paypal-cert-url'] ?? null,
            'auth_algo'         => $headers['paypal-auth-algo'] ?? null,
            'transmission_sig'  => $headers['paypal-transmission-sig'] ?? null,
        ];

        foreach ($required as $key => $value) {
            if ($value === null || $value === '') {
                Logger::security("PayPal webhook missing header: {$key}");
                return false;
            }
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return false;
        }

        try {
            $response = self::authedRequest('POST', '/v1/notifications/verify-webhook-signature', [
                'transmission_id'   => $required['transmission_id'],
                'transmission_time' => $required['transmission_time'],
                'cert_url'          => $required['cert_url'],
                'auth_algo'         => $required['auth_algo'],
                'transmission_sig'  => $required['transmission_sig'],
                'webhook_id'        => $webhookId,
                'webhook_event'     => $event,
            ]);
        } catch (\Throwable $e) {
            Logger::error('PayPal signature verification call failed: ' . $e->getMessage());
            return false;
        }

        return ($response['body']['verification_status'] ?? '') === 'SUCCESS';
    }

    // -- HTTP ---------------------------------------------------------------

    /**
     * @return array{status:int, body:array<string, mixed>}
     */
    private static function authedRequest(string $method, string $path, ?array $payload = null, array $extraHeaders = []): array
    {
        $headers = array_merge([
            'Content-Type: application/json',
            'Authorization: Bearer ' . self::accessToken(),
        ], $extraHeaders);

        $response = self::request(
            $method,
            $path,
            $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES),
            $headers
        );
        return $response;
    }

    /**
     * @return array{status:int, body:array<string, mixed>}
     */
    private static function request(string $method, string $path, ?string $body, array $headers, bool $log = true): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('The PHP cURL extension is required for PayPal. Enable extension=curl in php.ini.');
        }

        $ch = curl_init(self::apiBase() . $path);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            // Certificate verification is never disabled — this is a payments API.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);

        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('Could not reach PayPal: ' . $error);
        }

        $decoded = json_decode((string) $raw, true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($log && $status >= 400) {
            // Never log the request body: it can carry tokens and payer details.
            Logger::error('PayPal API error', [
                'path'   => $path,
                'status' => $status,
                'name'   => $decoded['name'] ?? null,
                'detail' => $decoded['message'] ?? null,
            ]);
        }

        return ['status' => $status, 'body' => $decoded];
    }
}

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
        return self::mode() !== 'live';
    }

    public static function clientId(): string
    {
        return (string) Config::get('paypal.client_id', '');
    }

    private static function apiBase(): string
    {
        return rtrim((string) Config::get('paypal.api_base', 'https://api-m.sandbox.paypal.com'), '/');
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
    public static function createSubscription(string $paypalPlanId, array $subscriber, string $returnUrl, string $cancelUrl, ?string $startAt = null): array
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
        return $response['body'];
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

        return self::request(
            $method,
            $path,
            $payload === null ? null : json_encode($payload, JSON_UNESCAPED_SLASHES),
            $headers
        );
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

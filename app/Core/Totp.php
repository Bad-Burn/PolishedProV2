<?php

declare(strict_types=1);

namespace App\Core;

/**
 * RFC 6238 time-based one-time passwords, implemented in plain PHP.
 *
 * This powers optional two-factor authentication for admins and artists using
 * any standard authenticator app (Google Authenticator, Authy, 1Password) —
 * no third-party service, no SMS cost, no extra dependency.
 */
final class Totp
{
    private const PERIOD  = 30;  // seconds per code
    private const DIGITS  = 6;
    private const WINDOW  = 1;   // accept the neighbouring steps for clock drift
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A fresh base32 shared secret to show as a QR code / setup key. */
    public static function generateSecret(int $length = 32): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }
        return $secret;
    }

    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $counter = (int) floor(time() / self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            if (hash_equals(self::codeFor($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function codeFor(string $secret, int $counter): string
    {
        $key    = self::base32Decode($secret);
        $binary = pack('J', $counter); // 64-bit big-endian counter
        $hash   = hash_hmac('sha1', $binary, $key, true);

        $offset = ord($hash[19]) & 0x0F;
        $value  = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
             (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** otpauth:// URI that authenticator apps scan. */
    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?' . http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
            'algorithm' => 'SHA1',
        ]);
    }

    /** One-time recovery codes, shown once and stored hashed. */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(3)) . '-' . bin2hex(random_bytes(3)));
        }
        return $codes;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
        if ($secret === '') {
            return '';
        }

        $buffer = 0;
        $bits   = 0;
        $output = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $index;
            $bits  += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $output;
    }
}

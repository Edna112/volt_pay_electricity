<?php

namespace App\Support;

class Totp
{
    /**
     * @return array{secret:string, otpauth_url:string}
     */
    public static function generate(string $issuer, string $accountName): array
    {
        $secret = self::base32Encode(random_bytes(20)); // 160-bit secret

        $label = rawurlencode($issuer.':'.$accountName);
        $issuerEncoded = rawurlencode($issuer);

        $otpauth = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerEncoded}&algorithm=SHA1&digits=6&period=30";

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauth,
        ];
    }

    public static function verify(string $base32Secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? $code;
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $secret = self::base32Decode($base32Secret);
        if ($secret === null) {
            return false;
        }

        $timeSlice = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            $calc = self::hotp($secret, $timeSlice + $i, 6);
            if (hash_equals($calc, $code)) {
                return true;
            }
        }

        return false;
    }

    private static function hotp(string $secret, int $counter, int $digits): string
    {
        // 8-byte big-endian counter
        $binCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binCounter, $secret, true);

        $offset = ord($hash[19]) & 0x0f;
        $truncated = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );

        $mod = 10 ** $digits;
        $otp = (string) ($truncated % $mod);

        return str_pad($otp, $digits, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $input): ?string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper($input);
        $input = preg_replace('/[^A-Z2-7]/', '', $input) ?? $input;

        if ($input === '') {
            return null;
        }

        $bits = '';
        $len = strlen($input);
        for ($i = 0; $i < $len; $i++) {
            $val = strpos($alphabet, $input[$i]);
            if ($val === false) {
                return null;
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $bytes .= chr(bindec(substr($bits, $i, 8)));
        }

        return $bytes;
    }

    private static function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $out .= $alphabet[bindec($chunk)];
        }

        return $out;
    }
}


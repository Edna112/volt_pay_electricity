<?php

namespace App\Services\Gateway;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookSignatureVerifier
{
    /**
     * Dummy MTN/Orange signature verification using HMAC-SHA256 over the raw body.
     *
     * MTN header:   X-MTN-Signature: <hex|base64>
     * Orange header: X-Orange-Signature: <hex|base64>
     *
     * You can swap this implementation to match the exact gateway spec later.
     */
    public function verify(Request $request, string $gateway): bool
    {
        $gateway = Str::lower($gateway);

        if ($gateway === 'mtn') {
            return $this->verifyHmacSha256(
                $request,
                secret: (string) config('services.mtn.webhook_secret', ''),
                headerName: 'X-MTN-Signature'
            );
        }

        if ($gateway === 'orange') {
            return $this->verifyHmacSha256(
                $request,
                secret: (string) config('services.orange.webhook_secret', ''),
                headerName: 'X-Orange-Signature'
            );
        }

        // For mock_gateway or unknown gateways, we accept (dummy behavior).
        return true;
    }

    private function verifyHmacSha256(Request $request, string $secret, string $headerName): bool
    {
        if ($secret === '') {
            return false;
        }

        $provided = trim((string) $request->header($headerName, ''));

        if ($provided === '') {
            return false;
        }

        $raw = (string) $request->getContent();

        // Hex is easy to use in Postman; some gateways use base64. We accept both.
        $expectedHex = hash_hmac('sha256', $raw, $secret);
        $expectedB64 = base64_encode(hex2bin($expectedHex) ?: '');

        return hash_equals($expectedHex, $provided) || hash_equals($expectedB64, $provided);
    }
}


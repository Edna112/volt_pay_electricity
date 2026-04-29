<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorCodeRequest;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $issuer = (string) config('app.name', 'VoltPay');
        $account = (string) ($user->email ?? $user->getKey());

        $generated = Totp::generate($issuer, $account);

        $user->two_factor_secret = Crypt::encryptString($generated['secret']);
        $user->two_factor_enabled_at = null;
        $user->save();

        return response()->json([
            'secret' => $generated['secret'],
            'otpauth_url' => $generated['otpauth_url'],
        ]);
    }

    public function enable(TwoFactorCodeRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            throw ValidationException::withMessages([
                'code' => ['2FA is not set up yet. Call /api/2fa/setup first.'],
            ]);
        }

        $secret = Crypt::decryptString((string) $user->two_factor_secret);
        $ok = Totp::verify($secret, (string) $request->validated('code'));

        if (! $ok) {
            throw ValidationException::withMessages([
                'code' => ['Invalid 2FA code.'],
            ]);
        }

        $user->two_factor_enabled_at = now();
        $user->save();

        return response()->json([
            'message' => '2FA enabled.',
        ]);
    }

    public function disable(TwoFactorCodeRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json([
                'message' => '2FA already disabled.',
            ]);
        }

        $secret = Crypt::decryptString((string) $user->two_factor_secret);
        $ok = Totp::verify($secret, (string) $request->validated('code'));

        if (! $ok) {
            throw ValidationException::withMessages([
                'code' => ['Invalid 2FA code.'],
            ]);
        }

        $user->two_factor_secret = null;
        $user->two_factor_enabled_at = null;
        $user->save();

        return response()->json([
            'message' => '2FA disabled.',
        ]);
    }
}


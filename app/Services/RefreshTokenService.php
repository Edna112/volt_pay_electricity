<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class RefreshTokenService
{
    /**
     * Issue access token + refresh token for the given user.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int|null}
     */
    public function issueTokens(User $user, Request $request): array
    {
        return DB::transaction(fn () => $this->issueTokensCore($user, $request));
    }

    /**
     * Rotate refresh token + issue new access token.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int|null}
     */
    public function rotate(Request $request): array
    {
        $plainRefreshToken = (string) $request->input('refresh_token', '');
        if ($plainRefreshToken === '') {
            throw ValidationException::withMessages([
                'refresh_token' => ['refresh_token is required.'],
            ]);
        }

        $hash = hash('sha256', $plainRefreshToken);

        return DB::transaction(function () use ($hash, $request) {
            /** @var RefreshToken|null $rt */
            $rt = RefreshToken::query()
                ->where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (! $rt || $rt->revoked_at || $rt->expires_at->isPast()) {
                Log::warning('Invalid refresh token attempt', [
                    'ip' => $request->ip(),
                ]);

                throw ValidationException::withMessages([
                    'refresh_token' => ['Invalid refresh token.'],
                ]);
            }

            /** @var User|null $user */
            $user = User::query()->whereKey($rt->user_id)->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'refresh_token' => ['Invalid refresh token.'],
                ]);
            }

            // Revoke old access token row if it still exists.
            /** @var PersonalAccessToken|null $oldAccess */
            $oldAccess = PersonalAccessToken::query()->whereKey($rt->access_token_id)->first();
            if ($oldAccess) {
                $oldAccess->delete();
            }

            // Issue new tokens inside the same DB transaction (avoid nesting).
            $issued = $this->issueTokensCore($user, $request);

            // Revoke old refresh token record and link replacement (best-effort).
            $newRt = RefreshToken::query()
                ->where('token_hash', hash('sha256', $issued['refresh_token']))
                ->latest('id')
                ->first();

            $rt->revoked_at = now();
            $rt->last_used_at = now();
            if ($newRt) {
                $rt->replaced_by_id = $newRt->getKey();
            }
            $rt->save();

            Log::info('Refresh token rotated', [
                'user_id' => (string) $user->getKey(),
                'old_refresh_token_id' => (int) $rt->getKey(),
                'new_refresh_token_id' => $newRt?->getKey(),
            ]);

            return $issued;
        });
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int|null}
     */
    private function issueTokensCore(User $user, Request $request): array
    {
        // Issue Sanctum personal access token (access token)
        $tokenResult = $user->createToken('access');
        $plainAccessToken = $tokenResult->plainTextToken;

        /** @var PersonalAccessToken $accessToken */
        $accessToken = $tokenResult->accessToken;

        $plainRefreshToken = $this->generatePlainRefreshToken();
        $refreshHash = hash('sha256', $plainRefreshToken);

        $refreshExpiresAt = now()->addMinutes((int) env('REFRESH_TOKEN_TTL_MINUTES', 60 * 24 * 30));

        RefreshToken::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => $refreshHash,
            'access_token_id' => $accessToken->getKey(),
            'expires_at' => $refreshExpiresAt,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
        ]);

        Log::info('Issued access + refresh tokens', [
            'user_id' => (string) $user->getKey(),
            'access_token_id' => (int) $accessToken->getKey(),
        ]);

        return [
            'access_token' => $plainAccessToken,
            'refresh_token' => $plainRefreshToken,
            'expires_in' => config('sanctum.expiration') ? ((int) config('sanctum.expiration')) * 60 : null,
        ];
    }

    /**
     * Revoke refresh tokens tied to the current access token.
     */
    public function revokeForCurrentAccessToken(?PersonalAccessToken $accessToken): void
    {
        if (! $accessToken) {
            return;
        }

        RefreshToken::query()
            ->where('access_token_id', $accessToken->getKey())
            ->update(['revoked_at' => now()]);
    }

    private function generatePlainRefreshToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }
}

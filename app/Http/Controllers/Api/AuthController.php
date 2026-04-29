<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\RefreshTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens
    ) {}

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $tokens = $this->refreshTokens->issueTokens($user, $request);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
            // Backwards compatibility with older clients:
            'token' => $tokens['access_token'],
        ]);
    }
    //login

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        $tokens = $this->refreshTokens->issueTokens($user, $request);

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
            // Backwards compatibility with older clients:
            'token' => $tokens['access_token'],
        ]);
    }
    //logout
    public function logout(Request $request)
    {
        /** @var PersonalAccessToken|null $current */
        $current = $request->user()?->currentAccessToken();

        $this->refreshTokens->revokeForCurrentAccessToken($current);

        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    public function refresh(Request $request)
    {
        $tokens = $this->refreshTokens->rotate($request);

        return response()->json([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $tokens['expires_in'],
            // Backwards compatibility:
            'token' => $tokens['access_token'],
        ]);
    }
}

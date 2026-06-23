<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Handle user registration.
     */
    public function register(StoreUserRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::create([
                'user_id' => Str::uuid(),
                'user_name' => $request->user_name,
                'profile_name' => $request->profile_name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'phone_number' => $request->phone_number,
                'status' => $request->status ?? 1,
                'lang' => $request->lang ?? 'en',
                'is_locked' => $request->is_locked ?? false,
            ]);

            $user->update([
                'last_login' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            return $this->respondWithToken($user, 201);
        });
    }

    /**
     * Handle user login.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->isCurrentlyLocked()) {
            throw ValidationException::withMessages([
                'email' => [__('auth.account_locked', ['until' => $user->locked_until])],
            ]);
        }

        $user->update([
            'last_login' => now(),
            'last_login_ip' => $request->ip(),
            'login_attempts' => 0,
        ]);

        // FIXED: Load 'role' not 'roles'
        $user->load('role');

        return $this->respondWithToken($user);
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['last_active_at' => now()]);

        $user->tokens()->each(function ($token) {
            $token->revoke();
        });

        return response()->json([
            'success' => true,
            'message' => __('auth.logout_success'),
        ]);
    }

    /**
     * Get authenticated user details with permissions
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['last_active_at' => now()]);

        // FIXED: Load 'role' not 'roles'
        $user->load('role');

        return response()->json([
            'data' => new AuthenticatedUserResource($user),
        ]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Send a password reset link to the given email address.
     */
    public function forgetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($data);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'If this email exists, a password reset link has been sent.',
        ]);
    }

    /**
     * Reset a user's password using a broker token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }

    /**
     * Standardize the authentication response.
     */
    protected function respondWithToken(User $user, int $code = 200): JsonResponse
    {
        $tokenResult = $user->createToken('auth_token');
        $token = $tokenResult->token;

        if ($expiration = config('passport.token_expiration', 31536000)) {
            $token->expires_at = now()->addSeconds($expiration);
            $token->save();
        }

        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $token->expires_at ? $token->expires_at->diffInSeconds(now()) : 31536000,
            'data' => new AuthenticatedUserResource($user),
        ], $code);
    }
}
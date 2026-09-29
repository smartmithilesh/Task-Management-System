<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\ApiToken;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:256'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);
        $credentials['email'] = Str::lower($credentials['email']);
        $credentials['status'] = 'active';
        $deviceName = $credentials['device_name'] ?? null;
        unset($credentials['device_name']);

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        $data = ['user' => new UserResource($request->user())];
        if ($deviceName !== null) {
            $plainTextToken = Str::random(80);
            ApiToken::query()->create([
                'user_id' => $request->user()->id,
                'name' => trim($deviceName) ?: 'Mobile device',
                'token_hash' => hash('sha256', $plainTextToken),
                'expires_at' => now()->addDays(90),
            ]);
            $data['token'] = $plainTextToken;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->attributes->has('api_token_id')) {
            ApiToken::query()->whereKey($request->attributes->get('api_token_id'))->delete();
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'message' => 'Logged out.']);
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function sendPasswordResetLink(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $status = Password::sendResetLink(['email' => Str::lower($validated['email'])]);

        if ($status === Password::RESET_THROTTLED) {
            abort(429, 'Please wait before requesting another password reset link.');
        }

        return response()->json([
            'success' => true,
            'message' => 'If an account matches that email, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
            'password_confirmation' => ['required', 'string'],
        ]);

        $status = Password::reset(
            [
                'email' => Str::lower($validated['email']),
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token' => $validated['token'],
            ],
            function ($user) use ($validated): void {
                $user->forceFill(['password' => $validated['password']]);
                $user->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['success' => true, 'message' => 'Password reset successfully.']);
    }

    public function sendVerificationNotification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['success' => true, 'message' => 'Email address already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['success' => true, 'message' => 'Verification link sent.']);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = $request->user();

        if ($user->getKey() !== $id || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json(['success' => true, 'message' => 'Email address verified.']);
    }
}

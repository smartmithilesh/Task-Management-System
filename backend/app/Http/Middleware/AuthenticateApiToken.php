<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();
        if ($plainTextToken === null) {
            abort_unless(Auth::guard('web')->check(), 401, 'Unauthenticated.');

            return $next($request);
        }

        $token = ApiToken::query()->with('user')->where('token_hash', hash('sha256', $plainTextToken))->first();
        abort_if($token === null || ($token->expires_at !== null && $token->expires_at->isPast()) || $token->user?->status !== 'active', 401, 'The access token is invalid or expired.');

        Auth::guard('web')->setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token_id', $token->id);
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        return $next($request);
    }
}

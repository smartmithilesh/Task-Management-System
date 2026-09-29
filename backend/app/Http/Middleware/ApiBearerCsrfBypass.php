<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiBearerCsrfBypass extends ValidateCsrfToken
{
    public function handle($request, Closure $next): Response
    {
        if ($request instanceof Request && $request->is('api/v1/*') && $request->bearerToken() !== null) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}

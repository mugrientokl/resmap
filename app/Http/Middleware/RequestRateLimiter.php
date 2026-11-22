<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RequestRateLimiter
{
    public function handle(
        Request $request,
        Closure $next,
        int $ipLimit,
        int $ipMinutes,
        ?int $userLimit = null,
        ?int $userMinutes = null,
    ): Response {
        $ipKey = 'rate-limit:ip:'.$ipMinutes.':'.$ipLimit.':'.$request->ip().':'.$request->route()?->getName();

        if (RateLimiter::tooManyAttempts($ipKey, $ipLimit)) {
            return $this->tooManyRequests($request, $ipKey);
        }

        if ($request->user() !== null && $userLimit !== null && $userMinutes !== null) {
            $userKey = 'rate-limit:user:'.$userMinutes.':'.$userLimit.':'.$request->user()->getAuthIdentifier().':'.$request->route()?->getName();

            if (RateLimiter::tooManyAttempts($userKey, $userLimit)) {
                return $this->tooManyRequests($request, $userKey);
            }

            RateLimiter::hit($userKey, $userMinutes * 60);
        }

        RateLimiter::hit($ipKey, $ipMinutes * 60);

        return $next($request);
    }

    private function tooManyRequests(Request $request, string $key): Response
    {
        $seconds = RateLimiter::availableIn($key);

        return response()->view('errors.system', [
            'status' => 429,
            'title' => 'Demasiados intentos',
            'message' => 'Espera unos minutos antes de volver a intentarlo.',
        ], 429, [
            'Retry-After' => $seconds,
            'X-RateLimit-Reset' => now()->timestamp + $seconds,
        ]);
    }
}

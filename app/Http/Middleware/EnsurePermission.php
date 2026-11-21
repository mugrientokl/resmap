<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        abort_unless($request->user() && collect($permisos)->contains(
            fn (string $permiso): bool => $request->user()->tienePermiso($permiso)
        ), 403);

        return $next($request);
    }
}

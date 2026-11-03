<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        abort_unless($request->user() && collect($permissions)->contains(fn ($key) => $request->user()->hasPermission($key)), 403);
        return $next($request);
    }
}

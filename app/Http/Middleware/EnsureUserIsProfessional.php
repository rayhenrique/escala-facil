<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsProfessional
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === UserRole::Professional, 403);

        return $next($request);
    }
}

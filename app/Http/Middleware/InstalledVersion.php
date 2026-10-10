<?php

namespace App\Http\Middleware;

use App\Support\ApplicationVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstalledVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-App-Version', app(ApplicationVersion::class)->current());
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            // Nem navegador nem proxy devem reapresentar HTML de outro release.
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}

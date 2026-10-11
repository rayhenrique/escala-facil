<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InvitationPrivacy
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->query->has('token')) {
            $token = $request->query('token');
            $parameters = [];
            if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
                $grant = bin2hex(random_bytes(16));
                $grants = array_slice($request->session()->get('account_activation_grants', []), -19, null, true);
                $grants[$grant] = hash('sha256', $token);
                $request->session()->put('account_activation_grants', $grants);
                $parameters['grant'] = $grant;
            }

            return redirect()->route('account.activate', $parameters)->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        $csp = "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; "
            ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
            ."font-src 'self' https://fonts.bunny.net data:; "
            ."img-src 'self' data: blob: https:; "
            ."connect-src 'self'; "
            ."frame-ancestors 'none'; "
            ."form-action 'self'; "
            ."base-uri 'self';";

        $response->headers->set('Content-Security-Policy', $csp);

        if ($request->isSecure() || app()->environment('production') || config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}

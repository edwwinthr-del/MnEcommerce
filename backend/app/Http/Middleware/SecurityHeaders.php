<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $request->is('api/*')
            ? "default-src 'none'; frame-ancestors 'none'; base-uri 'none'"
            : "object-src 'none'; frame-ancestors 'none'; base-uri 'self'");

        if ($request->is('api/v1/auth/*', 'api/v1/orders*', 'api/v1/account/*', 'api/v1/checkout/*', 'admin*', 'livewire/*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}

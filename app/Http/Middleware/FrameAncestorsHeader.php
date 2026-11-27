<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FrameAncestorsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $ancestors = trim((string) config('queue_app.csp_frame_ancestors', "'self'"));
        if ($ancestors !== '') {
            // Normalize bare "self" env value to "'self'" while leaving quoted directives intact.
            $parts = preg_split('/\s+/', $ancestors) ?: [];
            $normalized = array_map(
                fn (string $part): string => $part === 'self' ? "'self'" : $part,
                $parts
            );
            $response->headers->set('Content-Security-Policy', 'frame-ancestors '.implode(' ', $normalized));
        }

        $response->headers->remove('X-Frame-Options');

        return $response;
    }
}

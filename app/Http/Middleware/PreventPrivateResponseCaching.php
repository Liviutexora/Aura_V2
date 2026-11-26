<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventPrivateResponseCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() || in_array('auth', $request->route()?->gatherMiddleware() ?? [], true) || $this->isSensitivePath($request)) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        }

        return $response;
    }

    private function isSensitivePath(Request $request): bool
    {
        return $request->is([
            'login',
            'register',
            'forgot-password',
            'reset-password',
            'verify-email',
            'dashboard',
            'dashboard/*',
            'admin',
            'admin/*',
            'mission-submissions/*/evidence',
        ]);
    }
}

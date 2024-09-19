<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Raiolanetworks\OAuth\Controllers\OAuthController;
use Symfony\Component\HttpFoundation\Response;

class OAuthTokenRenewal
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authController = app(OAuthController::class);
        $authController->renew();

        return $next($request);
    }
}

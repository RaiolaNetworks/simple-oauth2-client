<?php

declare(strict_types=1);

namespace Raiolanetworks\OAuth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Raiolanetworks\OAuth\Services\TokenRenewalService;
use Symfony\Component\HttpFoundation\Response;

class OAuthTokenRenewal
{
    public function __construct(
        protected TokenRenewalService $tokenRenewal,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $this->tokenRenewal->renew();

        if ($response !== null) {
            return $response;
        }

        return $next($request);
    }
}

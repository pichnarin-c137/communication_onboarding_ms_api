<?php

namespace App\Http\Middleware;

use App\Services\Analytics\Support\AnalyticsScopeResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAnalyticsScope
{
    public function __construct(private AnalyticsScopeResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $scope = $this->resolver->resolve($request);

        $request->attributes->set('analytics_scope', $scope);

        return $next($request);
    }
}

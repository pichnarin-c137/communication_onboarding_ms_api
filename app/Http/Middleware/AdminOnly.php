<?php

namespace App\Http\Middleware;

use App\Exceptions\AdminOnlyException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $role = $request->get('auth_role');

        if (! $role || strtolower($role) !== 'admin') {
            throw new AdminOnlyException('Admin access required for this resource');
        }

        return $next($request);
    }
}

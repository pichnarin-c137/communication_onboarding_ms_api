<?php

namespace App\Http\Middleware;

use App\Exceptions\AdminOnlyException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $role = $request->get('auth_role');

        if (! $role || !in_array(strtolower($role), array_map('strtolower', $roles))) {
            $allowed = implode(', ', $roles);
            throw new AdminOnlyException("Access denied. Allowed roles: $allowed");
        }

        return $next($request);
    }
}

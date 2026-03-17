<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Trust all proxies — required for Cloudflare Tunnel (local dev) and
     * Cloudflare CDN (production). Cloudflare's IP ranges change frequently,
     * so '*' is the safe approach. Nginx's set_real_ip_from handles the
     * IP restriction layer in production.
     */
    protected $proxies = '*';

    protected $headers =
        Request::HEADER_FORWARDED |
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}

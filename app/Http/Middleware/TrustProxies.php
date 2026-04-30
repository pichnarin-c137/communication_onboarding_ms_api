<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Symfony\Component\HttpFoundation\Request as RequestAlias;

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
        RequestAlias::HEADER_FORWARDED |
        RequestAlias::HEADER_X_FORWARDED_FOR |
        RequestAlias::HEADER_X_FORWARDED_HOST |
        RequestAlias::HEADER_X_FORWARDED_PORT |
        RequestAlias::HEADER_X_FORWARDED_PROTO;
}

#!/usr/bin/env bash
# dev.sh — COMS Dev Environment
# Starts: Docker services (Laravel, Nginx, Queue, Telegram Bot, Frontend) + Cloudflare Tunnel

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/storage/logs"

cleanup() {
    echo ""
    echo "Shutting down..."
    pkill -f "cloudflared tunnel run coms-local" 2>/dev/null || true
    docker compose stop 2>/dev/null || true
}
trap cleanup EXIT INT TERM

cd "$SCRIPT_DIR"

# Kill any stale tunnel process from previous runs
echo "Cleaning up any previous processes..."
pkill -f "cloudflared tunnel run coms-local" 2>/dev/null || true
sleep 1

# Start all Docker services (Laravel FPM, Nginx, Queue, Redis, Postgres, Telegram Bot, Frontend)
echo "Starting Docker services..."
docker compose up -d

echo "Waiting for services to be healthy..."
sleep 8
docker compose exec -T app php artisan optimize:clear 2>/dev/null || true

# Cloudflare named tunnel (runs on host, tunnels to Docker-exposed ports)
# Uses /tmp to avoid Docker volume ownership issues on storage/logs/
TUNNEL_LOG="/tmp/cloudflared-coms.log"
> "$TUNNEL_LOG"
cloudflared tunnel run coms-local >> "$TUNNEL_LOG" 2>&1 &

# Wait for tunnel to connect
echo "Waiting for tunnel to connect..."
for i in $(seq 1 20); do
    if grep -q "Registered tunnel connection" "$TUNNEL_LOG" 2>/dev/null; then
        break
    fi
    sleep 1
done

# Telegram webhook registration is intentionally skipped.
# The telegram-bot container uses getUpdates long-polling and calls deleteWebhook
# itself on startup. Registering a webhook while polling is active causes a 409.
# Use `telegram:set-webhook` only when deploying to production without the polling container.

echo ""
echo "  COMS Dev Environment Ready"
echo ""
echo "  API (local):        http://localhost:8000"
echo "  Telescope:          https://api.ecoapsara.com/telescope"
echo "  Horizon:            https://api.ecoapsara.com/horizon"
echo "  API (tunnel):       https://api.ecoapsara.com"
echo "  Webhook:            https://api.ecoapsara.com/api/v1/telegram/webhook"
echo "  Frontend (dev):     http://localhost:5173"
echo "  Frontend (tunnel):  https://coms.ecoapsara.com"
echo ""
echo "  Logs:"
echo "    Tunnel:   $TUNNEL_LOG"
echo "    App:      docker compose logs app"
echo "    Queue:    docker compose logs queue"
echo "    Frontend: docker compose logs frontend"
echo ""
echo "  Press Ctrl+C to stop all."
echo ""

wait

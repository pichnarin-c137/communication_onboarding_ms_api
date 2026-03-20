#!/usr/bin/env bash
# dev.sh — COMS Dev Environment
# Starts: Laravel, Queue Worker, Cloudflare Tunnel (coms-local), Vue Frontend

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRONTEND_DIR="$SCRIPT_DIR/../../frontend_vue/automate_training_progress_management_system_version_1"
LOG_DIR="$SCRIPT_DIR/storage/logs"

cleanup() {
    echo ""
    echo "Shutting down..."
    pkill -f "php artisan serve" 2>/dev/null || true
    pkill -f "php artisan queue:work" 2>/dev/null || true
    pkill -f "cloudflared tunnel run coms-local" 2>/dev/null || true
    fuser -k 5173/tcp 2>/dev/null || true
}
trap cleanup EXIT INT TERM

cd "$SCRIPT_DIR"

# Kill any stale processes from previous runs before starting fresh
echo "Cleaning up any previous processes..."
pkill -f "php artisan serve" 2>/dev/null || true
pkill -f "php artisan queue:work" 2>/dev/null || true
pkill -f "cloudflared tunnel run coms-local" 2>/dev/null || true
fuser -k 5173/tcp 2>/dev/null || true
sleep 1

# Laravel
php artisan serve --host=127.0.0.1 --port=8000 >> "$LOG_DIR/laravel.log" 2>&1 &

# Queue worker
php artisan queue:work >> "$LOG_DIR/worker.log" 2>&1 &

# Cloudflare named tunnel
> "$LOG_DIR/cloudflared.log"
cloudflared tunnel run coms-local >> "$LOG_DIR/cloudflared.log" 2>&1 &

# Wait for tunnel to connect
echo "Waiting for tunnel to connect..."
for i in $(seq 1 20); do
    if grep -q "Registered tunnel connection" "$LOG_DIR/cloudflared.log" 2>/dev/null; then
        break
    fi
    sleep 1
done

# Give Cloudflare edge a moment to fully propagate the tunnel before Telegram tries to verify it
sleep 5

# Register Telegram webhook — retry up to 3 times
php artisan optimize:clear 2>/dev/null || true
WEBHOOK_REGISTERED=false
for attempt in 1 2 3; do
    if php artisan telegram:set-webhook 2>&1; then
        WEBHOOK_REGISTERED=true
        break
    fi
    echo "Webhook attempt $attempt failed, retrying in 5s..."
    sleep 5
done
if [ "$WEBHOOK_REGISTERED" = false ]; then
    echo "  WARNING: Webhook registration failed. Run manually: php artisan telegram:set-webhook"
fi

# Vue frontend
cd "$FRONTEND_DIR"
npm run dev >> "$LOG_DIR/frontend.log" 2>&1 &
cd "$SCRIPT_DIR"

echo ""
echo "  COMS Dev Environment Ready"
echo ""
echo "  API (local):        http://localhost:8000"
echo "  Telescope:          https://api.ecoapsara.com/telescope"
echo "  Hozizon:            https://api.ecoapsara.com/hozizon"
echo "  API (tunnel):       https://api.ecoapsara.com"
echo "  Webhook:            https://api.ecoapsara.com/api/v1/telegram/webhook"

echo "  Frontend (dev):     http://localhost:5173"
echo "  Frontend (tunnel):  https://coms.ecoapsara.com"
echo ""
echo "  Logs:"
echo "    Laravel:          $LOG_DIR/laravel.log"
echo "    Queue:            $LOG_DIR/worker.log"
echo "    Tunnel:           $LOG_DIR/cloudflared.log"
echo "    Frontend:         $LOG_DIR/frontend.log"
echo ""
echo "  Press Ctrl+C to stop all."
echo ""

wait

#!/usr/bin/env bash
# dev.sh — COMS Dev Environment
# Starts: Laravel Server, Horizon (queue), Cloudflare Tunnel
# Auto-updates APP_URL + TELEGRAM_WEBHOOK_URL and registers webhook

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"
FRONTEND_DIR="$SCRIPT_DIR/../../frontend_vue/automate_training_progress_management_system_version_1/.env"
LOG_DIR="$SCRIPT_DIR/storage/logs"
CF_LOG="$LOG_DIR/cloudflared.log"

PIDS=()
cleanup() {
    echo ""
    echo "Shutting down..."
    for pid in "${PIDS[@]}"; do
        kill "$pid" 2>/dev/null || true
    done
}
trap cleanup EXIT INT TERM

cd "$SCRIPT_DIR"

php artisan serve --port=8000 &>/dev/null &
PIDS+=($!)

> "$CF_LOG"
cloudflared tunnel --url http://localhost:8000 2>"$CF_LOG" &
PIDS+=($!)

echo "Waiting for Cloudflare tunnel URL..."
TUNNEL_URL=""
for i in $(seq 1 30); do
    TUNNEL_URL=$(grep -oP 'https://[a-z0-9\-]+\.trycloudflare\.com' "$CF_LOG" 2>/dev/null | head -1)
    [[ -n "$TUNNEL_URL" ]] && break
    sleep 1
done

if [[ -z "$TUNNEL_URL" ]]; then
    echo "ERROR: Could not detect tunnel URL after 30s. Check $CF_LOG"
    exit 1
fi

sed -i "s|^APP_URL=.*|APP_URL=${TUNNEL_URL}|" "$ENV_FILE"
sed -i "s|^VITE_API_BASE_URL\s*=.*|VITE_API_BASE_URL = ${TUNNEL_URL}/api/v1|" "$FRONTEND_DIR"
sed -i "s|^TELEGRAM_WEBHOOK_URL=.*|TELEGRAM_WEBHOOK_URL=${TUNNEL_URL}/api/v1/telegram/webhook|" "$ENV_FILE"

echo "Waiting for DNS to propagate..."
sleep 5
php artisan optimize:clear 2>/dev/null || true
php artisan telescope:clear 2>/dev/null || true
php artisan horizon:terminate 2>/dev/null || true
php artisan telegram:set-webhook

php artisan horizon &
PIDS+=($!)

echo ""
echo "  COMS Dev Environment Ready"
echo ""
echo "  App:        http://localhost:8000"
echo "  Telescope:  http://localhost:8000/telescope"
echo "  Horizon:    http://localhost:8000/horizon"
echo "  Webhook:    ${TUNNEL_URL}/api/v1/telegram/webhook"
echo "  Logs:       ${LOG_DIR}/laravel.log"
echo ""
echo "  Press Ctrl+C to stop."
echo ""

tail -f "$LOG_DIR/laravel.log" 2>/dev/null || wait

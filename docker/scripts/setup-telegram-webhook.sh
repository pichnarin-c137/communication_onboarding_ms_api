#!/bin/sh
set -e

# Guard: skip if bot token is not configured
if [ -z "$TELEGRAM_BOT_TOKEN" ]; then
    echo "[telegram-setup] TELEGRAM_BOT_TOKEN not set — skipping."
    exit 0
fi

# Install curl (BusyBox wget can't show response body on HTTP errors)
apk add --no-cache curl -q 2>/dev/null

echo "[telegram-setup] Waiting for cloudflared tunnel to establish..."

TUNNEL_HOSTNAME=""
i=0
while [ $i -lt 30 ]; do
    i=$((i + 1))
    RESPONSE=$(curl -sf http://cloudflared:2000/quicktunnel 2>/dev/null || true)

    if [ -n "$RESPONSE" ]; then
        TUNNEL_HOSTNAME=$(echo "$RESPONSE" | sed 's/.*"hostname":"\([^"]*\)".*/\1/')
        if [ -n "$TUNNEL_HOSTNAME" ] && [ "$TUNNEL_HOSTNAME" != "$RESPONSE" ]; then
            break
        fi
    fi

    echo "[telegram-setup] Attempt $i/30 — tunnel not ready, retrying in 3s..."
    sleep 3
done

if [ -z "$TUNNEL_HOSTNAME" ] || [ "$TUNNEL_HOSTNAME" = "$RESPONSE" ]; then
    echo "[telegram-setup] ERROR: Could not read tunnel URL from cloudflared."
    exit 1
fi

WEBHOOK_URL="https://${TUNNEL_HOSTNAME}/api/v1/telegram/webhook"
echo "[telegram-setup] Tunnel ready → $WEBHOOK_URL"

# Build JSON body
if [ -n "$TELEGRAM_WEBHOOK_SECRET" ]; then
    JSON_BODY="{\"url\":\"${WEBHOOK_URL}\",\"secret_token\":\"${TELEGRAM_WEBHOOK_SECRET}\"}"
else
    JSON_BODY="{\"url\":\"${WEBHOOK_URL}\"}"
fi

echo "[telegram-setup] Registering webhook with Telegram..."
RESULT=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -d "$JSON_BODY" \
    "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/setWebhook")

echo "[telegram-setup] Telegram response: $RESULT"

if echo "$RESULT" | grep -q '"ok":true'; then
    echo "[telegram-setup] Webhook registered successfully!"
else
    echo "[telegram-setup] ERROR: Webhook registration failed."
    exit 1
fi

<?php

/**
 * Telegram Bot Polling Service
 *
 * Polls Telegram getUpdates (long polling) and forwards each update
 * to the Laravel backend webhook handler via the internal Docker network.
 * No public URL or cloudflared required.
 */

$botToken     = getenv('TELEGRAM_BOT_TOKEN');
$secret       = getenv('TELEGRAM_WEBHOOK_SECRET');
$backendUrl   = getenv('BACKEND_WEBHOOK_URL') ?: 'http://nginx:80/api/v1/telegram/webhook';
$pollTimeout  = 30; // seconds — Telegram long-poll window

if (!$botToken) {
    echo "[telegram-bot] ERROR: TELEGRAM_BOT_TOKEN is not set.\n";
    exit(1);
}

//  Delete any registered webhook so getUpdates works
// Telegram disallows getUpdates while a webhook is active.
echo "[telegram-bot] Removing any existing webhook...\n";
$res = tgCall($botToken, 'deleteWebhook', ['drop_pending_updates' => false]);
echo "[telegram-bot] deleteWebhook → " . ($res['ok'] ? 'ok' : json_encode($res)) . "\n";

//  Start polling loop
echo "[telegram-bot] Polling started (timeout={$pollTimeout}s).\n";

$offset = 0;

while (true) {
    $updates = tgCall($botToken, 'getUpdates', [
        'offset'  => $offset,
        'timeout' => $pollTimeout,
        'limit'   => 100,
    ], $pollTimeout + 5);

    if (!$updates || !isset($updates['ok'])) {
        echo "[telegram-bot] No response from Telegram, retrying in 5s...\n";
        sleep(5);
        continue;
    }

    if (!$updates['ok']) {
        echo "[telegram-bot] Telegram error: " . json_encode($updates) . "\n";
        sleep(5);
        continue;
    }

    foreach ($updates['result'] as $update) {
        $updateId = $update['update_id'];
        $offset   = $updateId + 1;

        echo "[telegram-bot] Update #{$updateId} → forwarding to backend...\n";

        $code = forwardToBackend($backendUrl, $secret, $update);

        echo "[telegram-bot] Backend HTTP {$code}\n";
    }
}

//  Helpers 

function tgCall(string $token, string $method, array $params = [], int $timeout = 10): ?array
{
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ch  = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => $timeout,
    ]);

    $body = curl_exec($ch);
    curl_close($ch);

    return $body ? json_decode($body, true) : null;
}

function forwardToBackend(string $url, string $secret, array $update): int
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($update),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "X-Telegram-Bot-Api-Secret-Token: {$secret}",
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code;
}

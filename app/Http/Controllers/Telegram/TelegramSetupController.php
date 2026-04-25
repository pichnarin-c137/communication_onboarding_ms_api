<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramGroupService;
use App\Services\Telegram\TelegramWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramSetupController extends Controller
{
    public function __construct(
        private TelegramGroupService $groupService,
        private TelegramWebhookService $webhookService,
    ) {}

    /**
     * POST /api/v1/telegram/setup-token
     *
     * Generate a new setup token for a client. Auth: sale, admin.
     */
    public function generateToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
        ]);

        $token = $this->groupService->generateToken(
            $validated['client_id'],
            $request->auth_user_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Setup token generated successfully.',
            'data' => [
                'token' => $token->token,
                'expires_at' => $token->expires_at->format('Y-m-d H:i:s'),
                'client_id' => $token->client_id,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/telegram/webhook
     *
     * Receive incoming Telegram webhook events. No auth — protected by secret header.
     * Always returns 200 as required by the Telegram Bot API.
     */
    public function webhook(Request $request): JsonResponse
    {
        $this->webhookService->handle($request->all());

        return response()->json(['ok' => true], 200);
    }
}

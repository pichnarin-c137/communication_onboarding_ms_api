<?php

namespace App\Http\Controllers\Telegram;

use App\Exceptions\Business\TelegramSetupException;
use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramGroupService;
use App\Services\Telegram\TelegramWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramSetupController extends Controller
{
    public function __construct(
        private readonly TelegramGroupService $groupService,
        private readonly TelegramWebhookService $webhookService,
    ) {}

    /**
     * POST /api/v1/telegram/setup-token
     *
     * Generate a new setup token for a client. Auth: sale, admin.
     * @throws TelegramSetupException
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
                'expires_at' => $token->expires_at,
                'client_id' => $token->client_id,
                'created' => $token->wasRecentlyCreated,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/telegram/setup-token
     *
     * Return an active setup token for a client, or create one if none exists.
     * @throws TelegramSetupException
     */
    public function getOrCreateToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
        ]);

        $token = $this->groupService->getOrCreateToken(
            $validated['client_id'],
            $request->auth_user_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Setup token retrieved successfully.',
            'data' => [
                'token' => $token->token,
                'expires_at' => $token->expires_at,
                'client_id' => $token->client_id,
                'created' => $token->wasRecentlyCreated,
            ],
        ]);
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

<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramGroup;
use App\Services\Telegram\TelegramGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramGroupController extends Controller
{
    public function __construct(
        private TelegramGroupService $groupService,
    ) {}

    /**
     * GET /api/v1/telegram/groups
     *
     * Paginated list of Telegram groups. Supports filtering by client_id and bot_status.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) min(max($request->query('per_page', 15), 1), 100);
        $page    = (int) max($request->query('page', 1), 1);

        $query = TelegramGroup::with('client')
            ->when($request->query('client_id'), fn ($q, $clientId) => $q->where('client_id', $clientId))
            ->when($request->query('bot_status'), fn ($q, $status) => $q->where('bot_status', $status));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = $paginator->getCollection()->map(fn (TelegramGroup $group) => [
            'id'               => $group->id,
            'client_id'        => $group->client_id,
            'client_name'      => $group->client?->company_name,
            'group_name'       => $group->group_name,
            'chat_id'          => $group->chat_id,
            'bot_status'       => $group->bot_status,
            'language'         => $group->language,
            'connected_at'     => $group->connected_at?->toDateTimeString(),
            'disconnected_at'  => $group->disconnected_at?->toDateTimeString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Telegram groups retrieved successfully.',
            'data'    => $data,
            'meta'    => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem() ?? 0,
                'to'           => $paginator->lastItem() ?? 0,
            ],
        ]);
    }

    /**
     * GET /api/v1/telegram/groups/{id}
     *
     * Single group detail with recent messages (last 10).
     */
    public function show(string $id): JsonResponse
    {
        $group = TelegramGroup::with(['client', 'messages' => function ($q) {
            $q->orderByDesc('created_at')->limit(10);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Telegram group retrieved successfully.',
            'data'    => [
                'id'               => $group->id,
                'client_id'        => $group->client_id,
                'client_name'      => $group->client?->company_name,
                'group_name'       => $group->group_name,
                'chat_id'          => $group->chat_id,
                'bot_status'       => $group->bot_status,
                'language'         => $group->language,
                'connected_at'     => $group->connected_at?->toDateTimeString(),
                'disconnected_at'  => $group->disconnected_at?->toDateTimeString(),
                'reconnected_at'   => $group->reconnected_at?->toDateTimeString(),
                'recent_messages'  => $group->messages->map(fn ($msg) => [
                    'id'           => $msg->id,
                    'content'      => $msg->message_body,
                    'message_type' => $msg->message_type,
                    'status'       => $msg->status,
                    'language'     => $msg->language,
                    'sent_at'      => $msg->sent_at?->toDateTimeString(),
                ]),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/telegram/groups/{id}/disconnect
     *
     * Manually disconnect a Telegram group from COMS.
     */
    public function disconnect(string $id): JsonResponse
    {
        $group = $this->groupService->disconnectGroup($id);

        return response()->json([
            'success' => true,
            'message' => 'Telegram group disconnected successfully.',
            'data'    => [
                'id'              => $group->id,
                'bot_status'      => $group->bot_status,
                'disconnected_at' => $group->disconnected_at?->toDateTimeString(),
                'reconnected_at'  => $group->reconnected_at?->toDateTimeString(),
            ],
        ]);
    }

    public function reconnect(string $id): JsonResponse
    {
        $group = $this->groupService->reconnectGroup($id);

        return response()->json([
            'success' => true,
            'message' => 'Telegram group reconnected successfully.',
            'data'    => [
                'id'              => $group->id,
                'bot_status'      => $group->bot_status,
                'disconnected_at' => $group->disconnected_at?->toDateTimeString(),
                'reconnected_at'  => $group->reconnected_at?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/telegram/groups/{id}/language
     *
     * Update the preferred language for a Telegram group.
     */
    public function updateLanguage(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'string', 'in:en,km'],
        ]);

        $group = $this->groupService->updateLanguage($id, $validated['language']);

        return response()->json([
            'success' => true,
            'message' => 'Telegram group language updated successfully.',
            'data'    => [
                'id'       => $group->id,
                'language' => $group->language,
            ],
        ]);
    }

    /**
     * POST /api/v1/telegram/groups/{id}/test-message
     *
     * Dispatch a test message to a connected group.
     */
    public function testMessage(string $id): JsonResponse
    {
        $this->groupService->sendTestMessage($id);

        return response()->json([
            'success' => true,
            'message' => 'Test message dispatched successfully.',
            'data'    => null,
        ]);
    }
}

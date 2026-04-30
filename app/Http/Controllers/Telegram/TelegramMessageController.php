<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\TelegramMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramMessageController extends Controller
{
    /**
     * GET /api/v1/telegram/messages
     *
     * Paginated list of Telegram messages. Supports filtering by:
     * telegram_group_id, status, message_type, from_date, to_date.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) min(max($request->query('per_page', 15), 1), 100);
        $page = (int) max($request->query('page', 1), 1);

        $query = TelegramMessage::with('telegramGroup')
            ->when($request->query('telegram_group_id'), fn ($q, $v) => $q->where('telegram_group_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('message_type'), fn ($q, $v) => $q->where('message_type', $v))
            ->when($request->query('from_date'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to_date'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = $paginator->getCollection()->map(fn (TelegramMessage $msg) => [
            'id' => $msg->id,
            'group_name' => $msg->telegramGroup?->group_name,
            'message_type' => $msg->message_type,
            'content' => $msg->message_body,
            'language' => $msg->language,
            'status' => $msg->status,
            'sent_at' => $msg->sent_at,
            'error_message' => $msg->error_message,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Telegram messages retrieved successfully.',
            'data' => $data,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pusher\Pusher;

class BroadcastController extends Controller
{
    public function __construct(
        private Pusher $pusher
    ) {}

    public function auth(Request $request): JsonResponse
    {
        $request->validate([
            'socket_id' => ['required', 'string'],
            'channel_name' => ['required', 'string'],
        ]);

        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');
        $authUserId = $request->get('auth_user_id');

        // Authorize presence-trainers channel (any authenticated user)
        if ($channelName === 'presence-trainers') {
            $authResponse = json_decode(
                $this->pusher->authorizePresenceChannel(
                    $channelName,
                    $socketId,
                    $authUserId,
                    ['role' => $request->get('auth_role')]
                ),
                true
            );

            return response()->json($authResponse);
        }

        // Authorize private-notifications.{userId} channels
        if (! preg_match('/^private-notifications\.(.+)$/', $channelName, $matches)) {
            return response()->json([
                'success' => false,
                'message' => 'Channel authorization denied.',
                'error_code' => 'BROADCAST_AUTH_FORBIDDEN',
            ], 403);
        }

        if ($matches[1] !== $authUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to subscribe to this channel.',
                'error_code' => 'BROADCAST_AUTH_FORBIDDEN',
            ], 403);
        }

        $authResponse = json_decode(
            $this->pusher->authorizeChannel($channelName, $socketId),
            true
        );

        return response()->json($authResponse);
    }
}

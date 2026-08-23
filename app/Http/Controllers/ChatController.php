<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Services\ChatBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Chat bot endpoint: stores the user message, asks the bot, stores the reply.
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $userMessage = trim($data['message']);

        ChatMessage::create([
            'user_id' => Auth::id(),
            'sender' => ChatMessage::SENDER_USER,
            'message' => $userMessage,
        ]);

        $reply = app(ChatBotService::class)->reply($userMessage);

        ChatMessage::create([
            'user_id' => Auth::id(),
            'sender' => ChatMessage::SENDER_BOT,
            'message' => $reply,
        ]);

        return response()->json(['reply' => $reply]);
    }
}

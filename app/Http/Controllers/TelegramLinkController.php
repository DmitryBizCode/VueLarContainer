<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TelegramLinkController extends Controller
{
    public function createLinkCode(Request $request): JsonResponse
    {
        $user = $request->user();

        $code = strtoupper(Str::random(8));
        $user->forceFill([
            'telegram_link_code' => $code,
            'telegram_link_code_expires_at' => now()->addMinutes(15),
        ])->save();

        return response()->json([
            'code' => $code,
            'expires_at' => $user->telegram_link_code_expires_at?->toIso8601String(),
            'bot' => [
                'username' => (string) config('services.telegram.bot_username', ''),
            ],
        ]);
    }

    public function unlink(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'telegram_chat_id' => null,
            'telegram_link_code' => null,
            'telegram_link_code_expires_at' => null,
        ])->save();

        return response()->json(['ok' => true]);
    }
}

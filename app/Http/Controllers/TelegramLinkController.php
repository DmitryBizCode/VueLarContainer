<?php

namespace App\Http\Controllers;

use App\Models\UserTelegramLink;
use App\Services\Telegram\TelegramLinkCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramLinkController extends Controller
{
    public function __construct(
        private readonly TelegramLinkCodeService $linkCodes,
    ) {}

    public function createLinkCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $issued = $this->linkCodes->issueForUser($user);

        return response()->json([
            'code' => $issued['plain'],
            'expires_at' => $issued['expires_at']->toIso8601String(),
            'bot' => [
                'username' => (string) config('services.telegram.bot_username', ''),
            ],
        ]);
    }

    public function destroyLink(Request $request, UserTelegramLink $link): JsonResponse
    {
        $user = $request->user();
        if ((int) $link->user_id !== (int) $user->id) {
            abort(403);
        }

        $link->delete();

        return response()->json(['ok' => true]);
    }
}

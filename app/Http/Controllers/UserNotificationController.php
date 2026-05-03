<?php

namespace App\Http\Controllers;

use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));
        $paginator = $this->notifications->inApp()->paginateForUser($request->user(), $perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'type' => $n->type,
                'is_read' => (bool) $n->is_read,
                'action_url' => $n->action_url,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->notifications->unreadCount($request->user()),
        ]);
    }
}

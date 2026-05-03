<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Throwable;

class InAppNotificationService
{
    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
    ): ?Notification {
        try {
            // Defensive dedupe: prevents accidental duplicate notifications when an event is dispatched twice
            // (e.g. double-submit or duplicated listener registration in a dev environment).
            $existing = Notification::query()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('title', $title)
                ->where('message', $message)
                ->where('action_url', $actionUrl)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            /** @var Notification $row */
            $row = Notification::query()->create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'action_url' => $actionUrl,
                'is_read' => false,
            ]);

            return $row;
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('notification.in_app_create_failed', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function unreadCount(User $user): int
    {
        return (int) Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * @return LengthAwarePaginator<int, Notification>
     */
    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function markRead(User $user, Notification $notification): bool
    {
        if ((int) $notification->user_id !== (int) $user->id) {
            return false;
        }

        $notification->forceFill(['is_read' => true])->save();

        return true;
    }

    public function markAllRead(User $user): void
    {
        Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'updated_at' => now()]);
    }
}

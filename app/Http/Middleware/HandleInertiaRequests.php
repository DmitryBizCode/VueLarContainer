<?php

namespace App\Http\Middleware;

use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    protected static function userPhotoUrl($user): ?string
    {
        if (! $user?->photo) {
            return null;
        }
        $p = (string) $user->photo;
        if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
            return $p;
        }
        if (str_contains($p, '/')) {
            return '/'.ltrim($p, '/');
        }

        return '/image/profile/'.$p;
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $unreadNotificationCount = 0;
        if ($user !== null) {
            $unreadNotificationCount = app(NotificationService::class)->unreadCount($user);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? array_merge($user->toArray(), [
                    'role' => $user->role ?? 'client',
                    'photo_url' => static::userPhotoUrl($user),
                ]) : null,
            ],
            'unreadNotificationCount' => $unreadNotificationCount,
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
                'errors' => $request->session()->get('errors')?->getBag('default')->getMessages() ?? [],
            ],
        ];
    }
}

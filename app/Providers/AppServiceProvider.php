<?php

namespace App\Providers;

use App\Models\Shipment;
use App\Observers\ShipmentObserver;
use App\Services\Notifications\EmailNotificationService;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Notifications\NotificationPayloadFactory;
use App\Services\Notifications\NotificationService;
use App\Services\Telegram\TelegramAccountLinkService;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramLinkCodeService;
use App\Services\Telegram\TelegramNotificationService;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                $app->make(InAppNotificationService::class),
                $app->make(EmailNotificationService::class),
                $app->make(NotificationPayloadFactory::class),
            );
        });

        $this->app->singleton(TelegramLinkCodeService::class, fn () => new TelegramLinkCodeService);

        $this->app->singleton(TelegramAccountLinkService::class, fn ($app) => new TelegramAccountLinkService(
            $app->make(TelegramLinkCodeService::class),
        ));

        $this->app->singleton(TelegramNotificationService::class, fn ($app) => new TelegramNotificationService(
            TelegramBotClient::fromConfig(),
            $app->make(TelegramAccountLinkService::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Shipment::observe(ShipmentObserver::class);

        // Event listeners live in app/Listeners and are auto-discovered via
        // Application::configure()->withEvents() (see bootstrap flow). Do not also
        // Event::listen(...) the same classes here — that registers every handler twice.
    }
}

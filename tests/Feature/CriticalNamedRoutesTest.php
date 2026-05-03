<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CriticalNamedRoutesTest extends TestCase
{
    public function test_public_and_auth_named_routes_exist(): void
    {
        foreach ([
            'services',
            'contact',
            'contact.submit',
            'dashboard',
            'finance.monitoring',
            'rentals.center',
            'rentals.map-data',
            'rentals.request.create',
            'rentals.request.store',
            'rentals.request.preview',
            'profile.edit',
            'notifications.index',
            'telegram.link-code',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Missing route name: {$name}");
        }
    }

    public function test_admin_named_routes_exist(): void
    {
        foreach ([
            'admin.dashboard',
            'admin.finance.index',
            'admin.approvals',
            'admin.rentals.index',
            'admin.inquiries.index',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Missing route name: {$name}");
        }
    }

    public function test_api_named_routes_exist(): void
    {
        foreach ([
            'api.rentals.telemetry',
            'api.rentals.monitor-charts',
            'api.chart-layouts.index',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Missing route name: {$name}");
        }
    }
}

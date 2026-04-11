<?php

namespace Tests\Feature;

use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUserChainDailyChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_chain_includes_full_daily_series_for_filtered_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $subject = User::factory()->create();

        $from = '2026-04-01';
        $to = '2026-04-04';

        RequestLog::query()->create([
            'user_id' => $subject->id,
            'session_id' => 's1',
            'path' => '/dashboard',
            'method' => 'GET',
            'created_at' => '2026-04-02 10:00:00',
        ]);
        RequestLog::query()->create([
            'user_id' => $subject->id,
            'session_id' => 's1',
            'path' => '/profile',
            'method' => 'GET',
            'created_at' => '2026-04-02 11:00:00',
        ]);
        RequestLog::query()->create([
            'user_id' => $subject->id,
            'session_id' => 's1',
            'path' => '/admin',
            'method' => 'GET',
            'created_at' => '2026-04-04 09:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.request-logs.user-chain', [
            'user' => $subject->id,
            'date_from' => $from,
            'date_to' => $to,
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/RequestLogs/UserChain')
            ->has('chain_daily', 4)
            ->where('chain_daily.0.day', '2026-04-01')
            ->where('chain_daily.0.pages', 0)
            ->where('chain_daily.0.activities', 0)
            ->where('chain_daily.1.pages', 2)
            ->where('chain_daily.2.pages', 0)
            ->where('chain_daily.3.day', '2026-04-04')
            ->where('chain_daily.3.pages', 1)
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\UserDashboardDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly UserDashboardDataService $dashboardData,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user()->loadMissing('country');

        return Inertia::render('Dashboard', $this->dashboardData->buildForUser($user));
    }
}

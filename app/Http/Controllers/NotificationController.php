<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Notification $notification)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Notification $notification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Notification $notification)
    {
        //
    }

    public function markRead(Request $request, Notification $notification): Response|RedirectResponse
    {
        $userId = (int) $request->user()->id;
        if ((int) $notification->user_id !== $userId) {
            abort(404);
        }

        $notification->forceFill(['is_read' => true])->save();

        if ($request->header('X-Inertia')) {
            return redirect()->back(303);
        }

        return response()->noContent();
    }

    public function markAllRead(Request $request): Response|RedirectResponse
    {
        $userId = (int) $request->user()->id;
        Notification::query()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'updated_at' => now()]);

        if ($request->header('X-Inertia')) {
            return redirect()->back(303);
        }

        return response()->noContent();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Notification $notification)
    {
        //
    }
}

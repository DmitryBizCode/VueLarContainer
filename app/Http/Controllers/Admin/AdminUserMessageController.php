<?php

namespace App\Http\Controllers\Admin;

use App\Events\UserMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminUserMessageController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $message = UserMessage::query()->create([
            'recipient_user_id' => $user->id,
            'sender_user_id' => $request->user()->id,
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
        ]);

        UserMessageCreated::dispatch($message);

        return back()->with('status', 'Message sent to '.$user->email.'.');
    }
}

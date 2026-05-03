<?php

namespace App\Http\Controllers;

use App\Models\UserMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function show(Request $request, UserMessage $message): RedirectResponse
    {
        if ((int) $message->recipient_user_id !== (int) $request->user()->id) {
            abort(404);
        }

        if ($message->read_at === null) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return redirect()
            ->route('dashboard')
            ->with('status', 'Message marked as read.');
    }
}

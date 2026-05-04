<?php

namespace App\Http\Controllers;

use App\Events\InquirySubmitted;
use App\Http\Requests\StoreInquiryRequest;
use App\Models\Inquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class InquiryController extends Controller
{
    public function store(StoreInquiryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $inquiry = Inquiry::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone_number' => null,
                'telegram_username' => null,
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'source' => 'website',
                'handling_status' => Inquiry::HANDLING_NEW,
                'submitted_by_user_id' => $request->user()?->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('inquiry.store_failed', ['message' => $e->getMessage()]);

            return redirect()
                ->route('contact')
                ->withInput()
                ->with('error', 'We could not save your message. Please try again in a moment.');
        }

        try {
            InquirySubmitted::dispatch($inquiry, $request->user());
        } catch (\Throwable $e) {
            Log::error('inquiry.event_dispatch_failed', ['message' => $e->getMessage()]);
        }

        return redirect()
            ->route('contact')
            ->with('status', 'Message sent. We received your request and will reply soon.');
    }
}

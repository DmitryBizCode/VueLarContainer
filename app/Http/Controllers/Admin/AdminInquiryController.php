<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdminInquiryHandlingRequest;
use App\Models\Inquiry;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminInquiryController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = validator(
            [
                'handling_status' => $request->query('handling_status') !== '' ? $request->query('handling_status') : null,
                'page' => $request->query('page'),
            ],
            [
                'handling_status' => ['nullable', 'string', 'max:40', Rule::in(Inquiry::handlingStatusValues())],
                'page' => ['nullable', 'integer', 'min:1'],
            ]
        )->validate();

        $query = Inquiry::query()
            ->with(['submitter:id,first_name,last_name,email'])
            ->orderByDesc('created_at');

        if (! empty($validated['handling_status'])) {
            $query->where('handling_status', $validated['handling_status']);
        }

        $inquiries = $query->paginate(20)->withQueryString();

        $options = collect(Inquiry::handlingStatusLabels())
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values();

        return Inertia::render('Admin/Inquiries/Index', [
            'inquiries' => $inquiries,
            'handlingStatusOptions' => $options,
            'filters' => [
                'handling_status' => $validated['handling_status'] ?? '',
            ],
        ]);
    }

    public function update(UpdateAdminInquiryHandlingRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $old = $inquiry->only(['handling_status', 'admin_notes']);
        $inquiry->fill($request->validated());
        $inquiry->save();

        ActivityLogService::log(
            $request->user()->id,
            'inquiry_handling_updated',
            'Inquiry',
            $inquiry->id,
            $old,
            $inquiry->only(['handling_status', 'admin_notes']),
            "Inquiry #{$inquiry->id} handling updated",
            $request
        );

        return back()->with('status', 'Inquiry updated.');
    }
}

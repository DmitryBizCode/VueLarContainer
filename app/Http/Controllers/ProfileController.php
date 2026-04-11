<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Country;
use App\Services\ActivityLogService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->loadMissing('country');
        $profileChecklist = $this->buildProfileChecklist($user);
        $profileCompletion = (int) round(
            collect($profileChecklist)->filter()->count() / count($profileChecklist) * 100
        );

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'countries' => Country::query()
                ->orderBy('name')
                ->select(['id', 'name', 'iso_code'])
                ->get(),
            'profileCompletion' => $profileCompletion,
            'profileReadiness' => [
                'items' => collect($profileChecklist)->map(fn ($done, $label) => [
                    'key' => $label,
                    'label' => $label,
                    'done' => $done,
                ])->values(),
                'missingFields' => collect($profileChecklist)
                    ->filter(fn ($done) => ! $done)
                    ->keys()
                    ->values(),
            ],
            'accountSummary' => [
                'role' => $user->role ?? 'client',
                'accountStatus' => $user->account_status ?? 'pending_verification',
                'emailVerified' => ! is_null($user->email_verified_at),
                'memberSince' => $user->created_at,
                'lastUpdatedAt' => $user->updated_at,
                'countryName' => $user->country?->name,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $old = $user->only(['first_name', 'last_name', 'email', 'phone_number', 'address', 'country_id', 'company_name']);
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        ActivityLogService::log(
            $user->id,
            'profile_updated',
            'User',
            $user->id,
            $old,
            $user->only(['first_name', 'last_name', 'email', 'phone_number', 'address', 'country_id', 'company_name']),
            'Profile information updated',
            $request
        );

        return Redirect::route(request()->routeIs('admin.*') ? 'admin.profile.edit' : 'profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        ActivityLogService::logAuth($user->id, 'account_deleted', 'User deleted their account', $request);

        Auth::logout();

        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function buildProfileChecklist(object $user): array
    {
        return [
            'Full name' => filled($user->first_name) && filled($user->last_name),
            'Email' => filled($user->email),
            'Email verification' => ! is_null($user->email_verified_at),
            'Phone' => filled($user->phone_number),
            'Address' => filled($user->address),
            'Country' => ! is_null($user->country_id),
            'Company' => filled($user->company_name),
        ];
    }
}

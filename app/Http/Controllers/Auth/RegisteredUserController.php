<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        $detectedCountryIso = $this->detectCountryIso(request());

        $countries = Country::query()
            ->orderBy('name')
            ->get(['id', 'name', 'iso_code']);

        $detectedCountryId = $detectedCountryIso
            ? $countries->firstWhere('iso_code', $detectedCountryIso)?->id
            : null;

        return Inertia::render('Auth/Register', [
            'countries' => $countries,
            'detected_country_iso' => $detectedCountryIso,
            'detected_country_id' => $detectedCountryId,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'country_id' => 'required|exists:countries,id',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'email' => $request->email,
            'country_id' => $request->integer('country_id'),
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        $this->activityLog->logAuth($user->id, 'registered', 'New user registration', $request);

        return redirect(route('dashboard', absolute: false));
    }

    private function detectCountryIso(Request $request): ?string
    {
        $cfCountry = strtoupper((string) $request->header('CF-IPCountry'));
        if ($this->isValidCountryIso($cfCountry)) {
            return $cfCountry;
        }

        $clientIp = $request->ip();
        $endpoint = $this->shouldUseAutoIpLookup($clientIp)
            ? 'https://ipapi.co/json/'
            : "https://ipapi.co/{$clientIp}/json/";

        try {
            $payload = @file_get_contents($endpoint, false, stream_context_create([
                'http' => ['timeout' => 2],
            ]));

            if ($payload === false) {
                return null;
            }

            $payload = json_decode($payload, true);
            $apiCountry = strtoupper((string) ($payload['country_code'] ?? ''));

            return $this->isValidCountryIso($apiCountry) ? $apiCountry : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function shouldUseAutoIpLookup(?string $ip): bool
    {
        if (blank($ip)) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    private function isValidCountryIso(string $value): bool
    {
        return preg_match('/^[A-Z]{2}$/', $value) === 1;
    }
}

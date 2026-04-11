<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Rental;
use App\Models\User;
use App\Services\PhotoStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(
        private PhotoStorageService $photoStorage
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string', 'max:50'],
            'account_status' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $query = User::query()->with('country')->orderBy('email');

        if (! empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }
        if (! empty($validated['account_status'])) {
            $query->where('account_status', $validated['account_status']);
        }
        if (! empty($validated['q'])) {
            $search = '%'.addslashes($validated['q']).'%';
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', $search)
                    ->orWhere('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('company_name', 'like', $search);
            });
        }

        $users = $query->paginate(15)->withQueryString();

        $users->getCollection()->transform(fn (User $u) => [
            'id' => $u->id,
            'first_name' => $u->first_name,
            'last_name' => $u->last_name,
            'company_name' => $u->company_name,
            'email' => $u->email,
            'email_verified_at' => $u->email_verified_at?->toISOString(),
            'phone_number' => $u->phone_number,
            'address' => $u->address,
            'photo' => $u->photo,
            'account_status' => $u->account_status,
            'role' => $u->role,
            'country_id' => $u->country_id,
            'country_name' => $u->country?->name,
            'created_at' => $u->created_at?->toISOString(),
            'updated_at' => $u->updated_at?->toISOString(),
        ]);

        $countries = Country::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Users/Index', [
            'filters' => [
                'role' => $validated['role'] ?? null,
                'account_status' => $validated['account_status'] ?? null,
                'q' => $validated['q'] ?? null,
            ],
            'users' => $users,
            'countries' => $countries,
            'roleOptions' => ['client', 'admin', 'operator', 'ops'],
        ]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()?->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            return back()->with('error', 'Incorrect password. User was not deleted.');
        }

        $label = $user->email;
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "User “{$label}” moved to archive.");
    }

    public function archive(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $query = User::query()
            ->onlyTrashed()
            ->with('country')
            ->orderByDesc('deleted_at');

        if (! empty($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('email', 'like', '%'.$q.'%')
                    ->orWhere('first_name', 'like', '%'.$q.'%')
                    ->orWhere('last_name', 'like', '%'.$q.'%')
                    ->orWhere('company_name', 'like', '%'.$q.'%');
            });
        }

        $users = $query->paginate(15)->withQueryString();

        $users->getCollection()->transform(fn (User $u) => [
            'id' => $u->id,
            'first_name' => $u->first_name,
            'last_name' => $u->last_name,
            'company_name' => $u->company_name,
            'email' => $u->email,
            'photo' => $u->photo,
            'country_name' => $u->country?->name,
            'role' => $u->role,
            'deleted_at' => $u->deleted_at?->toISOString(),
        ]);

        return Inertia::render('Admin/Users/Archive', [
            'users' => $users,
            'filters' => ['q' => $validated['q'] ?? null],
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $label = $user->email;
        $user->restore();

        return redirect()->route('admin.users.archive')->with('status', "User “{$label}” restored.");
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            return back()->with('error', 'Incorrect password. User was not permanently deleted.');
        }

        $rentalsCount = Rental::where('user_id', $user->id)->count();
        if ($rentalsCount > 0) {
            return back()->with('error', "Cannot permanently delete user “{$user->email}”. They have {$rentalsCount} rental(s). Remove those rentals first.");
        }

        if ($user->photo) {
            $this->photoStorage->delete($user->photo, 'profile');
        }
        $label = $user->email;
        $user->forceDelete();

        return redirect()->route('admin.users.archive')->with('status', "User “{$label}” permanently deleted.");
    }

    public function edit(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.index')->with('openEditUserId', $user->id);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'max:51200'],
            'role' => ['required', 'string', Rule::in(['client', 'admin', 'operator', 'ops'])],
            'account_status' => ['nullable', 'string', 'max:50'],
            'country_id' => ['nullable', 'exists:countries,id'],
        ]);

        if ($request->hasFile('photo')) {
            if ($user->photo) {
                $this->photoStorage->delete($user->photo, 'profile');
            }
            $validated['photo'] = $this->photoStorage->store($request->file('photo'), 'profile');
        } else {
            unset($validated['photo']);
        }

        $user->update($validated);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }
}

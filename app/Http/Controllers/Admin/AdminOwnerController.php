<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Container;
use App\Models\Owner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminOwnerController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $query = Owner::query()->orderBy('name');

        if (! empty($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('phone_number', 'like', '%'.$q.'%');
            });
        }

        $owners = $query->paginate(15)->withQueryString();

        $owners->getCollection()->transform(fn (Owner $o) => [
            'id' => $o->id,
            'name' => $o->name,
            'email' => $o->email,
            'phone_number' => $o->phone_number,
        ]);

        return Inertia::render('Admin/Owners/Index', [
            'owners' => $owners,
            'filters' => ['q' => $validated['q'] ?? null],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Owners/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254'],
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        Owner::query()->create($validated);

        return redirect()->route('admin.owners.index')->with('status', 'Owner created.');
    }

    public function show(Owner $owner): RedirectResponse
    {
        return redirect()->route('admin.owners.edit', $owner);
    }

    public function edit(Owner $owner): Response
    {
        return Inertia::render('Admin/Owners/Edit', [
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'phone_number' => $owner->phone_number,
            ],
        ]);
    }

    public function update(Request $request, Owner $owner): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254'],
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        $owner->update($validated);

        return redirect()->route('admin.owners.index')->with('status', 'Owner updated.');
    }

    public function destroy(Request $request, Owner $owner): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Owner was not deleted.');
        }

        $name = $owner->name;
        $owner->delete();

        return redirect()->route('admin.owners.index')->with('status', "Owner “{$name}” moved to archive.");
    }

    public function archive(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $query = Owner::query()
            ->onlyTrashed()
            ->orderByDesc('deleted_at');

        if (! empty($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('phone_number', 'like', '%'.$q.'%');
            });
        }

        $owners = $query->paginate(15)->withQueryString();

        $owners->getCollection()->transform(fn (Owner $o) => [
            'id' => $o->id,
            'name' => $o->name,
            'email' => $o->email,
            'phone_number' => $o->phone_number,
            'deleted_at' => $o->deleted_at?->toISOString(),
        ]);

        return Inertia::render('Admin/Owners/Archive', [
            'owners' => $owners,
            'filters' => ['q' => $validated['q'] ?? null],
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $owner = Owner::onlyTrashed()->findOrFail($id);
        $name = $owner->name;
        $owner->restore();

        return redirect()->route('admin.owners.archive')->with('status', "Owner “{$name}” restored.");
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        $owner = Owner::onlyTrashed()->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Owner was not permanently deleted.');
        }

        $containersCount = Container::where('owner_id', $owner->id)->count();
        if ($containersCount > 0) {
            return back()->with('error', "Cannot permanently delete owner “{$owner->name}”. They have {$containersCount} container(s). Reassign or remove those containers first.");
        }

        $name = $owner->name;
        $owner->forceDelete();

        return redirect()->route('admin.owners.archive')->with('status', "Owner “{$name}” permanently deleted.");
    }
}

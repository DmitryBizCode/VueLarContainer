<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Container;
use App\Models\Country;
use App\Models\Port;
use App\Models\Route;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminPortController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'country_id' => ['nullable', 'exists:countries,id'],
        ]);

        $query = Port::query()->with('country')->orderBy('name');

        if (! empty($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', '%'.$q.'%')
                    ->orWhere('city', 'like', '%'.$q.'%');
            });
        }
        if (! empty($validated['country_id'] ?? null)) {
            $query->where('country_id', $validated['country_id']);
        }

        $ports = $query->paginate(15)->withQueryString();

        $ports->getCollection()->transform(fn (Port $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'city' => $p->city,
            'country_id' => $p->country_id,
            'country_name' => $p->country?->name,
        ]);

        $countries = Country::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Ports/Index', [
            'ports' => $ports,
            'countries' => $countries,
            'filters' => [
                'q' => $validated['q'] ?? null,
                'country_id' => $validated['country_id'] ?? null,
            ],
        ]);
    }

    public function full(Port $port): JsonResponse
    {
        $port->load('country');

        return response()->json([
            'id' => $port->id,
            'name' => $port->name,
            'city' => $port->city,
            'country_id' => $port->country_id,
            'country_name' => $port->country?->name,
        ]);
    }

    public function create(): Response
    {
        $countries = Country::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Ports/Create', [
            'countries' => $countries,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
        ]);

        Port::query()->create($validated);

        return redirect()->route('admin.ports.index')->with('status', 'Port created.');
    }

    public function show(Port $port): RedirectResponse
    {
        return redirect()->route('admin.ports.edit', $port);
    }

    public function edit(Port $port): Response
    {
        $port->load('country');
        $countries = Country::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Ports/Edit', [
            'port' => [
                'id' => $port->id,
                'country_id' => $port->country_id,
                'name' => $port->name,
                'city' => $port->city,
            ],
            'countries' => $countries,
        ]);
    }

    public function update(Request $request, Port $port): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
        ]);

        $port->update($validated);

        return redirect()->route('admin.ports.index')->with('status', 'Port updated.');
    }

    public function destroy(Request $request, Port $port): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Port was not deleted.');
        }

        $name = $port->name;
        $port->delete();

        return redirect()->route('admin.ports.index')->with('status', "Port “{$name}” moved to archive.");
    }

    public function archive(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $query = Port::query()
            ->onlyTrashed()
            ->with('country')
            ->orderByDesc('deleted_at');

        if (! empty($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', '%'.$q.'%')
                    ->orWhere('city', 'like', '%'.$q.'%');
            });
        }

        $ports = $query->paginate(15)->withQueryString();

        $ports->getCollection()->transform(fn (Port $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'city' => $p->city,
            'country_name' => $p->country?->name,
            'deleted_at' => $p->deleted_at?->toISOString(),
        ]);

        return Inertia::render('Admin/Ports/Archive', [
            'ports' => $ports,
            'filters' => ['q' => $validated['q'] ?? null],
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $port = Port::onlyTrashed()->findOrFail($id);
        $port->restore();

        return redirect()->route('admin.ports.archive')->with('status', "Port “{$port->name}” restored.");
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        $port = Port::onlyTrashed()->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Port was not permanently deleted.');
        }

        $containersCount = Container::where('current_port_id', $port->id)->count();
        if ($containersCount > 0) {
            return back()->with('error', "Cannot permanently delete port “{$port->name}”. It is used by {$containersCount} container(s). Reassign or remove those containers first.");
        }

        $routesCount = Route::where('origin_port_id', $port->id)->orWhere('destination_port_id', $port->id)->count();
        if ($routesCount > 0) {
            return back()->with('error', "Cannot permanently delete port “{$port->name}”. It is used by {$routesCount} route(s). Remove those routes first.");
        }

        $name = $port->name;
        $port->forceDelete();

        return redirect()->route('admin.ports.archive')->with('status', "Port “{$name}” permanently deleted.");
    }
}

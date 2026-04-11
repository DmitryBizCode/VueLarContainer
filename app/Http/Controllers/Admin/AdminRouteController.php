<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Port;
use App\Models\Rental;
use App\Models\Route as ShippingRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminRouteController extends Controller
{
    public function index(): Response
    {
        $routes = ShippingRoute::query()
            ->with(['originPort.country', 'destinationPort.country'])
            ->orderBy('id')
            ->paginate(15);

        $routes->getCollection()->transform(fn (ShippingRoute $r) => [
            'id' => $r->id,
            'origin_port_id' => $r->origin_port_id,
            'origin_name' => $r->originPort?->name,
            'destination_port_id' => $r->destination_port_id,
            'destination_name' => $r->destinationPort?->name,
            'estimated_days' => $r->estimated_days,
            'distance' => (float) $r->distance,
            'route_status' => $r->route_status,
        ]);

        $ports = Port::query()->with('country')->orderBy('name')->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'country' => $p->country?->name]);

        return Inertia::render('Admin/Routes/Index', [
            'routes' => $routes,
            'ports' => $ports,
        ]);
    }

    public function create(): Response
    {
        $ports = Port::query()->with('country')->orderBy('name')->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'country' => $p->country?->name]);

        return Inertia::render('Admin/Routes/Create', [
            'ports' => $ports,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'origin_port_id' => ['required', 'exists:ports,id'],
            'destination_port_id' => ['required', 'exists:ports,id', 'different:origin_port_id'],
            'estimated_days' => ['required', 'integer', 'min:1'],
            'distance' => ['nullable', 'numeric', 'min:0'],
            'route_status' => ['required', 'string', 'in:open,closed'],
        ]);

        $validated['distance'] = $validated['distance'] ?? 0;
        ShippingRoute::query()->create($validated);

        return redirect()->route('admin.routes.index')->with('status', 'Route created.');
    }

    public function show(ShippingRoute $route): RedirectResponse
    {
        return redirect()->route('admin.routes.edit', $route);
    }

    public function edit(ShippingRoute $route): Response
    {
        $route->load(['originPort.country', 'destinationPort.country']);
        $ports = Port::query()->with('country')->orderBy('name')->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'country' => $p->country?->name]);

        return Inertia::render('Admin/Routes/Edit', [
            'routeData' => [
                'id' => $route->id,
                'origin_port_id' => $route->origin_port_id,
                'destination_port_id' => $route->destination_port_id,
                'estimated_days' => $route->estimated_days,
                'distance' => (float) $route->distance,
                'route_status' => $route->route_status,
            ],
            'ports' => $ports,
        ]);
    }

    public function update(Request $request, ShippingRoute $route): RedirectResponse
    {
        $validated = $request->validate([
            'origin_port_id' => ['required', 'exists:ports,id'],
            'destination_port_id' => ['required', 'exists:ports,id', 'different:origin_port_id'],
            'estimated_days' => ['required', 'integer', 'min:1'],
            'distance' => ['nullable', 'numeric', 'min:0'],
            'route_status' => ['required', 'string', 'in:open,closed'],
        ]);

        $validated['distance'] = $validated['distance'] ?? 0;
        $route->update($validated);

        return redirect()->route('admin.routes.index')->with('status', 'Route updated.');
    }

    public function destroy(Request $request, ShippingRoute $route): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Route was not deleted.');
        }

        $label = $route->originPort?->name.' → '.$route->destinationPort?->name;
        $route->delete();

        return redirect()->route('admin.routes.index')->with('status', "Route “{$label}” moved to archive.");
    }

    public function archive(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $query = ShippingRoute::query()
            ->onlyTrashed()
            ->with(['originPort.country', 'destinationPort.country'])
            ->orderByDesc('deleted_at');

        if (! empty($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($qry) use ($q) {
                $qry->whereHas('originPort', fn ($p) => $p->where('name', 'like', '%'.$q.'%')->orWhere('city', 'like', '%'.$q.'%'))
                    ->orWhereHas('destinationPort', fn ($p) => $p->where('name', 'like', '%'.$q.'%')->orWhere('city', 'like', '%'.$q.'%'));
            });
        }

        $routes = $query->paginate(15)->withQueryString();

        $routes->getCollection()->transform(fn (ShippingRoute $r) => [
            'id' => $r->id,
            'origin_name' => $r->originPort?->name,
            'destination_name' => $r->destinationPort?->name,
            'estimated_days' => $r->estimated_days,
            'distance' => (float) $r->distance,
            'route_status' => $r->route_status,
            'deleted_at' => $r->deleted_at?->toISOString(),
        ]);

        return Inertia::render('Admin/Routes/Archive', [
            'routes' => $routes,
            'filters' => ['q' => $validated['q'] ?? null],
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $route = ShippingRoute::onlyTrashed()->findOrFail($id);
        $label = $route->originPort?->name.' → '.$route->destinationPort?->name;
        $route->restore();

        return redirect()->route('admin.routes.archive')->with('status', "Route “{$label}” restored.");
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        $route = ShippingRoute::onlyTrashed()->findOrFail($id);
        $label = $route->originPort?->name.' → '.$route->destinationPort?->name;

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Route was not permanently deleted.');
        }

        $rentalsCount = Rental::where('route_id', $route->id)->count();
        if ($rentalsCount > 0) {
            return back()->with('error', "Cannot permanently delete route “{$label}”. It is used by {$rentalsCount} rental(s). Remove those rentals first.");
        }

        $route->forceDelete();

        return redirect()->route('admin.routes.archive')->with('status', "Route “{$label}” permanently deleted.");
    }
}

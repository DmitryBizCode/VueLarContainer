<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Port;
use App\Models\Vessel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminVesselController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $query = Vessel::query()->with('currentPort')->orderBy('name');

        if (! empty($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', '%'.$q.'%')
                    ->orWhere('imo_number', 'like', '%'.$q.'%')
                    ->orWhere('status', 'like', '%'.$q.'%');
            });
        }

        $vessels = $query->paginate(15)->withQueryString();

        $vessels->getCollection()->transform(fn (Vessel $v) => [
            'id' => $v->id,
            'name' => $v->name,
            'imo_number' => $v->imo_number,
            'capacity_teu' => $v->capacity_teu,
            'status' => $v->status,
            'last_inspection_date' => $v->last_inspection_date?->format('Y-m-d'),
            'current_port_id' => $v->current_port_id,
            'current_port_name' => $v->currentPort?->name,
        ]);

        $ports = Port::query()->with('country')->orderBy('name')->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'country' => $p->country?->name]);

        return Inertia::render('Admin/Vessels/Index', [
            'vessels' => $vessels,
            'ports' => $ports,
            'filters' => ['q' => $validated['q'] ?? null],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.vessels.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'imo_number' => ['required', 'string', 'max:20', 'unique:vessels,imo_number'],
            'capacity_teu' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'max:50'],
            'last_inspection_date' => ['nullable', 'date', 'before_or_equal:today'],
            'current_port_id' => ['nullable', 'exists:ports,id'],
        ]);

        Vessel::query()->create($validated);

        return redirect()->route('admin.vessels.index')->with('status', 'Vessel created.');
    }

    public function show(Vessel $vessel): RedirectResponse
    {
        return redirect()->route('admin.vessels.index');
    }

    public function edit(Vessel $vessel): RedirectResponse
    {
        return redirect()->route('admin.vessels.index');
    }

    public function update(Request $request, Vessel $vessel): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'imo_number' => ['required', 'string', 'max:20', 'unique:vessels,imo_number,'.$vessel->id],
            'capacity_teu' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', 'max:50'],
            'last_inspection_date' => ['nullable', 'date', 'before_or_equal:today'],
            'current_port_id' => ['nullable', 'exists:ports,id'],
        ]);

        $vessel->update($validated);

        return redirect()->route('admin.vessels.index')->with('status', 'Vessel updated.');
    }

    public function destroy(Request $request, Vessel $vessel): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Vessel was not deleted.');
        }

        $name = $vessel->name;
        $vessel->delete();

        return redirect()->route('admin.vessels.index')->with('status', "Vessel “{$name}” moved to archive.");
    }

    public function archive(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $query = Vessel::query()
            ->onlyTrashed()
            ->with('currentPort')
            ->orderByDesc('deleted_at');

        if (! empty($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', '%'.$q.'%')
                    ->orWhere('imo_number', 'like', '%'.$q.'%')
                    ->orWhere('status', 'like', '%'.$q.'%');
            });
        }

        $vessels = $query->paginate(15)->withQueryString();

        $vessels->getCollection()->transform(fn (Vessel $v) => [
            'id' => $v->id,
            'name' => $v->name,
            'imo_number' => $v->imo_number,
            'capacity_teu' => $v->capacity_teu,
            'status' => $v->status,
            'last_inspection_date' => $v->last_inspection_date?->format('Y-m-d'),
            'current_port_name' => $v->currentPort?->name,
            'deleted_at' => $v->deleted_at?->toISOString(),
        ]);

        return Inertia::render('Admin/Vessels/Archive', [
            'vessels' => $vessels,
            'filters' => ['q' => $validated['q'] ?? null],
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $vessel = Vessel::onlyTrashed()->findOrFail($id);
        $name = $vessel->name;
        $vessel->restore();

        return redirect()->route('admin.vessels.archive')->with('status', "Vessel “{$name}” restored.");
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        $vessel = Vessel::onlyTrashed()->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Vessel was not permanently deleted.');
        }

        $name = $vessel->name;
        $vessel->forceDelete();

        return redirect()->route('admin.vessels.archive')->with('status', "Vessel “{$name}” permanently deleted.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Container;
use App\Models\Owner;
use App\Models\Port;
use App\Models\Rental;
use App\Models\SensorType;
use App\Services\ContainerSensorSyncService;
use App\Services\IotAuditChainService;
use App\Services\PhotoStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminContainerController extends Controller
{
    public function __construct(
        private PhotoStorageService $photoStorage
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'owner_id' => ['nullable', 'exists:owners,id'],
            'current_port_id' => ['nullable', 'exists:ports,id'],
            'current_status' => ['nullable', 'string', 'max:50'],
        ]);

        $query = Container::query()
            ->with(['owner', 'currentPort.country'])
            ->orderBy('serial_number');

        if (! empty($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('serial_number', 'like', '%'.$q.'%')
                    ->orWhere('type', 'like', '%'.$q.'%');
            });
        }
        if (! empty($validated['type'] ?? null)) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['owner_id'] ?? null)) {
            $query->where('owner_id', $validated['owner_id']);
        }
        if (isset($validated['current_port_id']) && $validated['current_port_id'] !== '') {
            $query->where('current_port_id', $validated['current_port_id']);
        }
        if (! empty($validated['current_status'] ?? null)) {
            $query->where('current_status', $validated['current_status']);
        }

        $containers = $query->paginate(15)->withQueryString();

        $containers->getCollection()->transform(fn (Container $c) => [
            'id' => $c->id,
            'serial_number' => $c->serial_number,
            'type' => $c->type,
            'width' => (float) $c->width,
            'length' => (float) $c->length,
            'height' => (float) $c->height,
            'max_weight' => (float) $c->max_weight,
            'manufacture_date' => $c->manufacture_date?->format('Y-m-d'),
            'photo' => $c->photo,
            'iot_active' => (bool) $c->iot_active,
            'current_status' => $c->current_status,
            'owner_id' => $c->owner_id,
            'owner_name' => $c->owner?->name,
            'current_port_id' => $c->current_port_id,
            'current_port_name' => $c->currentPort?->name,
            'current_port_country' => $c->currentPort?->country?->name,
        ]);

        $owners = Owner::query()->orderBy('name')->get(['id', 'name']);
        $ports = Port::query()->with('country')->orderBy('name')->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'country' => $p->country?->name]);
        $containerTypePresets = config('containers.type_presets', []);
        $statusOptions = config('containers.status_options', ['available', 'in_use', 'maintenance', 'out_of_service']);

        return Inertia::render('Admin/Containers/Index', [
            'containers' => $containers,
            'filters' => [
                'q' => $validated['q'] ?? null,
                'type' => $validated['type'] ?? null,
                'owner_id' => $validated['owner_id'] ?? null,
                'current_port_id' => $validated['current_port_id'] ?? null,
                'current_status' => $validated['current_status'] ?? null,
            ],
            'owners' => $owners,
            'ports' => $ports,
            'containerTypePresets' => $containerTypePresets,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function full(Container $container): JsonResponse
    {
        $container->load(['owner', 'currentPort.country', 'rentals' => fn ($q) => $q->orderByDesc('created_at')->limit(10)]);

        $rentalsTransformed = $container->rentals->map(fn ($r) => [
            'id' => $r->id,
            'status' => $r->status,
            'payment_status' => $r->payment_status,
            'start_date' => $r->start_date?->toISOString(),
            'end_date' => $r->end_date?->toISOString(),
            'price' => (float) $r->price,
            'created_at' => $r->created_at?->toISOString(),
        ]);

        return response()->json([
            'id' => $container->id,
            'serial_number' => $container->serial_number,
            'type' => $container->type,
            'width' => (float) $container->width,
            'length' => (float) $container->length,
            'height' => (float) $container->height,
            'max_weight' => (float) $container->max_weight,
            'manufacture_date' => $container->manufacture_date?->format('Y-m-d'),
            'photo' => $container->photo,
            'iot_active' => (bool) $container->iot_active,
            'current_status' => $container->current_status,
            'owner_id' => $container->owner_id,
            'current_port_id' => $container->current_port_id,
            'created_at' => $container->created_at?->toISOString(),
            'updated_at' => $container->updated_at?->toISOString(),
            'owner' => $container->owner ? ['id' => $container->owner->id, 'name' => $container->owner->name, 'email' => $container->owner->email, 'phone_number' => $container->owner->phone_number] : null,
            'currentPort' => $container->currentPort ? [
                'id' => $container->currentPort->id,
                'name' => $container->currentPort->name,
                'city' => $container->currentPort->city,
                'country' => $container->currentPort->country?->name,
            ] : null,
            'rentals' => $rentalsTransformed,
            'rentals_count' => $container->rentals()->count(),
        ]);
    }

    public function create(): Response
    {
        $owners = Owner::query()->orderBy('name')->get(['id', 'name']);
        $ports = Port::query()->with('country')->orderBy('name')->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'country' => $p->country?->name]);
        $containerTypePresets = config('containers.type_presets', []);
        $statusOptions = config('containers.status_options', ['available', 'in_use', 'maintenance', 'out_of_service']);

        $sensorTypes = SensorType::query()->orderBy('sort_order')->get(['id', 'slug', 'name', 'category', 'is_optional']);
        $defaultEnabledIds = $sensorTypes->pluck('id')->values()->all();

        return Inertia::render('Admin/Containers/Create', [
            'sensorTypes' => $sensorTypes->map(fn ($st) => [
                'id' => $st->id,
                'slug' => $st->slug,
                'name' => $st->name,
                'category' => $st->category,
                'is_optional' => $st->is_optional,
            ])->values()->all(),
            'enabledSensorTypeIds' => $defaultEnabledIds,
            'owners' => $owners,
            'ports' => $ports,
            'containerTypePresets' => $containerTypePresets,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function store(Request $request, ContainerSensorSyncService $sensorSync): RedirectResponse
    {
        $request->merge([
            'manufacture_date' => $request->input('manufacture_date') ?: null,
        ]);
        $validated = $request->validate([
            'serial_number' => ['required', 'string', 'max:50', 'unique:containers,serial_number'],
            'type' => ['required', 'string', 'max:50'],
            'width' => ['required', 'numeric', 'min:0'],
            'length' => ['required', 'numeric', 'min:0'],
            'height' => ['required', 'numeric', 'min:0'],
            'max_weight' => ['required', 'numeric', 'min:0'],
            'manufacture_date' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'max:51200'],
            'iot_active' => ['boolean'],
            'current_status' => ['required', 'string', 'max:50'],
            'owner_id' => ['required', 'exists:owners,id'],
            'current_port_id' => ['required', 'exists:ports,id'],
            'sensor_enabled' => ['nullable', 'array'],
            'sensor_enabled.*' => ['integer', 'exists:sensor_types,id'],
        ]);

        $validated['iot_active'] = (bool) ($validated['iot_active'] ?? false);
        if ($request->hasFile('photo')) {
            $validated['photo'] = $this->photoStorage->store($request->file('photo'), 'containers');
        } else {
            unset($validated['photo']);
        }
        $container = Container::query()->create($validated);

        if ($validated['iot_active']) {
            $sensorEnabled = array_map('intval', $request->input('sensor_enabled', []));
            if ($sensorEnabled === []) {
                $sensorEnabled = SensorType::query()->pluck('id')->values()->all();
            }
            $sensorSync->syncForContainer($container, true, $sensorEnabled);
        }

        return redirect()->route('admin.containers.index')->with('status', 'Container created successfully.');
    }

    public function show(Container $container): RedirectResponse
    {
        return redirect()->route('admin.containers.edit', $container);
    }

    public function edit(Container $container): Response
    {
        $container->load(['owner', 'currentPort.country', 'containerSensors.sensorType']);
        $owners = Owner::query()->orderBy('name')->get(['id', 'name']);
        $ports = Port::query()->with('country')->orderBy('name')->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'country' => $p->country?->name]);
        $containerTypePresets = config('containers.type_presets', []);
        $statusOptions = config('containers.status_options', ['available', 'in_use', 'maintenance', 'out_of_service']);

        $sensorTypes = SensorType::query()->orderBy('sort_order')->get(['id', 'slug', 'name', 'category', 'is_optional']);
        $enabledSensorTypeIds = $container->containerSensors->where('enabled', true)->pluck('sensor_type_id')->values()->all();

        return Inertia::render('Admin/Containers/Edit', [
            'container' => [
                'id' => $container->id,
                'serial_number' => $container->serial_number,
                'type' => $container->type,
                'width' => (float) $container->width,
                'length' => (float) $container->length,
                'height' => (float) $container->height,
                'max_weight' => (float) $container->max_weight,
                'manufacture_date' => $container->manufacture_date?->format('Y-m-d'),
                'photo' => $container->photo,
                'iot_active' => (bool) $container->iot_active,
                'current_status' => $container->current_status,
                'owner_id' => $container->owner_id,
                'current_port_id' => $container->current_port_id,
            ],
            'sensorTypes' => $sensorTypes->map(fn ($st) => [
                'id' => $st->id,
                'slug' => $st->slug,
                'name' => $st->name,
                'category' => $st->category,
                'is_optional' => $st->is_optional,
            ])->values()->all(),
            'enabledSensorTypeIds' => $enabledSensorTypeIds,
            'owners' => $owners,
            'ports' => $ports,
            'containerTypePresets' => $containerTypePresets,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function quickUpdate(Request $request, Container $container): RedirectResponse
    {
        $validated = $request->validate([
            'current_port_id' => ['nullable', 'exists:ports,id'],
            'current_status' => ['nullable', 'string', 'max:50'],
            'iot_active' => ['nullable', 'boolean'],
        ]);

        $messages = [];

        if (array_key_exists('current_port_id', $validated)) {
            $container->current_port_id = $validated['current_port_id'] ?: null;
            $messages[] = 'Port updated.';
        }
        if (array_key_exists('current_status', $validated)) {
            $container->current_status = $validated['current_status'] ?? $container->current_status;
            $messages[] = 'Status updated.';
        }
        if (array_key_exists('iot_active', $validated)) {
            $container->iot_active = (bool) $validated['iot_active'];
            $messages[] = 'IoT updated.';
        }
        $container->save();

        $message = trim(implode(' ', array_unique($messages))) ?: 'Container updated.';

        return back()->with('status', $message);
    }

    public function update(Request $request, Container $container, ContainerSensorSyncService $sensorSync, IotAuditChainService $audit): RedirectResponse
    {
        // When form is sent with only photo (e.g. file upload), other fields may be missing — use current container values
        $request->merge([
            'manufacture_date' => $request->input('manufacture_date') ?: null,
            'serial_number' => $request->input('serial_number') ?? $container->serial_number,
            'type' => $request->input('type') ?? $container->type,
            'width' => $request->input('width') ?? $container->width,
            'length' => $request->input('length') ?? $container->length,
            'height' => $request->input('height') ?? $container->height,
            'max_weight' => $request->input('max_weight') ?? $container->max_weight,
            'current_status' => $request->input('current_status') ?? $container->current_status,
            'owner_id' => $request->input('owner_id') ?? $container->owner_id,
            'current_port_id' => $request->input('current_port_id') ?? $container->current_port_id,
            'iot_active' => $request->has('iot_active') ? $request->boolean('iot_active') : $container->iot_active,
            'sensor_enabled' => $request->input('sensor_enabled', []),
        ]);
        $validated = $request->validate([
            'serial_number' => ['required', 'string', 'max:50', 'unique:containers,serial_number,'.$container->id],
            'type' => ['required', 'string', 'max:50'],
            'width' => ['required', 'numeric', 'min:0'],
            'length' => ['required', 'numeric', 'min:0'],
            'height' => ['required', 'numeric', 'min:0'],
            'max_weight' => ['required', 'numeric', 'min:0'],
            'manufacture_date' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'max:51200'],
            'iot_active' => ['boolean'],
            'current_status' => ['required', 'string', 'max:50'],
            'owner_id' => ['required', 'exists:owners,id'],
            'current_port_id' => ['required', 'exists:ports,id'],
            'sensor_enabled' => ['nullable', 'array'],
            'sensor_enabled.*' => ['integer', 'exists:sensor_types,id'],
        ]);

        $validated['iot_active'] = (bool) ($validated['iot_active'] ?? false);
        $sensorEnabled = array_map('intval', $request->input('sensor_enabled', []));

        if ($request->hasFile('photo')) {
            if ($container->photo) {
                $this->photoStorage->delete($container->photo, 'containers');
            }
            $validated['photo'] = $this->photoStorage->store($request->file('photo'), 'containers');
        } else {
            unset($validated['photo']);
        }
        $container->update($validated);

        if ($validated['iot_active']) {
            $prevEnabled = $container->containerSensors()->where('enabled', true)->pluck('sensor_type_id')->values()->sort()->values()->all();
            $sensorSync->syncForContainer($container, true, $sensorEnabled);
            $newEnabled = $container->containerSensors()->where('enabled', true)->pluck('sensor_type_id')->values()->sort()->values()->all();
            if ($prevEnabled !== $newEnabled) {
                $activeRental = Rental::query()
                    ->where('container_id', $container->id)
                    ->whereIn('status', ['active', 'in_progress', 'scheduled'])
                    ->orderByDesc('start_date')
                    ->first();
                $typeMap = SensorType::query()->whereIn('id', array_merge($prevEnabled, $newEnabled))->get()->keyBy('id');
                $added = array_diff($newEnabled, $prevEnabled);
                $removed = array_diff($prevEnabled, $newEnabled);
                $audit->append(
                    (int) $container->id,
                    IotAuditChainService::EVENT_SENSOR_TOGGLED,
                    [
                        'source' => 'admin',
                        'enabled_ids' => $newEnabled,
                        'prev_ids' => $prevEnabled,
                        'added' => array_values(array_map(fn ($id) => $typeMap->get($id)?->slug ?? (string) $id, $added)),
                        'removed' => array_values(array_map(fn ($id) => $typeMap->get($id)?->slug ?? (string) $id, $removed)),
                    ],
                    $activeRental?->id,
                    $request->user()?->id
                );
            }
        }

        return redirect()->route('admin.containers.index')->with('status', 'Container updated successfully.');
    }

    public function destroy(Request $request, Container $container): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Container was not deleted.');
        }

        $serial = $container->serial_number;
        $container->delete();

        return redirect()->route('admin.containers.index')->with('status', "Container “{$serial}” moved to archive.");
    }

    public function archive(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Container::query()
            ->onlyTrashed()
            ->with(['owner', 'currentPort.country'])
            ->orderByDesc('deleted_at');

        if (! empty($validated['q'] ?? null)) {
            $q = $validated['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('serial_number', 'like', '%'.$q.'%')
                    ->orWhere('type', 'like', '%'.$q.'%');
            });
        }

        $containers = $query->paginate(15)->withQueryString();

        $containers->getCollection()->transform(fn (Container $c) => [
            'id' => $c->id,
            'serial_number' => $c->serial_number,
            'type' => $c->type,
            'width' => (float) $c->width,
            'length' => (float) $c->length,
            'height' => (float) $c->height,
            'max_weight' => (float) $c->max_weight,
            'manufacture_date' => $c->manufacture_date?->format('Y-m-d'),
            'photo' => $c->photo,
            'deleted_at' => $c->deleted_at?->toISOString(),
            'owner_name' => $c->owner?->name,
            'current_port_name' => $c->currentPort?->name,
            'current_port_country' => $c->currentPort?->country?->name,
        ]);

        return Inertia::render('Admin/Containers/Archive', [
            'containers' => $containers,
            'filters' => ['q' => $validated['q'] ?? null],
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $container = Container::onlyTrashed()->findOrFail($id);
        $container->restore();

        return redirect()->route('admin.containers.archive')->with('status', "Container “{$container->serial_number}” restored.");
    }

    public function forceDestroy(Request $request, int $id): RedirectResponse
    {
        $container = Container::onlyTrashed()->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()->with('error', 'Incorrect password. Container was not permanently deleted.');
        }

        $serial = $container->serial_number;
        if ($container->photo) {
            $this->photoStorage->delete($container->photo, 'containers');
        }
        $container->forceDelete();

        return redirect()->route('admin.containers.archive')->with('status', "Container “{$serial}” permanently deleted.");
    }
}

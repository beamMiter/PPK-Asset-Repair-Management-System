<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequestType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\Toast;

class MaintenanceRequestTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        // Admin only (same gate as the route)
        $this->middleware('can:manage-system');

        $this->middleware('can:viewAny,' . MaintenanceRequestType::class);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', MaintenanceRequestType::class);

        $q = MaintenanceRequestType::query();

        if ($request->filled('active')) {
            $q->where('is_active', (bool) $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $s = trim((string) $request->string('search'));
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $types = $q->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if (!$request->expectsJson()) {
            return view('settings.maintenance-types.index', [
                'types'  => $types,
                'active' => $request->filled('active') ? (int) $request->boolean('active') : null,
                'search' => trim((string) $request->string('search')),
            ]);
        }

        return response()->json(['data' => $types]);
    }

    public function create()
    {
        $this->authorize('create', MaintenanceRequestType::class);
        return view('settings.maintenance-types.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', MaintenanceRequestType::class);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150', 'unique:maintenance_request_types,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'default_response_minutes' => ['nullable', 'integer', 'min:0'],
            'default_resolution_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput()
                ->with('toast', Toast::warning($validator->errors()->first(), 3000));
        }

        $data = $validator->validated();

        $type = MaintenanceRequestType::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : 0,
            'default_response_minutes' => $data['default_response_minutes'] ?? null,
            'default_resolution_minutes' => $data['default_resolution_minutes'] ?? null,
        ]);

        if (!$request->expectsJson()) {
            return redirect()
                ->route('settings.maintenance-types.index')
                ->with('toast', Toast::success('เพิ่มประเภทงานเรียบร้อยแล้ว', 1800));
        }

        return response()->json([
            'message' => 'created',
            'data' => $type,
            'toast' => Toast::success('เพิ่มประเภทงานเรียบร้อยแล้ว', 1800),
        ], 201);
    }

    public function edit(int $id)
    {
        $type = MaintenanceRequestType::findOrFail($id);
        $this->authorize('update', $type);

        return view('settings.maintenance-types.edit', compact('type'));
    }

    public function update(Request $request, int $id)
    {
        $type = MaintenanceRequestType::findOrFail($id);
        $this->authorize('update', $type);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150', Rule::unique('maintenance_request_types', 'name')->ignore($type->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'default_response_minutes' => ['nullable', 'integer', 'min:0'],
            'default_resolution_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput()
                ->with('toast', Toast::warning($validator->errors()->first(), 3000));
        }

        $data = $validator->validated();

        $type->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) $type->is_active,
            'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : (int) $type->sort_order,
            'default_response_minutes' => $data['default_response_minutes'] ?? null,
            'default_resolution_minutes' => $data['default_resolution_minutes'] ?? null,
        ]);

        if (!$request->expectsJson()) {
            return redirect()
                ->route('settings.maintenance-types.index')
                ->with('toast', Toast::success('บันทึกการแก้ไขเรียบร้อยแล้ว', 1800));
        }

        return response()->json([
            'message' => 'updated',
            'data' => $type->fresh(),
            'toast' => Toast::success('บันทึกการแก้ไขเรียบร้อยแล้ว', 1800),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $type = MaintenanceRequestType::findOrFail($id);
        $this->authorize('delete', $type);

        $type->update(['is_active' => false]);

        if (!$request->expectsJson()) {
            return redirect()
                ->route('settings.maintenance-types.index')
                ->with('toast', Toast::success('ปิดใช้งานประเภทเรียบร้อยแล้ว', 1800));
        }

        return response()->json([
            'message' => 'disabled',
            'data' => $type->fresh(),
            'toast' => Toast::success('ปิดใช้งานประเภทเรียบร้อยแล้ว', 1800),
        ]);
    }
}

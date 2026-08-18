<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use App\Http\Resources\TenantSettingResource;
use App\Http\Requests\TenantSettingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TenantSettingController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(TenantSetting::class, 'tenant_setting');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = TenantSetting::query();

        // Filter by key
        if ($request->filled('key')) {
            $query->where('key', 'like', '%' . $request->key . '%');
        }

        // Filter by group
        if ($request->filled('group')) {
            $query->where('group', $request->group);
        }

        $perPage = min($request->per_page ?? 15, 100);
        $tenantSettings = $query->paginate($perPage);

        return TenantSettingResource::collection($tenantSettings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TenantSettingRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $tenantSetting = TenantSetting::create($validated);
            
            DB::commit();

            return new TenantSettingResource($tenantSetting);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(TenantSetting $tenant_setting)
    {
        return new TenantSettingResource($tenant_setting);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TenantSettingRequest $request, TenantSetting $tenant_setting)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $tenant_setting->update($validated);
            
            DB::commit();

            return new TenantSettingResource($tenant_setting);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TenantSetting $tenant_setting)
    {
        $tenant_setting->delete();
        
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get settings by group
     */
    public function getByGroup(string $group)
    {
        $settings = TenantSetting::where('group', $group)->get();
        
        return TenantSettingResource::collection($settings);
    }

    /**
     * Get specific setting by key
     */
    public function getByKey(string $key)
    {
        $setting = TenantSetting::where('key', $key)->firstOrFail();
        
        return new TenantSettingResource($setting);
    }

    /**
     * Update multiple settings at once
     */
    public function batchUpdate(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
            'settings.*.group' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->settings as $settingData) {
                TenantSetting::updateOrCreate(
                    ['key' => $settingData['key']],
                    [
                        'value' => $settingData['value'],
                        'group' => $settingData['group'] ?? null,
                    ]
                );
            }
            
            DB::commit();

            return response()->json(['message' => 'Settings updated successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}
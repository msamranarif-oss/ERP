<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BranchSettingController extends Controller
{
    /**
     * Get branch settings
     */
    public function show(Branch $branch): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $branch->settings ?? []
        ]);
    }

    /**
     * Update branch settings
     */
    public function update(Request $request, Branch $branch): JsonResponse
    {
        $validated = $request->validate([
            'pos_settings' => 'nullable|array',
            'pos_settings.receipt_header' => 'nullable|string',
            'pos_settings.receipt_footer' => 'nullable|string',
            'pos_settings.show_barcode' => 'nullable|boolean',
            'pos_settings.show_customer_info' => 'nullable|boolean',
            'pos_settings.logo_url' => 'nullable|string',
        ]);

        $settings = $branch->settings ?? [];
        $settings['pos_settings'] = array_merge(
            $settings['pos_settings'] ?? [],
            $validated['pos_settings'] ?? []
        );

        $branch->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Branch settings updated successfully.',
            'data' => $branch->settings
        ]);
    }
}

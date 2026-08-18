<?php

namespace App\Services;

use App\Models\Brand;
use App\Services\Interfaces\ServiceInterface;

class BrandService extends BaseService implements ServiceInterface
{
    public function __construct()
    {
        parent::__construct(new Brand());
    }

    /**
     * Get brands with product counts
     */
    public function getWithProductCounts()
    {
        return Brand::withCount('products')
            ->orderBy('name')
            ->get();
    }

    /**
     * Find brand by ID with products count
     */
    public function findByIdWithProducts($id)
    {
        $brand = Brand::with('products')
            ->withCount('products')
            ->findOrFail($id);

        return $brand;
    }

    /**
     * Get active brands only
     */
    public function getActive()
    {
        return Brand::where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Toggle brand active status
     */
    public function toggleActive($id)
    {
        $brand = $this->findById($id);
        $brand->is_active = !$brand->is_active;
        $brand->save();

        return $brand;
    }

    /**
     * Get brands with pagination and search
     */
    public function searchWithPagination($search = null, $perPage = 15)
    {
        $query = Brand::query();

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
        }

        return $query->orderBy('name')
                    ->paginate($perPage);
    }

    /**
     * Create brand with image handling
     */
    public function createWithImage(array $data)
    {
        if (isset($data['image']) && is_string($data['image'])) {
            // Handle base64 image or file path
            $data['image'] = $this->processImage($data['image']);
        }

        return $this->create($data);
    }

    /**
     * Update brand with image handling
     */
    public function updateWithImage($id, array $data)
    {
        if (isset($data['image']) && is_string($data['image'])) {
            // Handle base64 image or file path
            $data['image'] = $this->processImage($data['image']);
        }

        return $this->update($id, $data);
    }

    /**
     * Process image data (placeholder for actual implementation)
     */
    private function processImage($imageData)
    {
        // This would handle image processing, storage, etc.
        // For now, just return the data as-is
        return $imageData;
    }

    /**
     * Get brand statistics
     */
    public function getStatistics()
    {
        return [
            'total' => Brand::count(),
            'active' => Brand::where('is_active', true)->count(),
            'inactive' => Brand::where('is_active', false)->count(),
        ];
    }
}
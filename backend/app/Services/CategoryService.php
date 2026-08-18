<?php

namespace App\Services;

use App\Models\Category;
use App\Services\Interfaces\ServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoryService extends BaseService implements ServiceInterface
{
    public function __construct()
    {
        parent::__construct(new Category());
    }

    /**
     * Get category tree structure
     */
    public function getTree()
    {
        return Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get categories with product counts
     */
    public function getWithProductCounts()
    {
        return Category::withCount('products')
            ->orderBy('name')
            ->get();
    }

    /**
     * Find category by ID with products count
     */
    public function findByIdWithProducts($id)
    {
        $category = Category::with(['products', 'parent'])
            ->withCount('products')
            ->findOrFail($id);

        return $category;
    }

    /**
     * Create category with slug generation
     */
    public function createWithSlug(array $data)
    {
        if (empty($data['slug'])) {
            $data['slug'] = str()->slug($data['name']);
        }

        return $this->create($data);
    }

    /**
     * Update category with slug regeneration if name changed
     */
    public function updateWithSlug($id, array $data)
    {
        $category = $this->findById($id);

        if (isset($data['name']) && $category->name !== $data['name']) {
            $data['slug'] = str()->slug($data['name']);
        }

        return $this->update($id, $data);
    }

    /**
     * Get category hierarchy path
     */
    public function getHierarchyPath($id)
    {
        $category = $this->findById($id);
        $path = [$category];

        while ($category->parent_id) {
            $category = Category::find($category->parent_id);
            if ($category) {
                array_unshift($path, $category);
            } else {
                break;
            }
        }

        return $path;
    }

    /**
     * Get all child categories recursively
     */
    public function getAllChildren($id)
    {
        $category = $this->findById($id);
        $children = [];

        $this->collectChildren($category, $children);

        return $children;
    }

    /**
     * Helper method to recursively collect children
     */
    private function collectChildren($category, &$children)
    {
        $category->load('children');
        
        foreach ($category->children as $child) {
            $children[] = $child;
            $this->collectChildren($child, $children);
        }
    }
}
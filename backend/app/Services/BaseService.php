<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseService
{
    protected $model;

    public function __construct(?Model $model = null)
    {
        $this->model = $model;
    }

    /**
     * Get all records with pagination
     */
    public function getAll(array $filters = [], int $perPage = 15)
    {
        $query = $this->model->newQuery();

        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $query->where($key, $value);
            }
        }

        return $query->paginate($perPage);
    }

    /**
     * Find a record by ID
     */
    public function findById(int $id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Create a new record
     */
    public function create(array $data)
    {
        try {
            DB::beginTransaction();

            $instance = $this->model->create($data);

            DB::commit();

            return $instance;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating ' . get_class($this->model), [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Update an existing record
     */
    public function update(int $id, array $data)
    {
        try {
            DB::beginTransaction();

            $instance = $this->findById($id);
            $instance->fill($data);
            $instance->save();

            DB::commit();

            // Invalidate cache for this model
            if (class_exists('App\\Services\\CachingService')) {
                \App\Services\CachingService::invalidateRelatedCaches(
                    strtolower(class_basename($this->model)), 
                    $id
                );
            }

            return $instance;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating ' . get_class($this->model), [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a record
     */
    public function delete(int $id)
    {
        try {
            DB::beginTransaction();

            $instance = $this->findById($id);
            $result = $instance->delete();

            DB::commit();

            // Invalidate cache for this model
            if (class_exists('App\\Services\\CachingService')) {
                \App\Services\CachingService::invalidateRelatedCaches(
                    strtolower(class_basename($this->model)), 
                    $id
                );
            }

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting ' . get_class($this->model), [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            
            throw $e;
        }
    }
}
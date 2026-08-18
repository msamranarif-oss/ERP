<?php

namespace App\Services\Interfaces;

interface ServiceInterface
{
    /**
     * Get all records with optional filters
     */
    public function getAll(array $filters = [], int $perPage = 15);

    /**
     * Find a record by ID
     */
    public function findById(int $id);

    /**
     * Create a new record
     */
    public function create(array $data);

    /**
     * Update an existing record
     */
    public function update(int $id, array $data);

    /**
     * Delete a record
     */
    public function delete(int $id);
}
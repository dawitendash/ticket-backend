<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;

class RoleService
{
    /**
     * Get paginated roles.
     */
    public function getPaginatedRoles(int $perPage = 10): LengthAwarePaginator
    {
        return Role::latest()->paginate($perPage);
    }

    /**
     * Create a new role.
     * Use Database Transactions to ensure data integrity.
     */
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            try {
                return Role::create($data);
            } catch (Exception $e) {
                Log::error("Error creating role: " . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Update an existing role.
     */
    public function updateRole(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            try {
                $role->update($data);
                return $role;
            } catch (Exception $e) {
                Log::error("Error updating role ID {$role->role_id}: " . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Delete a role.
     */
    public function deleteRole(Role $role): bool
    {
        return DB::transaction(function () use ($role) {
            try {
                return $role->delete();
            } catch (Exception $e) {
                Log::error("Error deleting role ID {$role->role_id}: " . $e->getMessage());
                throw $e;
            }
        });
    }
}
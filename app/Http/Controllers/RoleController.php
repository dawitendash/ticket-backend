<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    // 1. Inject the service via the constructor
    public function __construct(
        protected RoleService $roleService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 100);
        $roles = $this->roleService->getPaginatedRoles($perPage);
        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request): RoleResource
    {
        // 2. Delegate logic to the service
        $role = $this->roleService->createRole($request->validated());
        return new RoleResource($role);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        // 3. Keep controller clean
        $updatedRole = $this->roleService->updateRole($role, $request->validated());
        return new RoleResource($updatedRole);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->deleteRole($role);
        
        return response()->json([
            'message' => 'Role deleted successfully'
        ]);
    }
}
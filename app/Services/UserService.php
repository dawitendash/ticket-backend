<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function listUsers(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->when($filters['role_id'] ?? null, fn($q, $id) => $q->where('role_id', $id))
            ->when($filters['role_slug'] ?? null, function($q, $slug) {
                $q->whereHas('role', fn($query) => $query->where('slug', $slug));
            })
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['is_locked'] ?? null, fn($q, $locked) => $q->where('is_locked', $locked))
            ->when($filters['search'] ?? null, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('user_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhereRaw("JSON_EXTRACT(profile_name, '$.en') LIKE ?", ["%{$search}%"]);
                });
            })
            ->with(['role', 'information'])
            ->latest()
            ->paginate($perPage);
    }

    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $data['user_id'] = Str::uuid();
            $data['password'] = Hash::make($data['password']);
            
            // Set default role if not provided
            if (empty($data['role_id'])) {
                $userRole = \App\Models\Role::where('slug', 'user')->first();
                $data['role_id'] = $userRole?->role_id;
            }

            unset($data['password_confirmation']);
            
            return User::create($data);
        });
    }

    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (isset($data['password']) && $data['password']) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            unset($data['password_confirmation']);
            
            $user->update($data);
            return $user->fresh();
        });
    }

    public function deleteUser(User $user, bool $force = false): void
    {
        DB::transaction(function () use ($user, $force) {
            if ($force) {
                $user->forceDelete();
            } else {
                $user->delete();
            }
        });
    }

    public function restoreUser(string $id): User
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();
        return $user;
    }

    public function updateUserStatus(User $user, int $status): User
    {
        $user->update(['status' => $status]);
        return $user->fresh();
    }

    public function lockUser(User $user, int $minutes = 30): User
    {
        $user->update([
            'is_locked' => true,
            'locked_until' => now()->addMinutes($minutes),
        ]);
        return $user->fresh();
    }

    public function unlockUser(User $user): User
    {
        $user->update([
            'is_locked' => false,
            'locked_until' => null,
            'login_attempts' => 0,
        ]);
        return $user->fresh();
    }

    public function getUserStatistics(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('status', 1)->count(),
            'inactive' => User::where('status', 0)->count(),
           // 'admins' => User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->count(),
            'scanners' => User::whereHas('role', fn($q) => $q->where('slug', 'scanner'))->count(),
            'users' => User::whereHas('role', fn($q) => $q->where('slug', 'user'))->count(),
            'locked' => User::where('is_locked', true)->count(),
            'online' => User::where('last_active_at', '>=', now()->subMinutes(5))->count(),
        ];
    }
}
<?php

namespace App\Services;

use App\Models\UserInformation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserInformationService
{
    /**
     * List user information with filters.
     */
    public function listUserInformation(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return UserInformation::query()
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
            ->when($filters['device_id'] ?? null, fn($q, $id) => $q->where('device_id', $id))
            ->when($filters['has_national_id'] ?? null, function($q) use ($filters) {
                if ($filters['has_national_id']) {
                    return $q->whereNotNull('national_id_number');
                }
                return $q->whereNull('national_id_number');
            })
            ->when($filters['is_complete'] ?? null, function($q) use ($filters) {
                if ($filters['is_complete']) {
                    return $q->whereNotNull('full_name')
                        ->whereNotNull('phone_number')
                        ->whereNotNull('national_id_number');
                }
                return $q->where(function($query) {
                    $query->whereNull('full_name')
                        ->orWhereNull('phone_number')
                        ->orWhereNull('national_id_number');
                });
            })
            ->when($filters['search'] ?? null, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('phone_number', 'LIKE', "%{$search}%")
                        ->orWhere('national_id_number', 'LIKE', "%{$search}%")
                        ->orWhereRaw("JSON_EXTRACT(full_name, '$.en') LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("JSON_EXTRACT(full_name, '$.am') LIKE ?", ["%{$search}%"]);
                });
            })
            ->with(['user', 'device'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get user information by user ID.
     */
    public function getByUserId(string $userId): ?UserInformation
    {
        return UserInformation::with(['user', 'device'])
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Get user information by device ID.
     */
    public function getByDeviceId(string $deviceId): ?UserInformation
    {
        return UserInformation::with(['user', 'device'])
            ->where('device_id', $deviceId)
            ->first();
    }

    /**
     * Get user information by phone number.
     */
    public function getByPhoneNumber(string $phoneNumber): ?UserInformation
    {
        return UserInformation::with(['user', 'device'])
            ->where('phone_number', $phoneNumber)
            ->first();
    }

    /**
     * Find user information by ID.
     */
    public function findUserInformation(string $id): ?UserInformation
    {
        return UserInformation::with(['user', 'device'])->find($id);
    }

    /**
     * Find user information by ID or fail.
     */
    public function findUserInformationOrFail(string $id): UserInformation
    {
        return UserInformation::with(['user', 'device'])->findOrFail($id);
    }

    /**
     * Create user information.
     */
    public function createUserInformation(array $data): UserInformation
    {
        return DB::transaction(function () use ($data) {
            $data['user_information_id'] = Str::uuid();
            
            
            
            return UserInformation::create($data);
        });
    }

    /**
     * Create or update user information (upsert).
     */
    public function createOrUpdateUserInformation(array $data): UserInformation
    {
        return DB::transaction(function () use ($data) {
            $existing = UserInformation::where('user_id', $data['user_id'])->first();
            
            if ($existing) {
                $existing->update($data);
                return $existing->fresh();
            }
            
            $data['user_information_id'] = Str::uuid();
            return UserInformation::create($data);
        });
    }

    /**
     * Update user information.
     */
    public function updateUserInformation(UserInformation $information, array $data): UserInformation
    {
        return DB::transaction(function () use ($information, $data) {
            $information->update($data);
            return $information->fresh();
        });
    }

    /**
     * Delete user information.
     */
    public function deleteUserInformation(UserInformation $information, bool $force = false): bool
    {
        return DB::transaction(function () use ($information, $force) {
            if ($force) {
                return $information->forceDelete();
            }
            return $information->delete();
        });
    }

    /**
     * Delete user information by user ID.
     */
    public function deleteByUserId(string $userId, bool $force = false): bool
    {
        $information = UserInformation::where('user_id', $userId)->first();
        if (!$information) {
            return false;
        }
        return $this->deleteUserInformation($information, $force);
    }

    /**
     * Restore a soft-deleted user information.
     */
    public function restoreUserInformation(string $id): UserInformation
    {
        $information = UserInformation::onlyTrashed()->findOrFail($id);
        $information->restore();
        return $information->fresh();
    }

    /**
     * Check if user has information.
     */
    public function userHasInformation(string $userId): bool
    {
        return UserInformation::where('user_id', $userId)->exists();
    }

    /**
 * Check if device has information.
 */
    public function deviceHasInformation(string $deviceId): bool
    {
        return UserInformation::where('device_id', $deviceId)->exists();
    }

    /**
     * Get user information statistics.
     */
    public function getStatistics(): array
    {
        $total = UserInformation::count();
        $complete = UserInformation::whereNotNull('full_name')
            ->whereNotNull('phone_number')
            ->whereNotNull('national_id_number')
            ->count();
        $withNationalId = UserInformation::whereNotNull('national_id_number')->count();
        
        return [
            'total_users' => $total,
            'complete_profiles' => $complete,
            'incomplete_profiles' => $total - $complete,
            'with_national_id' => $withNationalId,
            'without_national_id' => $total - $withNationalId,
            'completion_rate' => $total > 0 ? round(($complete / $total) * 100, 2) : 0,
        ];
    }
}
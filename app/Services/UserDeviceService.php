<?php

namespace App\Services;

use App\Models\UserDevice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserDeviceService
{
    /**
     * List user devices with filters.
     */
    public function listDevices(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return UserDevice::query()
            ->when($filters['user_id'] ?? null, fn($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['device_type'] ?? null, fn($q, $type) => $q->where('device_type', $type))
            ->when($filters['platform'] ?? null, fn($q, $platform) => $q->where('platform', $platform))
            ->when(isset($filters['is_trusted']), fn($q) => $q->where('is_trusted', $filters['is_trusted']))
            ->when($filters['has_fcm'] ?? null, fn($q) => $q->whereNotNull('fcm_token'))
            ->when($filters['search'] ?? null, function($q, $search) {
                $q->where('device_name', 'LIKE', "%{$search}%")
                    ->orWhere('device_token', 'LIKE', "%{$search}%")
                    ->orWhere('platform', 'LIKE', "%{$search}%");
            })
            ->with(['user'])
            ->orderBy('last_active_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get devices for a specific user.
     */
    public function getUserDevices(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return UserDevice::where('user_id', $userId)
            ->orderBy('last_active_at', 'desc')
            ->get();
    }

    /**
     * Get trusted devices for a user.
     */
    public function getUserTrustedDevices(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return UserDevice::where('user_id', $userId)
            ->where('is_trusted', true)
            ->orderBy('last_active_at', 'desc')
            ->get();
    }

    /**
     * Get online devices for a user.
     */
    public function getUserOnlineDevices(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return UserDevice::where('user_id', $userId)
            ->where('last_active_at', '>=', now()->subMinutes(5))
            ->orderBy('last_active_at', 'desc')
            ->get();
    }

    /**
     * Find a device by ID.
     */
    public function findDevice(string $id): ?UserDevice
    {
        return UserDevice::with(['user'])->find($id);
    }

    /**
     * Find a device by ID or fail.
     */
    public function findDeviceOrFail(string $id): UserDevice
    {
        return UserDevice::with(['user'])->findOrFail($id);
    }

    /**
     * Find a device by user and device token.
     */
    public function findDeviceByToken(string $userId, string $deviceToken): ?UserDevice
    {
        return UserDevice::where('user_id', $userId)
            ->where('device_token', $deviceToken)
            ->first();
    }

    /**
     * Find a device by FCM token.
     */
    public function findDeviceByFcmToken(string $fcmToken): ?UserDevice
    {
        return UserDevice::where('fcm_token', $fcmToken)->first();
    }

    /**
     * Create a new device.
     */
    public function createDevice(array $data): UserDevice
    {
        return DB::transaction(function () use ($data) {
            $data['device_id'] = Str::uuid();
            $data['last_active_at'] = now();
            
            // Generate device token if not provided
            if (empty($data['device_token'])) {
                $data['device_token'] = Str::random(64);
            }
            
            return UserDevice::create($data);
        });
    }

    /**
     * Create or update a device (upsert).
     */
    public function createOrUpdateDevice(array $data): UserDevice
    {
        return DB::transaction(function () use ($data) {
            // Try to find by device token or user + device name
            $device = null;
            
            if (!empty($data['device_token'])) {
                $device = UserDevice::where('device_token', $data['device_token'])->first();
            }
            
            if (!$device && !empty($data['user_id']) && !empty($data['device_name'])) {
                $device = UserDevice::where('user_id', $data['user_id'])
                    ->where('device_name', $data['device_name'])
                    ->first();
            }

            if ($device) {
                // Update existing device
                $device->update([
                    'device_type' => $data['device_type'] ?? $device->device_type,
                    'platform' => $data['platform'] ?? $device->platform,
                    'fcm_token' => $data['fcm_token'] ?? $device->fcm_token,
                    'ip_address' => $data['ip_address'] ?? $device->ip_address,
                    'user_agent' => $data['user_agent'] ?? $device->user_agent,
                    'is_trusted' => $data['is_trusted'] ?? $device->is_trusted,
                    'last_active_at' => now(),
                ]);
                return $device->fresh();
            }

            return $this->createDevice($data);
        });
    }

    /**
     * Update a device.
     */
    public function updateDevice(UserDevice $device, array $data): UserDevice
    {
        return DB::transaction(function () use ($device, $data) {
            $device->update($data);
            return $device->fresh();
        });
    }

    /**
     * Delete a device.
     */
    public function deleteDevice(UserDevice $device, bool $force = false): void
    {
        DB::transaction(function () use ($device, $force) {
            if ($force) {
                $device->forceDelete();
            } else {
                $device->delete();
            }
        });
    }

    /**
     * Delete all devices for a user.
     */
    public function deleteUserDevices(string $userId, bool $force = false): int
    {
        return DB::transaction(function () use ($userId, $force) {
            $query = UserDevice::where('user_id', $userId);
            
            if ($force) {
                return $query->forceDelete();
            }
            
            return $query->delete();
        });
    }

    /**
     * Restore a soft-deleted device.
     */
    public function restoreDevice(string $id): UserDevice
    {
        $device = UserDevice::onlyTrashed()->findOrFail($id);
        $device->restore();
        return $device;
    }

    /**
     * Update last active timestamp.
     */
    public function updateLastActive(string $id): UserDevice
    {
        $device = $this->findDeviceOrFail($id);
        $device->updateLastActive();
        return $device;
    }

    /**
     * Trust a device.
     */
    public function trustDevice(string $id): UserDevice
    {
        $device = $this->findDeviceOrFail($id);
        $device->trust();
        return $device;
    }

    /**
     * Untrust a device.
     */
    public function untrustDevice(string $id): UserDevice
    {
        $device = $this->findDeviceOrFail($id);
        $device->untrust();
        return $device;
    }

    /**
     * Update FCM token for a device.
     */
    public function updateFcmToken(string $id, ?string $fcmToken): UserDevice
    {
        $device = $this->findDeviceOrFail($id);
        $device->updateFcmToken($fcmToken);
        return $device;
    }

    /**
     * Get device statistics for a user.
     */
    public function getUserDeviceStatistics(string $userId): array
    {
        $devices = UserDevice::where('user_id', $userId)->get();
        
        return [
            'total_devices' => $devices->count(),
            'trusted_devices' => $devices->where('is_trusted', true)->count(),
            'online_devices' => $devices->filter(fn($d) => $d->isOnline())->count(),
            'devices_with_fcm' => $devices->whereNotNull('fcm_token')->count(),
            'device_types' => $devices->groupBy('device_type')->map->count(),
            'platforms' => $devices->groupBy('platform')->map->count(),
            'devices' => $devices->map(fn($d) => $d->getDeviceInfo()),
        ];
    }

    /**
     * Get all devices statistics.
     */
    public function getAllDeviceStatistics(): array
    {
        $total = UserDevice::count();
        $trusted = UserDevice::where('is_trusted', true)->count();
        $online = UserDevice::where('last_active_at', '>=', now()->subMinutes(5))->count();
        $withFcm = UserDevice::whereNotNull('fcm_token')->count();
        
        return [
            'total_devices' => $total,
            'trusted_devices' => $trusted,
            'online_devices' => $online,
            'devices_with_fcm' => $withFcm,
            'device_types' => UserDevice::selectRaw('device_type, count(*) as count')
                ->groupBy('device_type')
                ->get()
                ->pluck('count', 'device_type'),
            'platforms' => UserDevice::selectRaw('platform, count(*) as count')
                ->groupBy('platform')
                ->get()
                ->pluck('count', 'platform'),
        ];
    }
}
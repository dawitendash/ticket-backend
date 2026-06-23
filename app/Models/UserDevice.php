<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 
class UserDevice extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'user_devices';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'device_id';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'device_id',
        'user_id',
        'device_name',
        'device_type',
        'platform',
        'device_token',
        'fcm_token',
        'ip_address',
        'user_agent',
        'is_trusted',
        'last_active_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_trusted' => 'boolean',
        'last_active_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'device_type_label',
        'platform_label',
        'last_active_ago',
        'is_online',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the user that owns the device.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // =============================================
    // ACCESSORS
    // =============================================

    /**
     * Get the device type label.
     */
    public function getDeviceTypeLabelAttribute(): string
    {
        $types = [
            'mobile' => 'Mobile',
            'web' => 'Web Browser',
            'tablet' => 'Tablet',
            'desktop' => 'Desktop',
            'other' => 'Other',
        ];
        return $types[$this->device_type] ?? ucfirst($this->device_type ?? 'Unknown');
    }

    /**
     * Get the platform label.
     */
    public function getPlatformLabelAttribute(): string
    {
        $platforms = [
            'ios' => 'iOS',
            'android' => 'Android',
            'windows' => 'Windows',
            'mac' => 'Mac',
            'linux' => 'Linux',
            'web' => 'Web Browser',
        ];
        return $platforms[$this->platform] ?? ucfirst($this->platform ?? 'Unknown');
    }

    /**
     * Get last active ago.
     */
    public function getLastActiveAgoAttribute(): ?string
    {
        return $this->last_active_at?->diffForHumans();
    }

    /**
     * Get is online status (active within last 5 minutes).
     */
    public function getIsOnlineAttribute(): bool
    {
        if (!$this->last_active_at) {
            return false;
        }
        return $this->last_active_at->diffInMinutes(now()) < 5;
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include trusted devices.
     */
    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true);
    }

    /**
     * Scope a query to only include online devices.
     */
    public function scopeOnline($query)
    {
        return $query->where('last_active_at', '>=', now()->subMinutes(5));
    }

    /**
     * Scope a query to only include devices of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('device_type', $type);
    }

    /**
     * Scope a query to only include devices of a specific platform.
     */
    public function scopeOfPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope a query to only include devices for a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include devices with FCM token.
     */
    public function scopeWithFcmToken($query)
    {
        return $query->whereNotNull('fcm_token');
    }

    /**
     * Scope a query to search by device name or token.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('device_name', 'LIKE', "%{$search}%")
            ->orWhere('device_token', 'LIKE', "%{$search}%")
            ->orWhere('platform', 'LIKE', "%{$search}%");
    }

    // =============================================
    // METHODS
    // =============================================

    /**
     * Update last active timestamp.
     */
    public function updateLastActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }

    /**
     * Trust the device.
     */
    public function trust(): void
    {
        $this->update(['is_trusted' => true]);
    }

    /**
     * Untrust the device.
     */
    public function untrust(): void
    {
        $this->update(['is_trusted' => false]);
    }

    /**
     * Update FCM token.
     */
    public function updateFcmToken(?string $fcmToken): void
    {
        $this->update(['fcm_token' => $fcmToken]);
    }

    /**
     * Check if device is online.
     */
    public function isOnline(): bool
    {
        return $this->is_online;
    }

    /**
     * Check if device is trusted.
     */
    public function isTrusted(): bool
    {
        return $this->is_trusted;
    }

    /**
     * Get device info array.
     */
    public function getDeviceInfo(): array
    {
        return [
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'device_type' => $this->device_type,
            'platform' => $this->platform,
            'is_trusted' => $this->is_trusted,
            'is_online' => $this->is_online,
            'last_active_at' => $this->last_active_at?->toDateTimeString(),
        ];
    }
}
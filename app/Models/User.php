<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne; 
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens , HasFactory, HasUuids, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'user_id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_name',
        'profile_name',
        'email',
        'email_verified_at',
        'password',
        'phone_number',
        'profile_picture_url',
        'lang',
        'login_attempts',
        'is_locked',
        'locked_until',
        'last_login',
        'last_active_at',
        'last_login_ip',
        'status',
        'role_id',
        'gate_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'profile_name' => 'array',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'login_attempts' => 'integer',
        'is_locked' => 'boolean',
        'locked_until' => 'datetime',
        'last_login' => 'datetime',
        'last_active_at' => 'datetime',
        'status' => 'integer',
        'gate_number' => 'integer',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_name_localized',
        'is_admin',
        'is_scanner',
        'is_user',
        'status_label', 
    ];
 
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    /**
     * Get the user information.
     */
    public function information(): HasOne
    {
        return $this->hasOne(UserInformation::class, 'user_id', 'user_id');
    }

    /**
     * Get the devices for the user.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class, 'user_id', 'user_id');
    }

    /**
     * Get the tickets for the user.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id', 'user_id');
    }

    /**
     * Get the payment accounts for the user.
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class, 'user_id', 'user_id');
    }

    /**
     * Get the attendance logs for the user.
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'user_id', 'user_id');
    }

    /**
     * Get the attendance logs scanned by this user.
     */
    public function scannedLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'scanned_by', 'user_id');
    }

   
    public function getProfileNameLocalizedAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $this->profile_name[$locale] ?? $this->profile_name['en'] ?? $this->profile_name[array_key_first($this->profile_name)] ?? null;
    }

    /**
     * Check if user is admin.
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->role && $this->role->slug === 'admin';
    }

    /**
     * Check if user is scanner.
     */
    public function getIsScannerAttribute(): bool
    {
        return $this->role && $this->role->slug === 'scanner';
    }

    /**
     * Check if user is regular user.
     */
    public function getIsUserAttribute(): bool
    {
        return $this->role && $this->role->slug === 'user';
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $statuses = [
            1 => 'Active',
            0 => 'Inactive',
            2 => 'Suspended',
            3 => 'Banned',
        ];
        return $statuses[$this->status] ?? 'Unknown';
    }

    /**
     * Check if user is online (active in last 5 minutes).
     */
    public function getIsOnlineAttribute(): bool
    {
        if (!$this->last_active_at) {
            return false;
        }
        return $this->last_active_at->diffInMinutes(now()) < 5;
    }

   
    public function setProfileNameAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['profile_name'] = json_encode($value);
        } else {
            $this->attributes['profile_name'] = $value;
        }
    }

  
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to only include inactive users.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', '!=', 1);
    }

    /**
     * Scope a query to only include admins.
     */
    public function scopeAdmins($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'admin');
        });
    }

    /**
     * Scope a query to only include scanners.
     */
    public function scopeScanners($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'scanner');
        });
    }

    /**
     * Scope a query to only include regular users.
     */
    public function scopeRegularUsers($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'user');
        });
    }

    /**
     * Scope a query to search by name or email.
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('user_name', 'LIKE', "%{$term}%")
            ->orWhere('email', 'LIKE', "%{$term}%")
            ->orWhere('phone_number', 'LIKE', "%{$term}%")
            ->orWhereRaw("JSON_EXTRACT(profile_name, '$.en') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(profile_name, '$.am') LIKE ?", ["%{$term}%"]);
    }

    /**
     * Scope a query to filter by role.
     */
    public function scopeByRole($query, string $roleSlug)
    {
        return $query->whereHas('role', function ($q) use ($roleSlug) {
            $q->where('slug', $roleSlug);
        });
    }

     
    public function hasRole(string $roleSlug): bool
    {
        return $this->role && $this->role->slug === $roleSlug;
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
     public function isCurrentlyLocked(): bool
    {
        if (!$this->is_locked) {
            return false;
        }

        if ($this->locked_until && $this->locked_until->isPast()) {
            $this->unlockAccount();
            return false;
        }

        return true;
    }
    /**
     * Check if user is scanner.
     */
    public function isScanner(): bool
    {
        return $this->hasRole('scanner');
    }

    /**
     * Check if user is regular user.
     */
    public function isUser(): bool
    {
        return $this->hasRole('user');
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 1;
    }

    /**
     * Check if user is locked.
     */
    public function isLocked(): bool
    {
        if (!$this->is_locked) {
            return false;
        }
        
        if ($this->locked_until && $this->locked_until->isPast()) {
            $this->update(['is_locked' => false, 'locked_until' => null]);
            return false;
        }
        
        return true;
    }

    /**
     * Increment login attempts.
     */
    public function incrementLoginAttempts(): void
    {
        $this->increment('login_attempts');
        
        if ($this->login_attempts >= 5) {
            $this->update([
                'is_locked' => true,
                'locked_until' => now()->addMinutes(30),
            ]);
        }
    }

    /**
     * Reset login attempts.
     */
    public function resetLoginAttempts(): void
    {
        $this->update([
            'login_attempts' => 0,
            'is_locked' => false,
            'locked_until' => null,
        ]);
    }

    /**
     * Record user login.
     */
    public function recordLogin(string $ip = null): void
    {
        $this->update([
            'last_login' => now(),
            'last_active_at' => now(),
            'last_login_ip' => $ip ?? request()->ip(),
            'login_attempts' => 0,
            'is_locked' => false,
            'locked_until' => null,
        ]);
    }

    /**
     * Update last active timestamp.
     */
    public function updateLastActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }

    /**
     * Check if user has completed profile.
     */
    public function hasCompletedProfile(): bool
    {
        return $this->information && 
               $this->information->full_name && 
               $this->information->phone_number;
    }

    /**
     * Get user's full name.
     */
    public function getFullName(): ?string
    {
        return $this->profile_name['en'] ?? $this->profile_name[array_key_first($this->profile_name)] ?? $this->user_name;
    }

    /**
     * Get user's full name in specific language.
     */
    public function getFullNameIn(string $locale): ?string
    {
        return $this->profile_name[$locale] ?? $this->profile_name['en'] ?? $this->user_name;
    }
 
    

   
}
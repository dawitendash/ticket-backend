<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 

class UserInformation extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'user_informations';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'user_information_id';

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
        'user_information_id',
        'user_id',
        'device_id',
        'full_name',
        'phone_number',
        'national_id_front_image',
        'national_id_back_image',
        'national_id_number',
        'address',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'full_name' => 'array',
        'address' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'has_national_id',
        'is_complete',
        'display_name',
        'display_address',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the user that owns the information.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the device that owns the information.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id', 'device_id');
    }

    // =============================================
    // ACCESSORS
    // =============================================

    /**
     * Get the display name from full_name.
     */
    public function getDisplayNameAttribute(): ?string
    {
        if (!is_array($this->full_name) || empty($this->full_name)) {
            return null;
        }
        
        $locale = app()->getLocale();
        return $this->full_name[$locale] 
            ?? $this->full_name['en'] 
            ?? $this->full_name[array_key_first($this->full_name)] 
            ?? null;
    }

    /**
     * Get the display address from address.
     */
    public function getDisplayAddressAttribute(): ?string
    {
        if (!is_array($this->address) || empty($this->address)) {
            return null;
        }
        
        $locale = app()->getLocale();
        return $this->address[$locale] 
            ?? $this->address['en'] 
            ?? $this->address[array_key_first($this->address)] 
            ?? null;
    }

    /**
     * Check if user has national ID.
     */
    public function getHasNationalIdAttribute(): bool
    {
        return !empty($this->national_id_number);
    }

    /**
     * Check if the user information is complete.
     */
    public function getIsCompleteAttribute(): bool
    {
        return !empty($this->full_name) 
            && !empty($this->phone_number) 
            && !empty($this->national_id_number);
    }

    // =============================================
    // MUTATORS
    // =============================================

    /**
     * Set the full_name attribute.
     */
    public function setFullNameAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['full_name'] = json_encode($value);
        } else {
            $this->attributes['full_name'] = $value;
        }
    }

    /**
     * Set the address attribute.
     */
    public function setAddressAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['address'] = json_encode($value);
        } else {
            $this->attributes['address'] = $value;
        }
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include information for a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include information for a specific device.
     */
    public function scopeForDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Scope a query to only include complete information.
     */
    public function scopeComplete($query)
    {
        return $query->whereNotNull('full_name')
            ->whereNotNull('phone_number')
            ->whereNotNull('national_id_number');
    }

    /**
     * Scope a query to only include information with national ID.
     */
    public function scopeWithNationalId($query)
    {
        return $query->whereNotNull('national_id_number');
    }

    /**
     * Scope a query to search by phone number or name.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('phone_number', 'LIKE', "%{$search}%")
                ->orWhere('national_id_number', 'LIKE', "%{$search}%")
                ->orWhereRaw("JSON_EXTRACT(full_name, '$.en') LIKE ?", ["%{$search}%"])
                ->orWhereRaw("JSON_EXTRACT(full_name, '$.am') LIKE ?", ["%{$search}%"]);
        });
    }

  
    /**
     * Check if the information is complete.
     */
    public function isComplete(): bool
    {
        return $this->is_complete;
    }

    /**
     * Mark as complete if all required fields are filled.
     */
    public function updateCompleteStatus(): void
    {
        $isComplete = !empty($this->full_name) 
            && !empty($this->phone_number) 
            && !empty($this->national_id_number);
      
    }
}
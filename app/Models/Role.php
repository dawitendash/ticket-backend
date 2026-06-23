<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'role_id';

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
        'role_id',
        'name',
        'description',
        'slug',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

  
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id', 'role_id');
    }

  
    public function setNameAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['name'] = json_encode($value);
        } else {
            $this->attributes['name'] = $value;
        }
    }

    /**
     * Set the description attribute.
     */
    public function setDescriptionAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['description'] = json_encode($value);
        } else {
            $this->attributes['description'] = $value;
        }
    }

    
    public function scopeSlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Scope a query to filter by role name (search).
     */
    public function scopeSearch($query, string $term)
    {
        return $query->whereRaw("JSON_EXTRACT(name, '$.en') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(name, '$.am') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(description, '$.en') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(description, '$.am') LIKE ?", ["%{$term}%"]);
    }

    
    public function isAdmin(): bool
    {
        return $this->slug === 'admin';
    }

    /**
     * Check if this is the scanner role.
     */
    public function isScanner(): bool
    {
        return $this->slug === 'scanner';
    }

    /**
     * Check if this is the user role.
     */
    public function isUser(): bool
    {
        return $this->slug === 'user';
    }

    /**
     * Get role by slug.
     */
    public static function getBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->first();
    }

    /**
     * Get admin role.
     */
    public static function getAdmin(): ?self
    {
        return self::where('slug', 'admin')->first();
    }

    /**
     * Get scanner role.
     */
    public static function getScanner(): ?self
    {
        return self::where('slug', 'scanner')->first();
    }

    /**
     * Get user role.
     */
    public static function getUser(): ?self
    {
        return self::where('slug', 'user')->first();
    }

    /**
     * Get all roles with their user counts.
     */
    public static function getWithUserCounts()
    {
        return self::withCount('users')->get();
    }

    /**
     * Get role options for dropdowns.
     */
    public static function getOptions(): array
    {
        return self::all()->mapWithKeys(function ($role) {
            $name = $role->name['en'] ?? $role->name[array_key_first($role->name)] ?? 'Unknown';
            return [$role->role_id => $name];
        })->toArray();
    }
 
 
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TicketType extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket_types';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ticket_type_id';

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
        'ticket_type_id',
        'concert_id',
        'ticket_type_name',
        'ticket_type_description',
        'price',
        'capacity',
        'sold_count',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ticket_type_name' => 'array',
        'ticket_type_description' => 'array',
        'price' => 'decimal:2',
        'capacity' => 'integer',
        'sold_count' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'available_tickets',
        'is_available',
        'is_sold_out',
        'formatted_price',
        'sold_percentage',
    ];

 
    public function concert(): BelongsTo
    {
        return $this->belongsTo(Concert::class, 'concert_id', 'concert_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_type_id', 'ticket_type_id');
    }

    public function capacityTracking(): HasMany
    {
        return $this->hasMany(ConcertCapacity::class, 'ticket_type_id', 'ticket_type_id');
    }

  
    public function getAvailableTicketsAttribute(): int
    {
        return $this->capacity - $this->sold_count;
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->available_tickets > 0 && $this->is_active;
    }

    public function getIsSoldOutAttribute(): bool
    {
        return $this->available_tickets <= 0;
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2) . ' ETB';
    }

    public function getSoldPercentageAttribute(): float
    {
        if ($this->capacity === 0) {
            return 0;
        }
        return round(($this->sold_count / $this->capacity) * 100, 2);
    }

   
    public function setTicketTypeNameAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['ticket_type_name'] = json_encode($value);
        } else {
            $this->attributes['ticket_type_name'] = $value;
        }
    }

    public function setTicketTypeDescriptionAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['ticket_type_description'] = json_encode($value);
        } else {
            $this->attributes['ticket_type_description'] = $value;
        }
    }

 
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->whereRaw('capacity > sold_count');
    }

    public function scopeSoldOut($query)
    {
        return $query->where('is_active', true)
            ->whereRaw('capacity <= sold_count');
    }

    public function scopePriceBetween($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    public function scopeMinPrice($query, $min)
    {
        return $query->where('price', '>=', $min);
    }

    public function scopeMaxPrice($query, $max)
    {
        return $query->where('price', '<=', $max);
    }

    public function scopeSearch($query, $term)
    {
        return $query->whereRaw("JSON_EXTRACT(ticket_type_name, '$.en') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(ticket_type_name, '$.am') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(ticket_type_description, '$.en') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(ticket_type_description, '$.am') LIKE ?", ["%{$term}%"]);
    }

  
    public function incrementSoldCount(int $amount = 1): bool
    {
        if ($this->available_tickets < $amount) {
            return false;
        }
        
        $this->increment('sold_count', $amount);
        return true;
    }

    public function decrementSoldCount(int $amount = 1): bool
    {
        if ($this->sold_count < $amount) {
            return false;
        }
        
        $this->decrement('sold_count', $amount);
        return true;
    }

    public function canPurchase(int $quantity = 1): bool
    {
        return $this->is_active && $this->available_tickets >= $quantity;
    }

    
}
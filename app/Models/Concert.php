<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Concert extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'concerts';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'concert_id';

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
        'concert_id',
        'name',
        'artist',
        'venue',
        'description',
        'concert_date',
        'door_open_time',
        'status',
        'max_capacity',
        'image_url',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'array',
        'artist' => 'array',
        'venue' => 'array',
        'description' => 'array',
        'concert_date' => 'datetime',
        'door_open_time' => 'datetime',
        'max_capacity' => 'integer',
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
        'is_sold_out',
        'formatted_concert_date',
        'formatted_door_open_time',
        'days_until_concert',
        'status_label',
        'status_color',
        'total_tickets_sold',
        'total_revenue',
        'checked_in_count',
    ];

  
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class, 'concert_id', 'concert_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'concert_id', 'concert_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'concert_id', 'concert_id');
    }

    public function capacity(): HasMany
    {
        return $this->hasMany(ConcertCapacity::class, 'concert_id', 'concert_id');
    }

  
    public function getAvailableTicketsAttribute(): int
    {
        $soldCount = $this->tickets()->where('status', 'active')->count();
        return $this->max_capacity - $soldCount;
    }

    public function getIsSoldOutAttribute(): bool
    {
        return $this->available_tickets <= 0;
    }

    public function getFormattedConcertDateAttribute(): string
    {
        return $this->concert_date->format('F j, Y, g:i A');
    }

    public function getFormattedDoorOpenTimeAttribute(): string
    {
        return $this->door_open_time->format('F j, Y, g:i A');
    }

    public function getDaysUntilConcertAttribute(): ?int
    {
        if ($this->concert_date->isPast()) {
            return null;
        }
        return now()->diffInDays($this->concert_date);
    }

    public function getTotalTicketsSoldAttribute(): int
    {
        return $this->tickets()->where('status', 'active')->count();
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->tickets()
            ->where('status', 'active')
            ->sum('price_paid');
    }

    public function getCheckedInCountAttribute(): int
    {
        return $this->attendanceLogs()
            ->where('status', 'success')
            ->distinct('ticket_id')
            ->count('ticket_id');
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'upcoming' => 'Upcoming',
            'ongoing' => 'Ongoing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'upcoming' => 'blue',
            'ongoing' => 'green',
            'completed' => 'gray',
            'cancelled' => 'red',
        ];
        return $colors[$this->status] ?? 'gray';
    }
 
    public function setNameAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['name'] = json_encode($value);
        } else {
            $this->attributes['name'] = $value;
        }
    }

    public function setArtistAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['artist'] = json_encode($value);
        } else {
            $this->attributes['artist'] = $value;
        }
    }

    public function setVenueAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['venue'] = json_encode($value);
        } else {
            $this->attributes['venue'] = $value;
        }
    }

    public function setDescriptionAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['description'] = json_encode($value);
        } else {
            $this->attributes['description'] = $value;
        }
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming')
            ->where('concert_date', '>', now());
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['upcoming', 'ongoing']);
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('concert_date', $date);
    }

    public function scopeDateBetween($query, $start, $end)
    {
        return $query->whereBetween('concert_date', [$start, $end]);
    }

    public function scopeSearch($query, $term)
    {
        return $query->whereRaw("JSON_EXTRACT(name, '$.en') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(name, '$.am') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(artist, '$.en') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(artist, '$.am') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(venue, '$.en') LIKE ?", ["%{$term}%"])
            ->orWhereRaw("JSON_EXTRACT(venue, '$.am') LIKE ?", ["%{$term}%"]);
    }

    // =============================================
    // HELPER METHODS
    // =============================================

    public function isUpcoming(): bool
    {
        return $this->status === 'upcoming' && $this->concert_date->isFuture();
    }

    public function isOngoing(): bool
    {
        return $this->status === 'ongoing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['upcoming', 'ongoing']);
    }

    public function getTicketTypeByName(string $name): ?TicketType
    {
        return $this->ticketTypes()
            ->where('ticket_type_name->en', $name)
            ->orWhere('ticket_type_name->am', $name)
            ->first();
    }

    public function getTicketTypesWithAvailability(): array
    {
        return $this->ticketTypes->map(function ($type) {
            return [
                'id' => $type->ticket_type_id,
                'name' => $type->ticket_type_name, // Returns full JSON
                'price' => $type->price,
                'capacity' => $type->capacity,
                'sold' => $type->sold_count,
                'available' => $type->capacity - $type->sold_count,
                'is_available' => ($type->capacity - $type->sold_count) > 0,
            ];
        })->toArray();
    }

    public function getAttendanceStats(): array
    {
        $totalTickets = $this->tickets()->where('status', 'active')->count();
        $checkedIn = $this->attendanceLogs()
            ->where('status', 'success')
            ->distinct('ticket_id')
            ->count('ticket_id');

        return [
            'total_tickets' => $totalTickets,
            'checked_in' => $checkedIn,
            'remaining' => $totalTickets - $checkedIn,
            'percentage' => $totalTickets > 0 ? round(($checkedIn / $totalTickets) * 100, 2) : 0,
        ];
    }

    public function getStatusWithColor(): array
    {
        $colors = [
            'upcoming' => 'blue',
            'ongoing' => 'green',
            'completed' => 'gray',
            'cancelled' => 'red',
        ];

        return [
            'status' => $this->status,
            'color' => $colors[$this->status] ?? 'gray',
            'label' => ucfirst($this->status),
        ];
    }

    public static function getNextUpcoming(): ?self
    {
        return self::upcoming()->orderBy('concert_date')->first();
    }

    public static function getFeatured(int $limit = 4)
    {
        return self::active()->orderBy('concert_date')->limit($limit)->get();
    }
 
}
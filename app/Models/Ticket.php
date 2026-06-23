<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; 
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'tickets';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'ticket_id';

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
        'ticket_id',
        'device_id',
        'user_id',
        'ticket_type_id',
        'concert_id',
        'order_reference',
        'qr_code',
        'ticket_number',
        'price_paid',
        'status',
        'purchase_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price_paid' => 'decimal:2',
        'purchase_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'formatted_price',
        'status_label',
        'is_active',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id', 'device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'ticket_type_id', 'ticket_type_id');
    }

    public function concert(): BelongsTo
    {
        return $this->belongsTo(Concert::class, 'concert_id', 'concert_id');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'ticket_id', 'ticket_id');
    }

    public function userInformation(): BelongsTo
    {
        return $this->belongsTo(UserInformation::class, 'device_id', 'device_id');
    }

    // =============================================
    // ACCESSORS
    // =============================================

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price_paid, 2) . ' ETB';
    }

    public function getStatusLabelAttribute(): string
    {
        $statuses = [
            'active' => 'Active',
            'used' => 'Used',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ];
        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    // =============================================
    // SCOPES
    // =============================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForConcert($query, string $concertId)
    {
        return $query->where('concert_id', $concertId);
    }

    public function scopeForTicketType($query, string $ticketTypeId)
    {
        return $query->where('ticket_type_id', $ticketTypeId);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('ticket_number', 'LIKE', "%{$search}%")
                ->orWhere('order_reference', 'LIKE', "%{$search}%")
                ->orWhere('qr_code', 'LIKE', "%{$search}%");
        });
    }

    // =============================================
    // METHODS
    // =============================================

    /**
     * Mark ticket as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['status' => 'used']);
    }

    public function getUserInformationByDevice()
    {
        if ($this->device_id) {
            return UserInformation::where('device_id', $this->device_id)->first();
        }
        return null;
    }
        /**
     * Mark ticket as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Mark ticket as refunded.
     */
    public function markAsRefunded(): void
    {
        $this->update(['status' => 'refunded']);
    }

    /**
     * Check if ticket is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if ticket is used.
     */
    public function isUsed(): bool
    {
        return $this->status === 'used';
    }

    /**
     * Check if ticket is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if ticket is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Generate QR code.
     */
    public static function generateQrCode(): string
    {
        return 'QR-' . Str::random(32);
    }

    /**
     * Generate ticket number.
     */
    public static function generateTicketNumber(): string
    {
        return 'TKT-' . strtoupper(Str::random(8)) . '-' . date('Ymd');
    }

    /**
     * Generate order reference.
     */
    public static function generateOrderReference(): string
    {
        return 'ORD-' . strtoupper(Str::random(10)) . '-' . date('Ymd');
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($ticket) {
            $ticket->ticket_id = Str::uuid();
            $ticket->ticket_number = $ticket->ticket_number ?? self::generateTicketNumber();
            $ticket->qr_code = $ticket->qr_code ?? self::generateQrCode();
            $ticket->order_reference = $ticket->order_reference ?? self::generateOrderReference();
            $ticket->purchase_date = $ticket->purchase_date ?? now();
        });
    }
}
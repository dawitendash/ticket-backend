<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceLog extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'attendance_logs';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'attendance_log_id';

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
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attendance_log_id',
        'ticket_id',
        'concert_id',
        'device_id',
        'user_id',
        'scanned_by',
        'gate_number',
        'scan_time',
        'status',
        'failure_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scan_time' => 'datetime',
        'failure_reason' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'status_label',
        'status_color',
        'is_success',
        'is_failure',
        'formatted_scan_time',
        'scanned_by_name',
    ];

 
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    /**
     * Get the concert associated with the attendance log.
     */
    public function concert(): BelongsTo
    {
        return $this->belongsTo(Concert::class, 'concert_id', 'concert_id');
    }

    /**
     * Get the device associated with the attendance log.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id', 'device_id');
    }

    /**
     * Get the user associated with the attendance log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the scanner user.
     */
    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by', 'user_id');
    }

    
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'success' => 'Success',
            'already_used' => 'Already Used',
            'invalid' => 'Invalid',
            'expired' => 'Expired',
        ];
        return $labels[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * Get status color.
     */
    public function getStatusColorAttribute(): string
    {
        $colors = [
            'success' => 'green',
            'already_used' => 'yellow',
            'invalid' => 'red',
            'expired' => 'blue',
        ];
        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Check if scan was successful.
     */
    public function getIsSuccessAttribute(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if scan was a failure.
     */
    public function getIsFailureAttribute(): bool
    {
        return $this->status !== 'success';
    }

    /**
     * Get formatted scan time.
     */
    public function getFormattedScanTimeAttribute(): string
    {
        return $this->scan_time->format('F j, Y, g:i A');
    }

    /**
     * Get scanner name.
     */
    public function getScannedByNameAttribute(): ?string
    {
        return $this->scanner ? $this->scanner->user_name : null;
    }

    /**
     * Get failure reason in current locale.
     */
    public function getFailureReasonLocalizedAttribute(): ?string
    {
        if (!$this->failure_reason) {
            return null;
        }
        
        $locale = app()->getLocale();
        return $this->failure_reason[$locale] ?? $this->failure_reason['en'] ?? null;
    }

    /**
     * Get failure reason in English.
     */
    public function getFailureReasonEnAttribute(): ?string
    {
        return $this->failure_reason['en'] ?? null;
    }

    /**
     * Get failure reason in Amharic.
     */
    public function getFailureReasonAmAttribute(): ?string
    {
        return $this->failure_reason['am'] ?? null;
    }
 
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed scans.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', '!=', 'success');
    }

    /**
     * Scope a query to only include scans for a specific concert.
     */
    public function scopeForConcert($query, string $concertId)
    {
        return $query->where('concert_id', $concertId);
    }

    /**
     * Scope a query to only include scans for a specific ticket.
     */
    public function scopeForTicket($query, string $ticketId)
    {
        return $query->where('ticket_id', $ticketId);
    }

    /**
     * Scope a query to only include scans for a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include scans by a specific scanner.
     */
    public function scopeScannedBy($query, string $userId)
    {
        return $query->where('scanned_by', $userId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateBetween($query, $start, $end)
    {
        return $query->whereBetween('scan_time', [$start, $end]);
    }

    /**
     * Scope a query to filter by gate number.
     */
    public function scopeGate($query, string $gateNumber)
    {
        return $query->where('gate_number', $gateNumber);
    }

    /**
     * Scope a query to get latest scans first.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('scan_time', 'desc');
    }

 
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if scan was a failure.
     */
    public function isFailure(): bool
    {
        return $this->status !== 'success';
    }

    /**
     * Get failure reason.
     */
    public function getFailureReason(?string $locale = null): ?string
    {
        if (!$this->failure_reason) {
            return null;
        }
        
        if ($locale) {
            return $this->failure_reason[$locale] ?? $this->failure_reason['en'] ?? null;
        }
        
        return $this->failure_reason_localized;
    }

    /**
     * Get attendance log summary.
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->attendance_log_id,
            'ticket_number' => $this->ticket ? $this->ticket->ticket_number : null,
            'concert_name' => $this->concert ? $this->concert->name : null,
            'user_name' => $this->user ? $this->user->user_name : null,
            'scanned_by' => $this->scanned_by_name,
            'gate_number' => $this->gate_number,
            'scan_time' => $this->formatted_scan_time,
            'status' => $this->status_label,
            'is_success' => $this->is_success,
        ];
    }
}
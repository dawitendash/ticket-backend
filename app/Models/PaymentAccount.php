<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 

class PaymentAccount extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'payment_accounts';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'payment_account_id';

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
        'payment_account_id',
        'user_id',
        'account_type',
        'owner_name',
        'account_identifier',
        'provider',
        'last_four',
        'expiry_month',
        'expiry_year',
        'meta',
        'is_default',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'meta' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'expiry_month' => 'integer',
        'expiry_year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'account_type_label',
        'status_label',
        'masked_account',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * Get the user that owns the payment account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // =============================================
    // ACCESSORS
    // =============================================

    /**
     * Get the account type label.
     */
    public function getAccountTypeLabelAttribute(): string
    {
        $types = [
            'bank' => 'Bank Account',
            'mobile_banking' => 'Mobile Money',
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
            'card' => 'Credit/Debit Card',
        ];
        return $types[$this->account_type] ?? ucfirst($this->account_type ?? 'Unknown');
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    /**
     * Get masked account identifier.
     */
    public function getMaskedAccountAttribute(): string
    {
        if ($this->last_four) {
            return '****' . $this->last_four;
        }
        
        if (strlen($this->account_identifier) > 4) {
            return '****' . substr($this->account_identifier, -4);
        }
        
        return $this->account_identifier;
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope a query to only include active payment accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include default payment accounts.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to only include accounts of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Scope a query to only include accounts for a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to search by owner name or account identifier.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('owner_name', 'LIKE', "%{$search}%")
                ->orWhere('account_identifier', 'LIKE', "%{$search}%")
                ->orWhere('provider', 'LIKE', "%{$search}%");
        });
    }

    // =============================================
    // METHODS
    // =============================================

    /**
     * Make this account the default for the user.
     */
    public function makeDefault(): void
    {
        // Remove default from other accounts for this user
        static::where('user_id', $this->user_id)
            ->where('payment_account_id', '!=', $this->payment_account_id)
            ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    /**
     * Activate the account.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the account.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            // If this is the first account for the user, make it default
            $existingCount = static::where('user_id', $model->user_id)->count();
            if ($existingCount === 0) {
                $model->is_default = true;
            }
        });
    }
}
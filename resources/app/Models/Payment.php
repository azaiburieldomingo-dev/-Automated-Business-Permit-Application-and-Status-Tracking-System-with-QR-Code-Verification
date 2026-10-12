<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_PAID = 'PAID';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const VERIFY_UNVERIFIED = 'UNVERIFIED';

    public const VERIFY_VERIFIED = 'VERIFIED';

    protected $fillable = [
        'application_id',
        'tracking_number',
        'amount',
        'breakdown_json',
        'method',
        'reference_number',
        'status',
        'verification_status',
        'payment_date',
        'verified_at',
        'verified_by',
        'payer_name',
        'payer_number',
        'proof_path',
        'proof_original_name',
    ];

    /**
     * Awaiting a BPLO decision (submitted, not yet confirmed or rejected).
     */
    public function isAwaitingVerification(): bool
    {
        return $this->status === self::STATUS_PROCESSING
            && $this->verification_status !== self::VERIFY_VERIFIED;
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'breakdown_json' => 'array',
            'payment_date' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}

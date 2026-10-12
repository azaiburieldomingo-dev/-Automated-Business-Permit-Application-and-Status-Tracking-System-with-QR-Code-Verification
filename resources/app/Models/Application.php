<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';

    public const STATUS_FOR_CORRECTION = 'FOR_CORRECTION';

    public const STATUS_APPROVED_FOR_PAYMENT = 'APPROVED_FOR_PAYMENT';

    public const STATUS_PAYMENT_PENDING = 'PAYMENT_PENDING';

    public const STATUS_PAYMENT_VERIFIED = 'PAYMENT_VERIFIED';

    public const STATUS_PERMIT_RELEASED = 'PERMIT_RELEASED';

    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'applicant_name',
        'contact_number',
        'email',
        'business_name',
        'business_address',
        'business_nature',
        'ownership_type',
        'dti_sec_type',
        'dti_sec_number',
        'barangay',
        'number_of_employees',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ApplicationRequirement::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class);
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(ApplicationRemark::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function permit(): HasOne
    {
        return $this->hasOne(Permit::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permit extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REVOKED = 'REVOKED';

    protected $fillable = [
        'application_id',
        'permit_number',
        'verification_token',
        'business_name',
        'owner_name',
        'business_address',
        'business_nature',
        'issued_at',
        'valid_until',
        'status',
        'pdf_path',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function qrVerifications(): HasMany
    {
        return $this->hasMany(QrVerification::class);
    }
}

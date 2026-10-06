<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationRequirement extends Model
{
    public const CHECK_COMPLETE = 'COMPLETE';

    public const CHECK_MISSING = 'MISSING';

    public const CHECK_FOR_CORRECTION = 'FOR_CORRECTION';

    protected $fillable = [
        'requirement_type_id',
        'original_filename',
        'stored_path',
        'mime',
        'size_kb',
        'checklist_status',
        'correction_note',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requirementType(): BelongsTo
    {
        return $this->belongsTo(RequirementType::class);
    }
}

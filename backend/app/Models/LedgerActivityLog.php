<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'subject_user_id',
        'squadron_id',
        'is_org_owned',
        'wipe_cycle_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_org_owned' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function squadron(): BelongsTo
    {
        return $this->belongsTo(Squadron::class);
    }

    public function wipeCycle(): BelongsTo
    {
        return $this->belongsTo(WipeCycle::class);
    }
}

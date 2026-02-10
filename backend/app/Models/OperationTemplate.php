<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationTemplate extends Model
{
    public const SCOPE_PERSONAL = 'personal';
    public const SCOPE_SQUADRON = 'squadron';
    public const SCOPE_GLOBAL = 'global';

    protected $fillable = [
        'name',
        'scope',
        'owner_user_id',
        'squadron_id',
        'created_by',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function squadron()
    {
        return $this->belongsTo(Squadron::class);
    }
}

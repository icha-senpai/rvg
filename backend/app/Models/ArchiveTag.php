<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArchiveTag extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(
            ArchiveEntry::class,
            'archive_entry_tag',
            'archive_tag_id',
            'archive_entry_id'
        )->withTimestamps();
    }
}

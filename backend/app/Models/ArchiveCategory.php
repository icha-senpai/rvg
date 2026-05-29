<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArchiveCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function topics(): HasMany
    {
        return $this->hasMany(ArchiveTopic::class)->orderBy('sort_order')->orderBy('title');
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(
            ArchiveEntry::class,
            'archive_category_entry',
            'archive_category_id',
            'archive_entry_id'
        )->withTimestamps();
    }

    public function directEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            ArchiveEntry::class,
            'archive_category_entry',
            'archive_category_id',
            'archive_entry_id'
        )
            ->whereNull('archive_entries.archive_topic_id')
            ->orderBy('archive_entries.sort_order')
            ->orderBy('archive_entries.title')
            ->withTimestamps();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArchiveEntry extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'archive_topic_id',
        'title',
        'slug',
        'excerpt',
        'body',
        'banner_image_path',
        'sort_order',
        'minimum_rank_level',
        'is_published',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'archive_topic_id' => 'integer',
            'sort_order' => 'integer',
            'minimum_rank_level' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ArchiveTopic::class, 'archive_topic_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ArchiveCategory::class,
            'archive_category_entry',
            'archive_entry_id',
            'archive_category_id'
        )->withTimestamps();
    }

    public function primaryCategory(): ?ArchiveCategory
    {
        if ($this->relationLoaded('categories')) {
            return $this->categories->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])->first();
        }

        return $this->categories()
            ->orderBy('archive_categories.sort_order')
            ->orderBy('archive_categories.name')
            ->first();
    }

    public function isDirectCategoryEntry(): bool
    {
        return $this->archive_topic_id === null;
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            ArchiveTag::class,
            'archive_entry_tag',
            'archive_entry_id',
            'archive_tag_id'
        )->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $inner) {
                $inner->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['director', 'tech_director'])) {
            return $query;
        }

        $rankLevel = (int) ($user->rank_level ?? 0);

        return $query->where(function (Builder $inner) use ($rankLevel) {
            $inner->whereNull('minimum_rank_level')
                ->orWhere('minimum_rank_level', '<=', $rankLevel);
        });
    }

    public function minimumRankLabel(): string
    {
        return match ((int) $this->minimum_rank_level) {
            1 => 'Member',
            2 => 'Lieutenant',
            3 => 'Commander',
            4 => 'Wing Commander',
            5 => 'Admiral',
            6 => 'Grand Admiral',
            default => 'All verified members',
        };
    }
}

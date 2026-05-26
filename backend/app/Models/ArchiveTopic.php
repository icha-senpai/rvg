<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'category_label',
        'card_image_path',
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
            'sort_order' => 'integer',
            'minimum_rank_level' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ArchiveEntry::class)->orderBy('sort_order')->orderBy('title');
    }

    public function publishedEntries(): HasMany
    {
        return $this->entries()->where('is_published', true);
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

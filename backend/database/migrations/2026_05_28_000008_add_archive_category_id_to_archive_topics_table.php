<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_topics', function (Blueprint $table) {
            $table->foreignId('archive_category_id')
                ->nullable()
                ->after('id')
                ->constrained('archive_categories')
                ->nullOnDelete();
        });

        $topics = DB::table('archive_topics')
            ->select(['id', 'category_label'])
            ->orderBy('id')
            ->get();

        $categoryIdsBySlug = DB::table('archive_categories')
            ->pluck('id', 'slug')
            ->all();

        $nextSortOrder = (int) (DB::table('archive_categories')->max('sort_order') ?? 0);
        $now = now();

        foreach ($topics as $topic) {
            $categoryId = null;
            $label = trim((string) ($topic->category_label ?? ''));

            if ($label !== '') {
                $slug = Str::slug($label);

                if ($slug !== '') {
                    $categoryId = $categoryIdsBySlug[$slug] ?? null;

                    if (! $categoryId) {
                        $nextSortOrder += 10;

                        $categoryId = DB::table('archive_categories')->insertGetId([
                            'name' => $label,
                            'slug' => $slug,
                            'description' => null,
                            'sort_order' => $nextSortOrder,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        $categoryIdsBySlug[$slug] = $categoryId;
                    }
                }
            }

            if (! $categoryId) {
                $categoryId = DB::table('archive_category_entry')
                    ->join('archive_entries', 'archive_entries.id', '=', 'archive_category_entry.archive_entry_id')
                    ->join('archive_categories', 'archive_categories.id', '=', 'archive_category_entry.archive_category_id')
                    ->where('archive_entries.archive_topic_id', $topic->id)
                    ->orderBy('archive_categories.sort_order')
                    ->orderBy('archive_categories.name')
                    ->value('archive_categories.id');
            }

            if ($categoryId) {
                DB::table('archive_topics')
                    ->where('id', $topic->id)
                    ->update(['archive_category_id' => $categoryId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('archive_topics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archive_category_id');
        });
    }
};

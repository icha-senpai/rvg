<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTable(topicIdNullable: true);

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE archive_entries DROP CONSTRAINT IF EXISTS archive_entries_archive_topic_id_foreign');
            DB::statement('ALTER TABLE archive_entries ALTER COLUMN archive_topic_id DROP NOT NULL');
            DB::statement('ALTER TABLE archive_entries ADD CONSTRAINT archive_entries_archive_topic_id_foreign FOREIGN KEY (archive_topic_id) REFERENCES archive_topics(id) ON DELETE CASCADE');

            return;
        }

        DB::statement('ALTER TABLE archive_entries DROP FOREIGN KEY archive_entries_archive_topic_id_foreign');
        DB::statement('ALTER TABLE archive_entries MODIFY archive_topic_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE archive_entries ADD CONSTRAINT archive_entries_archive_topic_id_foreign FOREIGN KEY (archive_topic_id) REFERENCES archive_topics(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DELETE FROM archive_entries WHERE archive_topic_id IS NULL');
            $this->rebuildSqliteTable(topicIdNullable: false);

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("DELETE FROM archive_entries WHERE archive_topic_id IS NULL");
            DB::statement('ALTER TABLE archive_entries DROP CONSTRAINT IF EXISTS archive_entries_archive_topic_id_foreign');
            DB::statement('ALTER TABLE archive_entries ALTER COLUMN archive_topic_id SET NOT NULL');
            DB::statement('ALTER TABLE archive_entries ADD CONSTRAINT archive_entries_archive_topic_id_foreign FOREIGN KEY (archive_topic_id) REFERENCES archive_topics(id) ON DELETE CASCADE');

            return;
        }

        DB::statement('DELETE FROM archive_entries WHERE archive_topic_id IS NULL');
        DB::statement('ALTER TABLE archive_entries DROP FOREIGN KEY archive_entries_archive_topic_id_foreign');
        DB::statement('ALTER TABLE archive_entries MODIFY archive_topic_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE archive_entries ADD CONSTRAINT archive_entries_archive_topic_id_foreign FOREIGN KEY (archive_topic_id) REFERENCES archive_topics(id) ON DELETE CASCADE');
    }

    protected function rebuildSqliteTable(bool $topicIdNullable): void
    {
        Schema::disableForeignKeyConstraints();

        DB::statement(sprintf(
            'CREATE TABLE archive_entries_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                archive_topic_id INTEGER %s,
                title VARCHAR NOT NULL,
                slug VARCHAR NOT NULL,
                excerpt TEXT NULL,
                body TEXT NULL,
                banner_image_path VARCHAR NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                minimum_rank_level INTEGER NULL,
                is_published TINYINT(1) NOT NULL DEFAULT 0,
                published_at DATETIME NULL,
                created_by INTEGER NULL,
                updated_by INTEGER NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                deleted_at DATETIME NULL,
                FOREIGN KEY (archive_topic_id) REFERENCES archive_topics (id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
            )',
            $topicIdNullable ? 'NULL' : 'NOT NULL'
        ));

        DB::statement('INSERT INTO archive_entries_new (id, archive_topic_id, title, slug, excerpt, body, banner_image_path, sort_order, minimum_rank_level, is_published, published_at, created_by, updated_by, created_at, updated_at, deleted_at)
            SELECT id, archive_topic_id, title, slug, excerpt, body, banner_image_path, sort_order, minimum_rank_level, is_published, published_at, created_by, updated_by, created_at, updated_at, deleted_at
            FROM archive_entries');

        DB::statement('DROP TABLE archive_entries');
        DB::statement('ALTER TABLE archive_entries_new RENAME TO archive_entries');
        DB::statement('CREATE UNIQUE INDEX archive_entries_archive_topic_id_slug_unique ON archive_entries (archive_topic_id, slug)');
        DB::statement('CREATE INDEX archive_entries_archive_topic_id_is_published_minimum_rank_level_sort_order_index ON archive_entries (archive_topic_id, is_published, minimum_rank_level, sort_order)');
        DB::statement('CREATE INDEX archive_entries_sort_order_index ON archive_entries (sort_order)');
        DB::statement('CREATE INDEX archive_entries_minimum_rank_level_index ON archive_entries (minimum_rank_level)');
        DB::statement('CREATE INDEX archive_entries_is_published_index ON archive_entries (is_published)');
        DB::statement('CREATE INDEX archive_entries_published_at_index ON archive_entries (published_at)');
        DB::statement('CREATE INDEX archive_entries_deleted_at_index ON archive_entries (deleted_at)');

        Schema::enableForeignKeyConstraints();
    }
};

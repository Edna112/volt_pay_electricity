<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow the same meter number for different users; one row per user+provider+meter.
     * Skips if the table was already created with the new unique (from updated create_meters migration).
     */
    public function up(): void
    {
        if (! $this->indexExists('meters', 'meters_provider_id_meter_number_unique')) {
            return;
        }

        // MySQL: cannot drop a unique that backs the provider_id foreign key; drop FK first.
        Schema::table('meters', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
        });

        Schema::table('meters', function (Blueprint $table) {
            $table->dropUnique(['provider_id', 'meter_number']);
        });

        if (! $this->indexExists('meters', 'meters_user_provider_meter_unique')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'provider_id', 'meter_number'],
                    'meters_user_provider_meter_unique'
                );
            });
        }

        if (! $this->indexExists('meters', 'meters_provider_id_index')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->index('provider_id', 'meters_provider_id_index');
            });
        }

        Schema::table('meters', function (Blueprint $table) {
            $table->foreign('provider_id')
                ->references('id')
                ->on('providers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! $this->indexExists('meters', 'meters_user_provider_meter_unique')) {
            return;
        }

        Schema::table('meters', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
        });

        if ($this->indexExists('meters', 'meters_provider_id_index')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->dropIndex('meters_provider_id_index');
            });
        }

        Schema::table('meters', function (Blueprint $table) {
            $table->dropUnique('meters_user_provider_meter_unique');
        });

        if (! $this->indexExists('meters', 'meters_provider_id_meter_number_unique')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->unique(['provider_id', 'meter_number']);
            });
        }

        if (! $this->foreignKeyExists('meters', 'meters_provider_id_foreign')) {
            Schema::table('meters', function (Blueprint $table) {
                $table->foreign('provider_id')
                    ->references('id')
                    ->on('providers')
                    ->cascadeOnDelete();
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $row = DB::selectOne(
            'SELECT 1 as ok FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [DB::getDatabaseName(), $table, $indexName]
        );

        return $row !== null;
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $row = DB::selectOne(
            'SELECT 1 as ok FROM information_schema.table_constraints
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = "FOREIGN KEY"
             LIMIT 1',
            [DB::getDatabaseName(), $table, $constraintName]
        );

        return $row !== null;
    }
};

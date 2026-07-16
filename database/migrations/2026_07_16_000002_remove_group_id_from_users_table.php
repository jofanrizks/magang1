<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'group_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'group_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('group_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('groups')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('group_user')) {
            return;
        }

        DB::table('group_user')
            ->select('user_id', DB::raw('MIN(group_id) as group_id'))
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('users')
                        ->where('id', $row->user_id)
                        ->update(['group_id' => $row->group_id]);
                }
            });
    }
};

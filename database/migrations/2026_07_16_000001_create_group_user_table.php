<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('group_id')
                ->constrained('groups')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'group_id']);
        });

        if (!Schema::hasColumn('users', 'group_id')) {
            return;
        }

        $now = now();

        DB::table('users')
            ->whereNotNull('group_id')
            ->orderBy('id')
            ->select(['id', 'group_id'])
            ->chunk(500, function ($users) use ($now) {
                $rows = $users->map(fn($user) => [
                    'user_id' => $user->id,
                    'group_id' => $user->group_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('group_user')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_user');
    }
};

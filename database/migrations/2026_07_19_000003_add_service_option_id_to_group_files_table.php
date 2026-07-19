<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_files', function (Blueprint $table) {
            $table->foreignId('service_option_id')
                ->nullable()
                ->after('group_id')
                ->constrained('service_options')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('group_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_option_id');
        });
    }
};

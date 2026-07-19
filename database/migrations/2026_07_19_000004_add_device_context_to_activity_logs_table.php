<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('browser')
                ->nullable()
                ->after('ip_address');

            $table->string('operating_system')
                ->nullable()
                ->after('browser');

            $table->string('device_type')
                ->nullable()
                ->after('operating_system');

            $table->text('user_agent')
                ->nullable()
                ->after('device_type');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn([
                'browser',
                'operating_system',
                'device_type',
                'user_agent',
            ]);
        });
    }
};

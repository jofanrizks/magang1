<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->enum('role', [
                'admin',
                'user'
            ])->default('user');
            $table->foreignId('group_id')
                ->nullable()
                ->constrained('groups')
                ->nullOnDelete();
            $table->string('nik')->unique();
            $table->string('nama');
            $table->string('instansi');
            $table->string('jabatan');
            $table->string('telp')->unique();
            $table->string('password');

            $table->enum('sts', [
                'pending',
                'aktif',
                'disabled'
            ])->default('pending');

            $table->enum('approval', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');
            
            $table->integer('login_attempt')->default(0);


            $table->timestamp('tgldaftar')->useCurrent();

            $table->timestamp('tglapproval')->nullable();

            $table->timestamp('tglupdate')->nullable();
            $table->timestamp('tgldisabled')->nullable();

            $table->rememberToken();
            $table->timestamps();
            });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
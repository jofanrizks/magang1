<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')
                ->constrained('services')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_options');
    }
};

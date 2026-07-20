<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('searches', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('objective')->nullable();
            $table->json('questions');
            $table->text('consent_term')->nullable();
            $table->text('instructions')->nullable();
            $table->enum('status', ['draft', 'published', 'closed', 'archived'])->default('draft');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->foreignId('author_id')->constrained('users');
            $table->foreignId('parent_search_id')->nullable()->constrained('searches')->onDelete('set null');
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date'], 'idx_searches_scheduling');
        });

        Schema::create('search_specialties', function (Blueprint $table) {
            $table->foreignId('search_id')->constrained('searches')->onDelete('cascade');
            $table->foreignId('specialty_id')->constrained('specialties')->onDelete('cascade');
            $table->primary(['search_id', 'specialty_id']);
        });

        Schema::create('search_managers', function (Blueprint $table) {
            $table->foreignId('search_id')->constrained('searches')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->primary(['search_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_managers');
        Schema::dropIfExists('search_specialties');
        Schema::dropIfExists('searches');
    }
};
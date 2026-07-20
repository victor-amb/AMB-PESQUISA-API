<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('search_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_id')->constrained('searches');
            $table->foreignId('responder_id')->constrained('responders');
            $table->enum('progress_status', ['in_progress', 'completed'])->default('in_progress');
            $table->json('answers');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['search_id', 'responder_id'], 'unique_search_responder');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_answers');
    }
};
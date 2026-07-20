<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('responders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->integer('crm')->nullable();
            $table->char('crm_state', 2)->nullable();
            $table->json('metadata')->nullable(); 
            $table->boolean('active')->default(true);
            $table->foreignId('inviter_id')->nullable()->constrained('users')->onDelete('set null');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('responder_specialties', function (Blueprint $table) {
            $table->foreignId('responder_id')->constrained('responders')->onDelete('cascade');
            $table->foreignId('specialty_id')->constrained('specialties')->onDelete('cascade');
            $table->primary(['responder_id', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responder_specialties');
        Schema::dropIfExists('responders');
    }
};
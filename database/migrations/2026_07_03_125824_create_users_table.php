<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['master', 'director']);
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
            $table->string('password');
            $table->foreignId('inviter_id')->nullable()->constrained('users')->onDelete('set null');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_specialties', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('specialty_id')->constrained('specialties')->onDelete('cascade');
            $table->primary(['user_id', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_specialties');
        Schema::dropIfExists('users');
    }
};
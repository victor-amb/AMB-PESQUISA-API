<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->enum('status', ['pending', 'registered', 'expired'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('search_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_id')->constrained('searches')->onDelete('cascade'); 
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('name')->nullable();
            $table->enum('status', ['pending_publish', 'sent', 'accepted', 'declined'])->default('pending_publish');
            $table->enum('delivery_status', ['standby', 'success_email', 'success_whatsapp', 'failed'])->default('standby');
            $table->foreignId('responder_id')->nullable()->constrained('responders')->onDelete('set null');
            $table->timestamps();

            $table->unique(['search_id', 'email']);
            $table->index('email', 'idx_invitations_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_invitations');
        Schema::dropIfExists('system_invitations');
    }
};
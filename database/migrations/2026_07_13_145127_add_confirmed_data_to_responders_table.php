<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responders', function (Blueprint $table) {
            // Adiciona o campo após a coluna active. False por padrão.
            $table->boolean('confirmed_data')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('responders', function (Blueprint $table) {
            $table->dropColumn('confirmed_data');
        });
    }
};
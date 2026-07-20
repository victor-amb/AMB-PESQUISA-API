<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Carga inicial de especialidades médicas (IDs sequenciais de 1 a 55)
        $this->call(SpecialtySeeder::class);

        // 2. Carga estruturada de usuários administrativos e médicos
        $this->call(UserSeeder::class);
    }
}
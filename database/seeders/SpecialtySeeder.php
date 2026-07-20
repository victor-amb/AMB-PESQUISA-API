<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpecialtySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Desativa as travas de chave estrangeira do MySQL
        Schema::disableForeignKeyConstraints();

        // Agora o MySQL permite limpar e resetar o AUTO_INCREMENT para 1
        DB::table('specialties')->truncate();

        // Reativa as travas de segurança
        Schema::enableForeignKeyConstraints();

        $specialties = [
            ["name" => "Pediatria", "image_path" => "/assets/images/logos_sociedades/PEDIATRIA.jpg"],
            ["name" => "Neurocirurgia", "image_path" => "/assets/images/logos_sociedades/NEUROCIRURGIA.jpg"],
            ["name" => "Psiquiatria", "image_path" => "/assets/images/logos_sociedades/LOGO_ABP_OFICIAL_EXCELENTE_RESOLUÇÃO.jpg"],
            ["name" => "Acupuntura", "image_path" => "/assets/images/logos_sociedades/Logo-CMBA-1.jpg"],
            ["name" => "Alergia", "image_path" => "/assets/images/logos_sociedades/logoASBAI.jpg"],
            ["name" => "Anestesiologia", "image_path" => "/assets/images/logos_sociedades/SBA.jpg"],
            ["name" => "Cabeça e Pescoço", "image_path" => "/assets/images/logos_sociedades/SBCCPVH_Colorida verde escuro.jpg"],
            ["name" => "Cirurgia Cardíaca", "image_path" => "/assets/images/logos_sociedades/cardiologia.jpg"],
            ["name" => "Cirurgia Digestiva", "image_path" => "/assets/images/logos_sociedades/LOGO CBCD.jpg"],
            ["name" => "Clínica Médica", "image_path" => "/assets/images/logos_sociedades/Clínica Médica 2025_page.jpg"],
            ["name" => "Cirurgia Geral", "image_path" => "/assets/images/logos_sociedades/cbc_logo.jpg"],
            ["name" => "Dermatologia", "image_path" => "/assets/images/logos_sociedades/LogoSBD.jpg"],
            ["name" => "Cardiologia", "image_path" => "/assets/images/logos_sociedades/cardiologia.jpg"],
            ["name" => "Oftalmologia", "image_path" => "/assets/images/logos_sociedades/Hematologia__Hemoterapia_e_Terapia_Celular.jpg"],
            ["name" => "Otorrinolaringologia", "image_path" => "/assets/images/logos_sociedades/ABORL.jpg"],
            ["name" => "Cirurgia Vascular", "image_path" => "/assets/images/logos_sociedades/sbacv_completo"],
            ["name" => "Ginecologia e Obstetrícia", "image_path" => "/assets/images/logos_sociedades/Ginecologia.png"],
            ["name" => "Cirurgia Digestiva", "image_path" => "/assets/images/logos_sociedades/LOGO CBCD.jpg"],
            ["name" => "Cirurgia Oncológica", "image_path" => "/assets/images/logos_sociedades/Cirurgia_Oncologica.png"],
            ["name" => "Gastroenterologia", "image_path" => "/assets/images/logos_sociedades/default.png"],
            ["name" => "Reumatologia", "image_path" => "/assets/images/logos_sociedades/Reumatologia.png"],
            ["name" => "Homeopatia", "image_path" => "/assets/images/logos_sociedades/Homeopatia.png"],
            ["name" => "Hematologia e Hemoterapia", "image_path" => "/assets/images/logos_sociedades/Hematologia_e_Hemoterapia.png"],
            ["name" => "Infectologia", "image_path" => "/assets/images/logos_sociedades/Infectologia.png"],
            ["name" => "Medicina do Tráfego", "image_path" => "/assets/images/logos_sociedades/Medicina_Preventiva.png"],
            ["name" => "Cirurgia da Mão", "image_path" => "/assets/images/logos_sociedades/Cirurgia_da_M__o.png"],
            ["name" => "Clínica Médica", "image_path" => "/assets/images/logos_sociedades/Cirurgia_Plastica.png"],
            ["name" => "Cirurgia Cardiovascular", "image_path" => "/assets/images/logos_sociedades/LOGO SBCCV.jpg"],
            ["name" => "Cirurgia do Trauma", "image_path" => "/assets/images/logos_sociedades/cbc_logo.jpg"],
            ["name" => "Cirurgia Pediátrica", "image_path" => "/assets/images/logos_sociedades/PEDIATRIA.jpg"],
            ["name" => "Cirurgia Plástica", "image_path" => "/assets/images/logos_sociedades/Sociedade Brasileira de Cirurgia Plástica.jpg"],
            ["name" => "Cirurgia Torácica", "image_path" => "/assets/images/logos_sociedades/SBCT.jpg"],
            ["name" => "Coloproctologia", "image_path" => "/assets/images/logos_sociedades/Sociedade Brasileira de Coloproctologia.jpg"],
            ["name" => "Endocrinologia e Metabologia", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Endoscopia e Endoscopia Digestiva", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Geriatria", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Mastologia", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Medicina de Emergência", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Medicina do Esporte", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Medicina do Trabalho", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Medicina de Tráfego", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Medicina Física e Reabilitação", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Medicina Intensiva", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Medicina Legal e Perícia Médica", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Medicina Nuclear", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Medicina Paliativa", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Medicina Preventiva e Social", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Nefrologia", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Neurologia", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Nutrologia", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Patologia Clínica e Medicina Laboratorial", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Pneumologia e Tisiologia", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Radiologia e Diagnóstico por Imagem", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Ortopedia e Traumatologia", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"],
            ["name" => "Urologia", "image_path" => "/assets/images/logos_sociedades/SABEM.jpg"]
        ];

        // Corrige caminhos que vieram sem barra inicial
        $dataToInsert = array_map(function ($specialty) {
            $imagePath = $specialty['image_path'];
            if (!str_starts_with($imagePath, '/')) {
                $imagePath = '/' . $imagePath;
            }

            return [
                'name' => $specialty['name'],
                'image_path' => $imagePath,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $specialties);

        DB::table('specialties')->insert($dataToInsert);
    }
}
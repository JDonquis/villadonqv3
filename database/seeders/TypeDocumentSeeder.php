<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use DB;

class TypeDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {   
         $types = [

            ['name' => 'Notas_certificadas', 'status' => 1, 'required' => 1],
            ['name' => 'Certificado_de_buena_conducta', 'status' => 1, 'required' => 1],
            ['name' => 'Partida_de_nacimiento', 'status' => 1, 'required' => 1],
            ['name' => 'Boleta', 'status' => 1, 'required' => 1],
            ['name' => 'Foto', 'status' => 1, 'required' => 1],            

         ];   

         DB::table('type_documents')->insert($types);
    }
}

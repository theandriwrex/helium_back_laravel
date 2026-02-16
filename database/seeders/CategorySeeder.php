<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Desarrollo Web',
            'Desarrollo Móvil',
            'Diseño Gráfico',
            'Marketing Digital',
            'Redacción y Traducción',
            'Edición de Video y Multimedia',
            'Soporte Técnico y TI',
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'name' => $category,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
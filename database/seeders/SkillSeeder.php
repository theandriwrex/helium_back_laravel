<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [

            // Desarrollo Web
            'Laravel','PHP','JavaScript','React','Vue.js','Node.js',
            'PostgreSQL','MySQL','HTML5','CSS3',

            // Desarrollo Móvil
            'Flutter','React Native','Kotlin','Swift','Android Studio',
            'Firebase','API REST','UI/UX móvil',
            'Publicación en Play Store','Publicación en App Store',

            // Diseño Gráfico
            'Photoshop','Illustrator','Figma','Diseño de Logos','Branding',
            'Diseño Publicitario','Edición de imágenes','Diseño UX/UI',
            'Canva Pro','Prototipado',

            // Marketing Digital
            'SEO','SEM','Google Ads','Facebook Ads','Email Marketing',
            'Copywriting','Analytics','Embudos de venta',
            'Automatización','Estrategia digital',

            // Redacción y Traducción
            'Redacción SEO','Copy publicitario','Traducción Inglés',
            'Traducción Español','Corrección de estilo','Artículos de blog',
            'Escritura técnica','Ghostwriting','Transcripción','Storytelling',

            // Edición de Video
            'Premiere Pro','After Effects','CapCut','Edición YouTube',
            'Motion Graphics','Animación 2D','Animación 3D',
            'Producción audiovisual','Colorización','Diseño Sonoro',

            // Soporte TI
            'Soporte remoto','Redes','Linux','Windows Server',
            'Ciberseguridad','Mantenimiento PC','Docker',
            'DevOps básico','AWS','Backup y recuperación'
        ];

        foreach ($skills as $skill) {
            DB::table('skills')->insert([
                'name' => $skill,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

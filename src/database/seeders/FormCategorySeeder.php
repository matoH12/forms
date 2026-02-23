<?php

namespace Database\Seeders;

use App\Models\FormCategory;
use Illuminate\Database\Seeder;

class FormCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Štúdium',
                'slug' => 'studium',
                'description' => 'Formuláre súvisiace so štúdiom, zápismi a skúškami',
                'color' => '#3B82F6', // blue
                'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                'order' => 1,
            ],
            [
                'name' => 'Ubytovanie',
                'slug' => 'ubytovanie',
                'description' => 'Žiadosti o ubytovanie na internátoch',
                'color' => '#10B981', // green
                'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                'order' => 2,
            ],
            [
                'name' => 'Štipendiá',
                'slug' => 'stipendia',
                'description' => 'Žiadosti o štipendiá a finančnú podporu',
                'color' => '#F59E0B', // amber
                'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                'order' => 3,
            ],
            [
                'name' => 'Prax a stáže',
                'slug' => 'prax-staze',
                'description' => 'Prihlášky na odbornú prax a stáže',
                'color' => '#8B5CF6', // violet
                'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                'order' => 4,
            ],
            [
                'name' => 'IT služby',
                'slug' => 'it-sluzby',
                'description' => 'Žiadosti o IT služby, prístupy a účty',
                'color' => '#06B6D4', // cyan
                'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                'order' => 5,
            ],
            [
                'name' => 'Potvrdenia',
                'slug' => 'potvrdenia',
                'description' => 'Žiadosti o vydanie potvrdení',
                'color' => '#EC4899', // pink
                'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                'order' => 6,
            ],
            [
                'name' => 'Udalosti',
                'slug' => 'udalosti',
                'description' => 'Registrácia na konferencie, workshopy a akcie',
                'color' => '#EF4444', // red
                'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                'order' => 7,
            ],
            [
                'name' => 'Ostatné',
                'slug' => 'ostatne',
                'description' => 'Iné formuláre a žiadosti',
                'color' => '#6B7280', // gray
                'icon' => 'M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z',
                'order' => 99,
            ],
        ];

        foreach ($categories as $category) {
            FormCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}

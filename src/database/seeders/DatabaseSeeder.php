<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Run email template seeder first
        $this->call(EmailTemplateSeeder::class);

        // Vytvorenie admin používateľa
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'keycloak_id' => 'admin-keycloak-id',
            'is_admin' => true,
        ]);

        // Vytvorenie ukážkového formulára
        Form::create([
            'name' => 'Kontaktný formulár',
            'slug' => 'kontakt',
            'description' => 'Kontaktujte nás cez tento formulár.',
            'is_public' => true,
            'is_active' => true,
            'schema' => [
                'fields' => [
                    [
                        'id' => 'field_1',
                        'type' => 'text',
                        'name' => 'meno',
                        'label' => 'Vaše meno',
                        'required' => true,
                    ],
                    [
                        'id' => 'field_2',
                        'type' => 'email',
                        'name' => 'email',
                        'label' => 'Email',
                        'required' => true,
                    ],
                    [
                        'id' => 'field_3',
                        'type' => 'textarea',
                        'name' => 'sprava',
                        'label' => 'Správa',
                        'required' => true,
                    ],
                ],
            ],
            'created_by' => 1,
        ]);

        // Žiadosť o prístup (privátny formulár)
        Form::create([
            'name' => 'Žiadosť o prístup',
            'slug' => 'ziadost-o-pristup',
            'description' => 'Žiadosť o prístup k systému.',
            'is_public' => false,
            'is_active' => true,
            'schema' => [
                'fields' => [
                    [
                        'id' => 'field_1',
                        'type' => 'text',
                        'name' => 'system',
                        'label' => 'Názov systému',
                        'required' => true,
                    ],
                    [
                        'id' => 'field_2',
                        'type' => 'select',
                        'name' => 'typ_pristupu',
                        'label' => 'Typ prístupu',
                        'required' => true,
                        'options' => [
                            ['label' => 'Čítanie', 'value' => 'read'],
                            ['label' => 'Zápis', 'value' => 'write'],
                            ['label' => 'Admin', 'value' => 'admin'],
                        ],
                    ],
                    [
                        'id' => 'field_3',
                        'type' => 'textarea',
                        'name' => 'dovod',
                        'label' => 'Dôvod žiadosti',
                        'required' => true,
                    ],
                    [
                        'id' => 'field_4',
                        'type' => 'email',
                        'name' => 'manager_email',
                        'label' => 'Email vedúceho (pre schválenie)',
                        'required' => true,
                    ],
                ],
            ],
            'created_by' => 1,
        ]);
    }
}

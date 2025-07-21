<?php
namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run()
    {
        $languages = [
            [
                'code' => 'uz',
                'name' => 'O\'zbek',
                'native_name' => 'O\'zbekcha',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1
            ],
            [
                'code' => 'ru',
                'name' => 'Русский',
                'native_name' => 'Русский',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'is_active' => false,
                'is_default' => false,
                'sort_order' => 3
            ]
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['code' => $language['code']],
                $language
            );
        }
    }
}
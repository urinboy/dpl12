<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use App\Models\City;
use Illuminate\Database\Seeder;

class AddressSeeder extends Seeder
{
    public function run()
    {
        // Test foydalanuvchini olish
        $user = User::where('email', 'customer@test.com')->first();
        
        if (!$user) {
            $this->command->warn('Test user not found. Please run ProductSeeder first.');
            return;
        }

        $addresses = [
            [
                'user_id' => $user->id,
                'title' => 'Uy',
                'address' => 'Chilonzor tumani, 12-kvartal, 25-uy',
                'city_id' => 1, // Toshkent
                'district' => 'Chilonzor',
                'landmark' => 'Mega Planet yonida',
                'phone' => '+998901234568',
                'latitude' => 41.311081,
                'longitude' => 69.240562,
                'is_default' => true
            ],
            [
                'user_id' => $user->id,
                'title' => 'Ish',
                'address' => 'Mirobod tumani, Amir Temur ko\'chasi, 108',
                'city_id' => 1, // Toshkent
                'district' => 'Mirobod',
                'landmark' => 'IT Park yaqinida',
                'phone' => '+998901234568',
                'latitude' => 41.311158,
                'longitude' => 69.279737,
                'is_default' => false
            ]
        ];

        foreach ($addresses as $addressData) {
            Address::create($addressData);
        }

        // $this->command->info('Test addresses created successfully!');
    }
}
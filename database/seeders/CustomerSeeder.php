<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            //return;
        }

        $customers = [
            ['name' => 'Casey Customer', 'email' => 'casey@example.com', 'phone' => '+1 555 0100', 'address' => '12 Market Street'],
            ['name' => 'Jordan Shopper', 'email' => 'jordan@example.com', 'phone' => '+1 555 0111', 'address' => '8 Orchard Lane'],
            ['name' => 'Riley Buyer', 'email' => 'riley@example.com', 'phone' => null, 'address' => null],
        ];

        foreach ($customers as $attributes) {
            $customer = User::query()->firstOrNew(['email' => $attributes['email']]);
            $customer->name = $attributes['name'];
            $customer->phone = $attributes['phone'];
            $customer->address = $attributes['address'];
            $customer->password = 'password';
            $customer->forceFill(['is_admin' => false])->save();
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\FreelancerProfile;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'name');
        $categories = DB::table('categories')->orderBy('id')->get(['id', 'name']);

        $clienteRoleId = (int) ($roleIds['cliente'] ?? 1);
        $freelancerRoleId = (int) ($roleIds['freelancer'] ?? 2);
        $empresaRoleId = (int) ($roleIds['empresa'] ?? 3);
        $adminRoleId = (int) ($roleIds['admin'] ?? 4);

        User::create([
            'names' => 'Admin',
            'last_names' => 'Helium',
            'email' => 'admin@helium.test',
            'phone' => '3000000000',
            'password' => Hash::make('password123'),
            'role_id' => $adminRoleId,
        ]);

        $clientes = collect([
            ['Laura', 'Martinez', 'laura.cliente@helium.test', '3001000001'],
            ['Daniel', 'Rojas', 'daniel.cliente@helium.test', '3001000002'],
            ['Sofia', 'Gomez', 'sofia.cliente@helium.test', '3001000003'],
            ['Andres', 'Mejia', 'andres.cliente@helium.test', '3001000004'],
        ])->map(function ($data) use ($clienteRoleId) {
            return User::create([
                'names' => $data[0],
                'last_names' => $data[1],
                'email' => $data[2],
                'phone' => $data[3],
                'password' => Hash::make('password123'),
                'role_id' => $clienteRoleId,
            ]);
        });

        $empresasUsers = collect([
            ['Nexora', 'SAS', 'nexora@helium.test', '900111111-1', 'Calle 100 #10-20', 'https://nexora.test'],
            ['ByteWorks', 'SAS', 'byteworks@helium.test', '900222222-2', 'Carrera 15 #30-40', 'https://byteworks.test'],
        ])->map(function ($data) use ($empresaRoleId) {
            $user = User::create([
                'names' => $data[0],
                'last_names' => $data[1],
                'email' => $data[2],
                'phone' => '601555' . rand(1000, 9999),
                'password' => Hash::make('password123'),
                'role_id' => $empresaRoleId,
            ]);

            Company::create([
                'user_id' => $user->id,
                'nit' => $data[3],
                'address' => $data[4],
                'website' => $data[5],
            ]);

            return $user;
        });

        $freelancerUsers = collect([
            ['Valentina', 'Ruiz', 'valentina.freelancer@helium.test', 'Desarrolladora Backend Laravel', 'Ingeniera de Software'],
            ['Camilo', 'Torres', 'camilo.freelancer@helium.test', 'Disenador UX/UI', 'Disenador Multimedia'],
            ['Manuela', 'Castro', 'manuela.freelancer@helium.test', 'Especialista Marketing Digital', 'Profesional en Mercadeo'],
            ['Sebastian', 'Diaz', 'sebastian.freelancer@helium.test', 'Editor de Video y Multimedia', 'Comunicador Audiovisual'],
        ])->map(function ($data) use ($freelancerRoleId) {
            $user = User::create([
                'names' => $data[0],
                'last_names' => $data[1],
                'email' => $data[2],
                'phone' => '300200' . rand(1000, 9999),
                'password' => Hash::make('password123'),
                'role_id' => $freelancerRoleId,
            ]);

            $profile = FreelancerProfile::create([
                'user_id' => $user->id,
                'description' => 'Perfil demo para pruebas de frontend',
                'experience' => 'Mas de 3 anos de experiencia',
                'profession' => $data[3],
                'education_level' => $data[4],
                'strikes' => 0,
            ]);

            return ['user' => $user, 'profile' => $profile];
        });

        foreach ($freelancerUsers as $freelancer) {
            $categoryIds = $categories->pluck('id')->shuffle()->take(2)->values();
            $skillIds = DB::table('skills')
                ->whereIn('category_id', $categoryIds)
                ->inRandomOrder()
                ->limit(6)
                ->pluck('id');

            $freelancer['profile']->skills()->sync($skillIds);
        }

        $services = collect();
        $serviceCounter = 0;

        foreach ($categories as $category) {
            for ($variant = 1; $variant <= 2; $variant++) {
                // Reparto solicitado: 3, 3, 4, 4 servicios entre 4 freelancers.
                if ($serviceCounter < 3) {
                    $freelancerIndex = 0;
                } elseif ($serviceCounter < 6) {
                    $freelancerIndex = 1;
                } elseif ($serviceCounter < 10) {
                    $freelancerIndex = 2;
                } else {
                    $freelancerIndex = 3;
                }

                $profile = $freelancerUsers[$freelancerIndex]['profile'];
                $price = 350000 + ($serviceCounter * 45000);
                $deliveryTime = 4 + (($serviceCounter % 6) * 2);
                $revisions = ($serviceCounter % 3) + 1;

                $services->push(Service::create([
                    'freelancer_id' => $profile->id,
                    'category_id' => $category->id,
                    'title' => $category->name . ' - Servicio ' . $variant,
                    'description' => 'Servicio demo de ' . $category->name . ' para pruebas de frontend.',
                    'price' => $price,
                    'delivery_time' => $deliveryTime,
                    'revisions' => $revisions,
                    'requirements' => 'Compartir alcance, referencias y entregables esperados.',
                    'is_active' => true,
                ]));

                $serviceCounter++;
            }
        }

        $buyers = $clientes->concat($empresasUsers)->values();

        $ordersData = [
            ['service_index' => 0, 'buyer_index' => 0, 'status' => Order::STATUS_PENDING],
            ['service_index' => 1, 'buyer_index' => 1, 'status' => Order::STATUS_IN_PROGRESS],
            ['service_index' => 2, 'buyer_index' => 2, 'status' => Order::STATUS_DELIVERED],
            ['service_index' => 3, 'buyer_index' => 3, 'status' => Order::STATUS_COMPLETED],
            ['service_index' => 4, 'buyer_index' => 4, 'status' => Order::STATUS_CANCELLED],
            ['service_index' => 5, 'buyer_index' => 5, 'status' => Order::STATUS_COMPLETED],
            ['service_index' => 6, 'buyer_index' => 0, 'status' => Order::STATUS_IN_PROGRESS],
            ['service_index' => 7, 'buyer_index' => 1, 'status' => Order::STATUS_PENDING],
        ];

        foreach ($ordersData as $index => $orderData) {
            $service = $services[$orderData['service_index']];
            $buyer = $buyers[$orderData['buyer_index']];
            $status = $orderData['status'];

            Order::create([
                'user_id' => $buyer->id,
                'service_id' => $service->id,
                'amount' => $service->price,
                'pse_reference' => 'PSE-DEMO-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'status' => $status,
                'requirements' => 'Requerimientos demo para pruebas de frontend',
                'started_at' => in_array($status, [Order::STATUS_IN_PROGRESS, Order::STATUS_DELIVERED, Order::STATUS_COMPLETED], true) ? now()->subDays(3) : null,
                'delivered_at' => in_array($status, [Order::STATUS_DELIVERED, Order::STATUS_COMPLETED], true) ? now()->subDay() : null,
                'completed_at' => $status === Order::STATUS_COMPLETED ? now() : null,
                'cancelled_at' => $status === Order::STATUS_CANCELLED ? now() : null,
            ]);
        }
    }
}

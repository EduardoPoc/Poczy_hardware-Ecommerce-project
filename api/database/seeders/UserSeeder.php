<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::USERS as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);
            if (! $user->exists) {
                $user->name = $data['name'];
                $user->password = $data['role'] === 'ADMIN' ? 'PoczyAdmin!2026' : 'PoczyDemo!2026';
                $user->role = $data['role'];
                $user->email_verified_at = now();
                $user->save();
            }

            foreach ($data['addresses'] as $address) {
                $user->addresses()->firstOrCreate([
                    'postal_code' => $address['postal_code'],
                    'street' => $address['street'],
                    'number' => $address['number'],
                ], [
                    'complement' => $address['complement'],
                    'city' => $address['city'],
                    'state' => $address['state'],
                ]);
            }
        }
    }

    /** @var list<array{name: string, email: string, role: string, addresses: list<array{postal_code: string, street: string, number: string, complement: ?string, city: string, state: string}>}> */
    private const USERS = [
        [
            'name' => 'Eduardo Almeida',
            'email' => 'admin@example.com',
            'role' => 'ADMIN',
            'addresses' => [],
        ],
        [
            'name' => 'Mariana Costa',
            'email' => 'mariana.costa@example.com',
            'role' => 'CUSTOMER',
            'addresses' => [
                ['postal_code' => '80010-000', 'street' => 'Rua Jardim das Araucárias', 'number' => '245', 'complement' => 'Apartamento 302', 'city' => 'Curitiba', 'state' => 'PR'],
                ['postal_code' => '80020-000', 'street' => 'Avenida Horizonte Verde', 'number' => '1180', 'complement' => 'Sala 12', 'city' => 'Curitiba', 'state' => 'PR'],
            ],
        ],
        [
            'name' => 'Lucas Ferreira',
            'email' => 'lucas.ferreira@example.com',
            'role' => 'CUSTOMER',
            'addresses' => [
                ['postal_code' => '01310-000', 'street' => 'Alameda dos Ipês', 'number' => '78', 'complement' => 'Casa 2', 'city' => 'São Paulo', 'state' => 'SP'],
            ],
        ],
        [
            'name' => 'Beatriz Oliveira',
            'email' => 'beatriz.oliveira@example.com',
            'role' => 'CUSTOMER',
            'addresses' => [
                ['postal_code' => '30130-000', 'street' => 'Rua Vila das Acácias', 'number' => '630', 'complement' => null, 'city' => 'Belo Horizonte', 'state' => 'MG'],
            ],
        ],
        [
            'name' => 'Rafael Martins',
            'email' => 'rafael.martins@example.com',
            'role' => 'CUSTOMER',
            'addresses' => [
                ['postal_code' => '90450-000', 'street' => 'Rua Recanto dos Plátanos', 'number' => '154', 'complement' => 'Apartamento 501', 'city' => 'Porto Alegre', 'state' => 'RS'],
            ],
        ],
        [
            'name' => 'Ana Luiza Santos',
            'email' => 'ana.santos@example.com',
            'role' => 'CUSTOMER',
            'addresses' => [
                ['postal_code' => '88015-000', 'street' => 'Rua Brisa da Ilha', 'number' => '920', 'complement' => 'Bloco B, apartamento 104', 'city' => 'Florianópolis', 'state' => 'SC'],
            ],
        ],
    ];
}

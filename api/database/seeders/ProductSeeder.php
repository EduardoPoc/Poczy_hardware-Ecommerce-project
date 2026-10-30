<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(CatalogSeeder::class);

            foreach (self::PRODUCTS as $data) {
                $category = Category::where('name', $data['category'])->sole();
                $brand = Brand::firstOrCreate(['name' => $data['brand']]);
                Product::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $data['name']],
                    [
                        'brand_id' => $brand->id,
                        'description' => $data['description'],
                        'price' => $data['price'],
                        'cost' => $data['cost'],
                        'stock' => $data['stock'],
                        'specifications' => $data['specifications'],
                    ],
                );
            }
        });
    }

    /**
     * Configurações ilustrativas; preços, custos e estoques não são cotações reais.
     *
     * @var list<array{category: string, brand: string, name: string, description: string, price: string, cost: string, stock: int, specifications: array<string, mixed>}>
     */
    private const PRODUCTS = [
        [
            'category' => 'Notebooks',
            'brand' => 'Lenovo',
            'name' => 'Notebook Lenovo 15,6 IPS 16 GB SSD 512 GB',
            'description' => 'Notebook para estudo e trabalho, com vídeo integrado e tela IPS.',
            'price' => '3299.90',
            'cost' => '2700.00',
            'stock' => 8,
            'specifications' => [
                'storage_capacity_gb' => 512,
                'has_dedicated_gpu' => false,
                'ram_capacity_gb' => 16,
                'screen_size_inches' => 15.6,
                'screen_type' => 'IPS',
            ],
        ],
        [
            'category' => 'Notebooks',
            'brand' => 'Acer',
            'name' => 'Notebook Acer Gamer 15,6 IPS 16 GB SSD 1 TB',
            'description' => 'Configuração de referência para jogos, com GPU dedicada e SSD NVMe.',
            'price' => '5299.90',
            'cost' => '4400.00',
            'stock' => 5,
            'specifications' => [
                'storage_capacity_gb' => 1000,
                'has_dedicated_gpu' => true,
                'ram_capacity_gb' => 16,
                'screen_size_inches' => 15.6,
                'screen_type' => 'IPS',
            ],
        ],
        [
            'category' => 'PCs montados',
            'brand' => 'Poczy',
            'name' => 'PC Poczy Essencial 16 GB SSD 512 GB',
            'description' => 'Montagem de referência para escritório e estudos, com processador Ryzen 5 e vídeo integrado.',
            'price' => '2499.90',
            'cost' => '2050.00',
            'stock' => 6,
            'specifications' => [
                'storage_capacity_gb' => 512,
                'has_dedicated_gpu' => false,
                'ram_capacity_gb' => 16,
            ],
        ],
        [
            'category' => 'PCs montados',
            'brand' => 'Poczy',
            'name' => 'PC Poczy Gamer 32 GB SSD 1 TB',
            'description' => 'Montagem de referência com Ryzen 5, GPU dedicada, 32 GB de RAM e SSD NVMe.',
            'price' => '4899.90',
            'cost' => '4150.00',
            'stock' => 4,
            'specifications' => [
                'storage_capacity_gb' => 1000,
                'has_dedicated_gpu' => true,
                'ram_capacity_gb' => 32,
            ],
        ],
        [
            'category' => 'Monitores',
            'brand' => 'LG',
            'name' => 'Monitor LG 23,8 IPS Full HD',
            'description' => 'Monitor de referência para uso diário, com painel IPS e resolução Full HD.',
            'price' => '749.90',
            'cost' => '610.00',
            'stock' => 15,
            'specifications' => [
                'screen_size_inches' => 23.8,
                'screen_type' => 'IPS',
            ],
        ],
        [
            'category' => 'Monitores',
            'brand' => 'Samsung',
            'name' => 'Monitor Samsung 27 OLED QHD',
            'description' => 'Monitor de referência para jogos, com painel OLED e resolução QHD.',
            'price' => '3499.90',
            'cost' => '2900.00',
            'stock' => 5,
            'specifications' => [
                'screen_size_inches' => 27,
                'screen_type' => 'OLED',
            ],
        ],
        [
            'category' => 'Periféricos',
            'brand' => 'Logitech',
            'name' => 'Mouse Logitech USB',
            'description' => 'Mouse óptico com conexão USB para uso diário.',
            'price' => '119.90',
            'cost' => '78.00',
            'stock' => 35,
            'specifications' => [
                'type' => 'Mouse',
            ],
        ],
        [
            'category' => 'Periféricos',
            'brand' => 'Redragon',
            'name' => 'Teclado Redragon Mecânico ABNT2',
            'description' => 'Teclado mecânico com conexão USB e layout brasileiro ABNT2.',
            'price' => '249.90',
            'cost' => '175.00',
            'stock' => 20,
            'specifications' => [
                'type' => 'Teclado',
            ],
        ],
        [
            'category' => 'Processadores',
            'brand' => 'AMD',
            'name' => 'Processador AMD Ryzen 5 AM4',
            'description' => 'Processador de referência para plataforma AMD AM4.',
            'price' => '799.90',
            'cost' => '650.00',
            'stock' => 18,
            'specifications' => [
                'socket' => 'AM4',
            ],
        ],
        [
            'category' => 'Processadores',
            'brand' => 'Intel',
            'name' => 'Processador Intel Core i5 LGA 1700',
            'description' => 'Processador de referência para plataforma Intel LGA 1700.',
            'price' => '999.90',
            'cost' => '820.00',
            'stock' => 12,
            'specifications' => [
                'socket' => 'LGA 1700',
            ],
        ],
        [
            'category' => 'Memórias RAM',
            'brand' => 'Kingston',
            'name' => 'Memória Kingston 16 GB DDR4 3200 MHz',
            'description' => 'Módulo de memória para desktop, capacidade de 16 GB, padrão DDR4.',
            'price' => '249.90',
            'cost' => '185.00',
            'stock' => 30,
            'specifications' => [
                'capacity_gb' => 16,
                'ddr' => 'DDR4',
                'speed_mhz' => 3200,
            ],
        ],
        [
            'category' => 'Memórias RAM',
            'brand' => 'Corsair',
            'name' => 'Memória Corsair 32 GB DDR5 5600 MHz',
            'description' => 'Módulo de memória para desktop, capacidade de 32 GB, padrão DDR5.',
            'price' => '599.90',
            'cost' => '470.00',
            'stock' => 16,
            'specifications' => [
                'capacity_gb' => 32,
                'ddr' => 'DDR5',
                'speed_mhz' => 5600,
            ],
        ],
        [
            'category' => 'Placas de vídeo',
            'brand' => 'ASUS',
            'name' => 'Placa de vídeo ASUS GeForce 8 GB',
            'description' => 'Placa de vídeo de referência para jogos, com 8 GB de memória dedicada.',
            'price' => '1999.90',
            'cost' => '1690.00',
            'stock' => 9,
            'specifications' => [
                'video_memory_gb' => 8,
            ],
        ],
        [
            'category' => 'Placas de vídeo',
            'brand' => 'Sapphire',
            'name' => 'Placa de vídeo Sapphire Radeon 12 GB',
            'description' => 'Placa de vídeo de referência para jogos, com 12 GB de memória dedicada.',
            'price' => '2799.90',
            'cost' => '2350.00',
            'stock' => 7,
            'specifications' => [
                'video_memory_gb' => 12,
            ],
        ],
        [
            'category' => 'Placas-mãe',
            'brand' => 'ASUS',
            'name' => 'Placa-mãe ASUS AM4 Micro ATX DDR4',
            'description' => 'Placa-mãe de referência para processadores AM4 e memória DDR4.',
            'price' => '699.90',
            'cost' => '550.00',
            'stock' => 14,
            'specifications' => [
                'ddr' => 'DDR4',
                'form_factor' => 'Micro ATX',
                'socket' => 'AM4',
            ],
        ],
        [
            'category' => 'Placas-mãe',
            'brand' => 'MSI',
            'name' => 'Placa-mãe MSI AM5 ATX DDR5',
            'description' => 'Placa-mãe de referência para processadores AM5 e memória DDR5.',
            'price' => '1299.90',
            'cost' => '1050.00',
            'stock' => 8,
            'specifications' => [
                'ddr' => 'DDR5',
                'form_factor' => 'ATX',
                'socket' => 'AM5',
            ],
        ],
        [
            'category' => 'Armazenamento',
            'brand' => 'Kingston',
            'name' => 'SSD Kingston NVMe 1 TB',
            'description' => 'SSD de referência com interface NVMe; velocidades sequenciais ilustrativas.',
            'price' => '449.90',
            'cost' => '345.00',
            'stock' => 25,
            'specifications' => [
                'capacity_gb' => 1000,
                'write_speed_mbps' => 2100,
                'read_speed_mbps' => 3500,
                'type' => 'SSD NVMe',
            ],
        ],
        [
            'category' => 'Armazenamento',
            'brand' => 'Seagate',
            'name' => 'HD Seagate SATA 2 TB',
            'description' => 'Disco rígido de referência com interface SATA; velocidades sequenciais ilustrativas.',
            'price' => '399.90',
            'cost' => '310.00',
            'stock' => 12,
            'specifications' => [
                'capacity_gb' => 2000,
                'write_speed_mbps' => 200,
                'read_speed_mbps' => 220,
                'type' => 'HD SATA',
            ],
        ],
        [
            'category' => 'Fontes',
            'brand' => 'Corsair',
            'name' => 'Fonte Corsair 650 W 80 Plus Bronze',
            'description' => 'Fonte de referência de 650 W, com cabeamento fixo.',
            'price' => '449.90',
            'cost' => '350.00',
            'stock' => 15,
            'specifications' => [
                'modularity' => 'Não modular',
                'certification_80_plus' => 'Bronze',
            ],
        ],
        [
            'category' => 'Fontes',
            'brand' => 'Cooler Master',
            'name' => 'Fonte Cooler Master 750 W 80 Plus Gold',
            'description' => 'Fonte de referência de 750 W, com cabeamento totalmente modular.',
            'price' => '699.90',
            'cost' => '555.00',
            'stock' => 10,
            'specifications' => [
                'modularity' => 'Modular',
                'certification_80_plus' => 'Gold',
            ],
        ],
        [
            'category' => 'Gabinetes',
            'brand' => 'Cooler Master',
            'name' => 'Gabinete Cooler Master Micro ATX',
            'description' => 'Gabinete de referência compacto, compatível com placas-mãe Micro ATX.',
            'price' => '279.90',
            'cost' => '205.00',
            'stock' => 11,
            'specifications' => [
                'motherboard_form_factor' => 'Micro ATX',
            ],
        ],
        [
            'category' => 'Gabinetes',
            'brand' => 'NZXT',
            'name' => 'Gabinete NZXT ATX',
            'description' => 'Gabinete de referência para montagem de desktops com placa-mãe ATX.',
            'price' => '549.90',
            'cost' => '425.00',
            'stock' => 7,
            'specifications' => [
                'motherboard_form_factor' => 'ATX',
            ],
        ],
        [
            'category' => 'Refrigeração',
            'brand' => 'DeepCool',
            'name' => 'Air cooler DeepCool para desktop',
            'description' => 'Cooler de referência para processador, com dissipador e ventoinha.',
            'price' => '169.90',
            'cost' => '115.00',
            'stock' => 20,
            'specifications' => [
                'type' => 'Air cooler',
            ],
        ],
        [
            'category' => 'Refrigeração',
            'brand' => 'Corsair',
            'name' => 'Water cooler Corsair 240 mm',
            'description' => 'Sistema de refrigeração líquida de referência com radiador de 240 mm.',
            'price' => '549.90',
            'cost' => '420.00',
            'stock' => 9,
            'specifications' => [
                'type' => 'Water cooler',
            ],
        ],
    ];
}

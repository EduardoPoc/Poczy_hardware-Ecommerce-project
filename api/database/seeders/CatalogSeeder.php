<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'notebooks' => 'Notebooks',
            'pcs' => 'PCs montados',
            'monitors' => 'Monitores',
            'peripherals' => 'Periféricos',
            'processors' => 'Processadores',
            'memory' => 'Memórias RAM',
            'graphics' => 'Placas de vídeo',
            'motherboards' => 'Placas-mãe',
            'storage' => 'Armazenamento',
            'power-supplies' => 'Fontes',
            'cases' => 'Gabinetes',
            'cooling' => 'Refrigeração',
        ];

        $definitions = [
            'notebooks' => [
                ['storage_capacity_gb', 'Armazenamento', 'integer', 'GB'],
                ['has_dedicated_gpu', 'GPU dedicada', 'boolean', null],
                ['ram_capacity_gb', 'RAM', 'integer', 'GB'],
                ['screen_size_inches', 'Tamanho da tela', 'number', 'pol'],
                ['screen_type', 'Tipo de tela', 'text', null],
            ],
            'pcs' => [
                ['storage_capacity_gb', 'Armazenamento', 'integer', 'GB'],
                ['has_dedicated_gpu', 'GPU dedicada', 'boolean', null],
                ['ram_capacity_gb', 'RAM', 'integer', 'GB'],
            ],
            'monitors' => [
                ['screen_size_inches', 'Tamanho', 'number', 'pol'],
                ['screen_type', 'Tipo de tela', 'text', null],
            ],
            'peripherals' => [['type', 'Tipo', 'text', null]],
            'processors' => [['socket', 'Socket', 'text', null]],
            'memory' => [
                ['capacity_gb', 'Capacidade', 'integer', 'GB'],
                ['ddr', 'DDR', 'text', null],
                ['speed_mhz', 'Velocidade', 'integer', 'MHz'],
            ],
            'graphics' => [['video_memory_gb', 'Memória de vídeo', 'integer', 'GB']],
            'motherboards' => [
                ['ddr', 'DDR', 'text', null],
                ['form_factor', 'Formato', 'text', null],
                ['socket', 'Socket', 'text', null],
            ],
            'storage' => [
                ['capacity_gb', 'Capacidade', 'integer', 'GB'],
                ['write_speed_mbps', 'Gravação', 'integer', 'MB/s'],
                ['read_speed_mbps', 'Leitura', 'integer', 'MB/s'],
                ['type', 'Tipo', 'text', null],
            ],
            'power-supplies' => [
                ['modularity', 'Cabeamento', 'text', null],
                ['certification_80_plus', 'Certificação 80 Plus', 'text', null],
            ],
            'cases' => [['motherboard_form_factor', 'Formato da placa-mãe', 'text', null]],
            'cooling' => [['type', 'Tipo', 'text', null]],
        ];

        $options = [
            'screen_type' => ['IPS', 'OLED', 'VA', 'TN'],
            'socket' => ['AM4', 'AM5', 'LGA 1700'],
            'ddr' => ['DDR4', 'DDR5'],
            'form_factor' => ['ATX', 'Micro ATX', 'Mini ITX'],
            'motherboard_form_factor' => ['ATX', 'Micro ATX', 'Mini ITX'],
            'modularity' => ['Não modular', 'Semi-modular', 'Modular'],
            'certification_80_plus' => ['Bronze', 'Silver', 'Gold', 'Platinum', 'Titanium'],
        ];
        $types = [
            'peripherals' => ['Mouse', 'Teclado', 'Headset', 'Controle'],
            'storage' => ['SSD NVMe', 'SSD SATA', 'HD SATA'],
            'cooling' => ['Air cooler', 'Water cooler', 'Ventoinha'],
        ];

        foreach ($categories as $code => $name) {
            $attributes = ['brand' => [
                'name' => 'Marca',
                'type' => 'text',
                'is_filterable' => true,
            ]];

            foreach ($definitions[$code] ?? [] as [$key, $label, $type, $unit]) {
                $attributes[$key] = [
                    'name' => $label,
                    'type' => $type,
                    'unit' => $unit,
                    'required' => false,
                    'is_filterable' => true,
                ];
                if (isset($options[$key])) {
                    $attributes[$key]['options'] = $options[$key];
                } elseif ($key === 'type') {
                    $attributes[$key]['options'] = $types[$code];
                }
                if (in_array($type, ['integer', 'number'], true)) {
                    $attributes[$key]['exclusive_minimum'] = 0;
                }
            }

            Category::firstOrCreate(['name' => $name], ['attributes' => $attributes]);
        }
    }
}

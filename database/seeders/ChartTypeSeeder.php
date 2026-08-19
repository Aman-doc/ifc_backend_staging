<?php

namespace Database\Seeders; // Yahan backslash (\) lagayein

use Illuminate\Database\Seeder;
use App\Models\ChartType;

class ChartTypeSeeder extends Seeder
{
    public function run(): void
    {
        $chartTypes = [
            'line-chart'             => 'Line',
            'area-chart'             => 'Area',
            'bar-chart'              => 'Bar',
            'butterfly-chart'        => 'Butterfly',
            'pie-chart'              => 'Pie',
            'slope-chart'            => 'Slope',
            'cleveland-dot-plot'     => 'Cleveland dot plot',
            'heatmap-chart'          => 'Heatmap',
            'lollipop-chart'         => 'Lollipop',
            'stacked-area-chart'     => 'Stacked Area',
            'treemap-chart'          => 'Treemap',
            'doughnut-chart'         => 'Doughnut',
            'circular-packing-chart' => 'Circular Packing',
        ];

        foreach ($chartTypes as $slug => $name) {
            ChartType::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'fields_definition' => [],
                    'is_active' => true,
                ]
            );
        }
    }
}
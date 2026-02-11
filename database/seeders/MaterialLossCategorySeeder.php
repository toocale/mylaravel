<?php

namespace Database\Seeders;

use App\Models\MaterialLossCategory;
use Illuminate\Database\Seeder;

class MaterialLossCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // PACKAGING LOSSES (already in finished units)
            [
                'name' => 'Packaging Waste',
                'code' => 'PKG',
                'description' => 'Defective packaging materials, overruns, or damaged packaging',
                'loss_type' => 'packaging',
                'affects_oee' => false,
                'requires_reason' => false,
                'color' => '#f59e0b', // amber
                'active' => true,
            ],
            [
                'name' => 'Startup Scrap',
                'code' => 'START',
                'description' => 'Products rejected during machine startup and warmup',
                'loss_type' => 'packaging',  // Finished products lost
                'affects_oee' => true,
                'requires_reason' => false,
                'color' => '#f97316', // orange
                'active' => true,
            ],
            
            // RAW MATERIAL LOSSES (need conversion)
            [
                'name' => 'Spillage',
                'code' => 'SPILL',
                'description' => 'Material spillage during transfer, filling, or processing',
                'loss_type' => 'raw_material',
                'affects_oee' => true,
                'requires_reason' => true,
                'color' => '#ef4444', // red
                'active' => true,
            ],
            [
                'name' => 'Setup Waste',
                'code' => 'SETUP',
                'description' => 'Material lost during machine setup and calibration',
                'loss_type' => 'raw_material',
                'affects_oee' => true,
                'requires_reason' => false,
                'color' => '#eab308', // yellow
                'active' => true,
            ],
            [
                'name' => 'Trim Waste',
                'code' => 'TRIM',
                'description' => 'Edge trim, cut-offs, and normal production waste',
                'loss_type' => 'raw_material',
                'affects_oee' => false,
                'requires_reason' => false,
                'color' => '#84cc16', // lime
                'active' => true,
            ],
            [
                'name' => 'Changeover Loss',
                'code' => 'CHNG',
                'description' => 'Material lost during product or format changeovers',
                'loss_type' => 'raw_material',
                'affects_oee' => true,
                'requires_reason' => false,
                'color' => '#06b6d4', // cyan
                'active' => true,
            ],
            [
                'name' => 'Material Damage',
                'code' => 'DMG',
                'description' => 'Raw materials damaged during handling or storage',
                'loss_type' => 'raw_material',
                'affects_oee' => true,
                'requires_reason' => true,
                'color' => '#dc2626', // red-600
                'active' => true,
            ],
            [
                'name' => 'Leakage',
                'code' => 'LEAK',
                'description' => 'Material leaked from equipment or containers',
                'loss_type' => 'raw_material',
                'affects_oee' => true,
                'requires_reason' => true,
                'color' => '#8b5cf6', // violet
                'active' => true,
            ],
            
            // OTHER
            [
                'name' => 'Other Loss',
                'code' => 'OTHER',
                'description' => 'Miscellaneous material losses not covered by other categories',
                'loss_type' => 'other',
                'affects_oee' => false,
                'requires_reason' => true,
                'color' => '#6b7280', // gray
                'active' => true,
            ],
        ];

        foreach ($categories as $category) {
            MaterialLossCategory::updateOrCreate(
                ['code' => $category['code']],
                $category
            );
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            [
                'group' => 'formulas',
                'key' => 'formula_target_time_basis',
                'value' => 'planned_production_time',
                'type' => 'select',
                'label' => 'Target Time Basis',
            ],
            [
                'group' => 'formulas',
                'key' => 'formula_availability_exclude_breaks',
                'value' => '1',
                'type' => 'toggle',
                'label' => 'Exclude Planned Downtime from Availability',
            ],
            [
                'group' => 'formulas',
                'key' => 'formula_performance_include_rejects',
                'value' => '1',
                'type' => 'toggle',
                'label' => 'Include Rejects in Performance Calculation',
            ]
        ];

        foreach ($settings as $setting) {
            \App\Models\SiteSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    public function down(): void
    {
        \App\Models\SiteSetting::whereIn('key', [
            'formula_target_time_basis',
            'formula_availability_exclude_breaks',
            'formula_performance_include_rejects'
        ])->delete();
    }
};

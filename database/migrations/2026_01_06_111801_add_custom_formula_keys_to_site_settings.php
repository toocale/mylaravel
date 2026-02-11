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
                'key' => 'formula_availability_mode',
                'value' => 'standard', // 'standard' or 'custom'
                'group' => 'formulas',
                'type' => 'select',
                'label' => 'Availability Formula Mode',
            ],
            [
                'key' => 'formula_availability_expression',
                'value' => '(run_time / planned_production_time) * 100',
                'group' => 'formulas',
                'type' => 'textarea',
                'label' => 'Custom Availability Expression',
            ],
            [
                'key' => 'formula_performance_mode',
                'value' => 'standard',
                'group' => 'formulas',
                'type' => 'select',
                'label' => 'Performance Formula Mode',
            ],
            [
                'key' => 'formula_performance_expression',
                'value' => '(standard_time_produced / run_time) * 100',
                'group' => 'formulas',
                'type' => 'textarea',
                'label' => 'Custom Performance Expression',
            ],
            [
                'key' => 'formula_quality_mode',
                'value' => 'standard',
                'group' => 'formulas',
                'type' => 'select',
                'label' => 'Quality Formula Mode',
            ],
            [
                'key' => 'formula_quality_expression',
                'value' => '(total_goods / total_count) * 100',
                'group' => 'formulas',
                'type' => 'textarea',
                'label' => 'Custom Quality Expression',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $keys = [
            'formula_availability_mode',
            'formula_availability_expression',
            'formula_performance_mode',
            'formula_performance_expression',
            'formula_quality_mode',
            'formula_quality_expression',
        ];

        DB::table('site_settings')->whereIn('key', $keys)->delete();
    }
};

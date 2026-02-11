<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            [
                'key' => 'formula_target_mode',
                'value' => 'static', // static (db), dynamic (ict*time), custom (expression)
                'group' => 'formulas', // Changed from 'general' to 'formulas' to match others
                'type' => 'select',
                'label' => 'Target Calculation Mode',
            ],
            [
                'key' => 'formula_target_expression',
                'value' => 'run_time * weighted_ideal_rate',
                'group' => 'formulas',
                'type' => 'textarea',
                'label' => 'Target Formula',
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
        DB::table('site_settings')->whereIn('key', [
            'formula_target_mode',
            'formula_target_expression',
        ])->delete();
    }
};

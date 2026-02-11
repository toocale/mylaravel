<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\SiteSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add terminology settings for customizable asset names
        $settings = [
            [
                'key' => 'asset_plant_name',
                'value' => 'Plant',
                'group' => 'terminology',
                'type' => 'text',
                'label' => 'Plant Label',
            ],
            [
                'key' => 'asset_line_name',
                'value' => 'Line',
                'group' => 'terminology',
                'type' => 'text',
                'label' => 'Line Label',
            ],
            [
                'key' => 'asset_machine_name',
                'value' => 'Machine',
                'group' => 'terminology',
                'type' => 'text',
                'label' => 'Machine Label',
            ],
        ];

        foreach ($settings as $setting) {
            SiteSetting::firstOrCreate(
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
        SiteSetting::whereIn('key', [
            'asset_plant_name',
            'asset_line_name',
            'asset_machine_name',
        ])->delete();
    }
};

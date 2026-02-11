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
        if (Schema::hasTable('site_settings')) {
            $setting = SiteSetting::firstOrNew(['key' => 'site_name']);
            $setting->fill([
                'value' => $setting->value ?? config('app.name', 'Dawaoee'),
                'group' => 'general',
                'type' => 'text',
                'label' => 'Website Title',
            ]);
            $setting->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse strictly, but we could revert label
    }
};

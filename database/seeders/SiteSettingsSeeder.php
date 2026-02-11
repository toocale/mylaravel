<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'site_name',
                'value' => 'Dawaoee Manufacturing',
                'group' => 'general',
                'type' => 'text',
                'label' => 'Application Name'
            ],
            [
                'key' => 'site_logo',
                'value' => null, // Path to uploaded logo
                'group' => 'general',
                'type' => 'image',
                'label' => 'Application Logo'
            ],
            [
                'key' => 'footer_text',
                'value' => '© 2025 Dawaoee Engineering. All Rights Reserved.',
                'group' => 'footer',
                'type' => 'text',
                'label' => 'Footer Copyright Text'
            ],
            [
                'key' => 'primary_color',
                'value' => '#3b82f6',
                'group' => 'appearance',
                'type' => 'color',
                'label' => 'Primary Brand Color'
            ],
            [
                'key' => 'site_favicon',
                'value' => null,
                'group' => 'general',
                'type' => 'image',
                'label' => 'Browser Favicon'
            ],
            [
                'key' => 'default_theme',
                'value' => 'system',
                'group' => 'appearance',
                'type' => 'select',
                'label' => 'Default Theme Mode',
                // We'll handle options in the frontend or store JSON here if we were fancy
            ],
            [
                'key' => 'site_skin',
                'value' => 'default',
                'group' => 'appearance',
                'type' => 'select',
                'label' => 'Interface Skin'
            ],
            [
                'key' => 'support_email',
                'value' => 'support@dawaoee.com',
                'group' => 'general',
                'type' => 'email',
                'label' => 'Support Email Address'
            ]
        ];

        foreach ($settings as $setting) {
            \App\Models\SiteSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}

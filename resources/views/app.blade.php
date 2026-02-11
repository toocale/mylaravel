@php
    $settings = \App\Models\SiteSetting::all()->pluck('value', 'key');
    $favicon = $settings['site_favicon'] ?? '/favicon.ico';
    $defaultTheme = $settings['default_theme'] ?? 'system';
    $siteSkin = $settings['site_skin'] ?? 'default';
    $siteName = $settings['site_name'] ?? config('app.name', 'Vicoee');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($defaultTheme ?? 'system') == 'dark', 'theme-forest' => ($siteSkin ?? 'default') == 'forest', 'theme-elegant' => ($siteSkin ?? 'default') == 'elegant', 'theme-cyber' => ($siteSkin ?? 'default') == 'cyber'])>
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference or use configured default --}}
        <script>
            (function() {
                const appearance = '{{ $defaultTheme }}'; // Use configured default
                const skin = '{{ $siteSkin }}';
                


                // Apply Skin
                document.documentElement.classList.remove('theme-forest', 'theme-elegant', 'theme-cyber');
                if (skin !== 'default') {
                    document.documentElement.classList.add(`theme-${skin}`);
                }

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                } else if (appearance === 'dark') {
                     document.documentElement.classList.add('dark');
                } else {
                     document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ $siteName }}</title>

        <link rel="icon" href="{{ $favicon }}" sizes="any">
        @if(str_ends_with($favicon, '.svg'))
        <link rel="icon" href="{{ $favicon }}" type="image/svg+xml">
        @endif
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @php
            // Ensure legacy Driftopex page component names map to Vicoee pages
            $component = $page['component'] ?? '';
            $componentPath = "resources/js/pages/{$component}.vue";

            // Legacy compatibility: map Driftopex/ -> Vicoee/ to avoid Vite manifest lookup errors
            if (str_contains($componentPath, 'Driftopex/')) {
                $componentPath = str_replace('Driftopex/', 'Vicoee/', $componentPath);
            }
        @endphp

        @vite(['resources/js/app.ts', $componentPath])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

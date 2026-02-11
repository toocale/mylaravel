import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { initializeTheme } from './composables/useAppearance';

let appName = (import.meta.env.VITE_APP_NAME || 'Vicoee').toString();

createInertiaApp({
    title: (title) => {
        if (!title) return appName;
        if (title.includes(appName)) return title;
        return `${title} - ${appName}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Hydrate appName from server props
        const siteSettings = props.initialPage.props.site as Record<string, string> | undefined;
        if (siteSettings?.site_name) {
            appName = siteSettings.site_name;
        }

        const app = createApp({ render: () => h(App, props) });

        app.use(plugin)
            .use(createPinia());

        // Dynamically update appName when page props change (client-side nav)
        app.mixin({
            watch: {
                '$page.props.site.site_name': {
                    handler(newVal: string) {
                        if (newVal) appName = newVal;
                    },
                    immediate: true
                }
            }
        });

        app.mount(el);
    },
    progress: {
        delay: 250,
        color: '#3b82f6', // Blue color
        includeCSS: true,
        showSpinner: false,
    },
});

initializeTheme();

import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const appName = (import.meta.env.VITE_APP_NAME || 'Vicoee').toString();

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
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
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) }).use(plugin),
        }),
    { cluster: true },
);

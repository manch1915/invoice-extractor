import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import AppLayout from '@/layouts/AppLayout.vue';

type Page = DefineComponent & { layout?: unknown };

const pages = import.meta.glob<{ default: Page }>('./pages/**/*.vue');

void createInertiaApp({
    title: (title) => (title ? `${title} · Invoice Extractor` : 'Invoice Extractor'),
    resolve: async (name) => {
        const loader = pages[`./pages/${name}.vue`];
        if (!loader) {
            throw new Error(`Unknown page: ${name}`);
        }
        const page = (await loader()).default;
        if (page.layout === undefined && !name.startsWith('Auth/')) {
            page.layout = AppLayout;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: { color: 'var(--accent)', delay: 180 },
});

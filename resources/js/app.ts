import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

let appName = import.meta.env.VITE_APP_NAME || 'Sistem Invoice';

const syncBrand = (name?: string, faviconUrl?: string | null) => {
    if (name && name !== appName) {
        const previousName = appName;
        appName = name;

        if (document.title.endsWith(` - ${previousName}`)) {
            document.title = `${document.title.slice(0, -previousName.length)}${appName}`;
        }

        document.querySelectorAll<HTMLMetaElement>('meta[name="application-name"], meta[name="apple-mobile-web-app-title"]')
            .forEach((meta) => meta.content = appName);
    }

    if (faviconUrl) {
        document.querySelectorAll<HTMLLinkElement>('link[rel="icon"], link[rel="apple-touch-icon"]')
            .forEach((link) => link.href = faviconUrl);
    }
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const company = props.initialPage.props.company as
            | { name?: string; favicon_url?: string | null }
            | undefined;
        syncBrand(company?.name, company?.favicon_url);

        router.on('navigate', (event) => {
            const nextCompany = event.detail.page.props.company as
                | { name?: string; favicon_url?: string | null }
                | undefined;
            syncBrand(nextCompany?.name, nextCompany?.favicon_url);
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

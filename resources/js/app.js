import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Send user timezone and GMT offset with every Inertia visit for request logging
router.on('before', (event) => {
    try {
        const opts = Intl.DateTimeFormat().resolvedOptions();
        const tz = opts.timeZone || '';
        const offsetMin = -new Date().getTimezoneOffset();
        const visit = event.detail.visit;
        if (!visit.headers) visit.headers = {};
        visit.headers['X-Timezone'] = tz;
        visit.headers['X-Timezone-Offset'] = String(offsetMin);
    } catch (_) {}
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

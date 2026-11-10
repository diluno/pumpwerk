import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    title: (title) => (title ? `${title} · Pumpwerk` : 'Pumpwerk'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    // Page-to-page navigation animates with the View Transitions API where the
    // browser has it. Form posts (logging a set) keep their own in-page motion.
    defaults: {
        visitOptions: (href, options) => ({ viewTransition: (options.method ?? 'get') === 'get' }),
    },
    progress: {
        color: '#15151b',
    },
});

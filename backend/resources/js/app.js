import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import axios from 'axios';

createInertiaApp({
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue')
    ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
// Automatically attach token to every request
axios.interceptors.request.use(config => {
    const token = localStorage.getItem('access_token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});
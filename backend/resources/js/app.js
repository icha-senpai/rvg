import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import axios from 'axios';
import { route } from 'ziggy-js';
import { Ziggy } from '@/ziggy'; // This file will exist once you publish

function notifyUnauthenticated() {
    try {
        const now = Date.now();

        if (window.__hz_lastUnauthenticatedAt && now - window.__hz_lastUnauthenticatedAt < 3000) {
            return;
        }

        window.__hz_lastUnauthenticatedAt = now;
        window.dispatchEvent(
            new CustomEvent('hz:unauthenticated', {
                detail: {
                    verifyUrl: 'https://horizoninterstellar.com/verify',
                },
            })
        );
    } catch (e) {
        // ignore
    }
}
/* ============================================================
   HORIZON COMPONENT IMPORTS (GLOBAL REGISTRATION)
   ============================================================ */
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonPanel from '@/Components/HorizonPanel.vue';
import HorizonInput from '@/Components/HorizonInput.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';
import HorizonAlert from '@/Components/HorizonAlert.vue';
import HorizonStat from '@/Components/HorizonStat.vue';
import SquadronBadge from '@/Components/SquadronBadge.vue';
import CommandWidget from '@/Components/CommandWidget.vue';
import HUDStatusBar from '@/Components/HUDStatusBar.vue';
import MiniMapPanel from '@/Components/MiniMapPanel.vue';
import MissionGrid from '@/Components/MissionGrid.vue';
import MissionCard from '@/Components/MissionCard.vue';
import ProgressPill from '@/Components/ProgressPill.vue';
import RoleSlotCard from '@/Components/RoleSlotCard.vue';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import AppShell from '@/Components/AppShell.vue';



/* ============================================================
   INERTIA APP INITIALIZATION
   ============================================================ */
createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ).then((module) => {
            const page = module?.default ?? module;

            if (name === 'Verify') {
                page.layout = null;
            } else if (page.layout === undefined) {
                page.layout = AppShell;
            }

            return page;
        }),

    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        app.use(plugin);
        app.config.globalProperties.route = (name, params, absolute = false) =>
            route(name, params, absolute, Ziggy);
        /* ============================================================
           REGISTER GLOBAL HORIZON COMPONENTS
           ============================================================ */
        app.component('HorizonButton', HorizonButton);
        app.component('HorizonPanel', HorizonPanel);
        app.component('HorizonInput', HorizonInput);
        app.component('HorizonSectionHeader', HorizonSectionHeader);
        app.component('HorizonAlert', HorizonAlert);
        app.component('HorizonStat', HorizonStat);
        app.component('SquadronBadge', SquadronBadge);
        app.component('CommandWidget', CommandWidget);
        app.component('HUDStatusBar', HUDStatusBar);
        app.component('MiniMapPanel', MiniMapPanel);
        app.component('MissionGrid', MissionGrid);
        app.component('MissionCard', MissionCard);
        app.component('ProgressPill', ProgressPill);
        app.component('RoleSlotCard', RoleSlotCard);
        app.component('HorizonContainer', HorizonContainer);

        app.mount(el);
    },
});

/* ============================================================
   AXIOS TOKEN ATTACHMENT
   ============================================================ */
axios.interceptors.request.use((config) => {
    const token = localStorage.getItem('access_token');

    if (token && !config.headers?.Authorization) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error?.response?.status;
        const originalRequest = error?.config;

        if (!originalRequest || (status !== 401 && status !== 419)) {
            return Promise.reject(error);
        }

        const url = String(originalRequest?.url ?? '');

        if (url.startsWith('/api/v1/auth/refresh')) {
            return Promise.reject(error);
        }

        if (!url.startsWith('/api/v1/')) {
            notifyUnauthenticated();
            return Promise.reject(error);
        }

        if (originalRequest._retry) {
            notifyUnauthenticated();
            return Promise.reject(error);
        }

        const refreshToken = localStorage.getItem('refresh_token');

        if (!refreshToken) {
            notifyUnauthenticated();
            return Promise.reject(error);
        }

        originalRequest._retry = true;

        try {
            const refreshResponse = await axios.post(
                '/api/v1/auth/refresh',
                {},
                {
                    headers: {
                        Authorization: `Bearer ${refreshToken}`,
                    },
                }
            );

            const newAccessToken = refreshResponse?.data?.access_token;

            if (!newAccessToken) {
                notifyUnauthenticated();
                return Promise.reject(error);
            }

            localStorage.setItem('access_token', newAccessToken);
            originalRequest.headers = originalRequest.headers ?? {};
            originalRequest.headers.Authorization = `Bearer ${newAccessToken}`;

            return axios(originalRequest);
        } catch (refreshError) {
            notifyUnauthenticated();
            return Promise.reject(refreshError);
        }
    }
);

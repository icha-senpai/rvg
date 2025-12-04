import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import axios from 'axios';

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

/* ============================================================
   INERTIA APP INITIALIZATION
   ============================================================ */
createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        ),

    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        app.use(plugin);

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

        app.mount(el);
    },
});

/* ============================================================
   AXIOS TOKEN ATTACHMENT
   ============================================================ */
axios.interceptors.request.use((config) => {
    const token = localStorage.getItem('access_token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

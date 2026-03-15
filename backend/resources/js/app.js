import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { route } from 'ziggy-js';
import { Ziggy } from '@/ziggy'; // This file will exist once you publish
import { extractFirstErrorMessage, notifyError } from '@/errors'

window.hzNotifyError = notifyError;

try {
    router.on('invalid', (event) => {
        const detail = event?.detail ?? {};
        const errors = detail?.errors ?? detail?.response?.data?.errors ?? null;
        notifyError({ message: extractFirstErrorMessage(errors) });
    });
} catch (e) {
    // ignore
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
import HorizonErrorDialog from '@/Components/HorizonErrorDialog.vue';



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

            if (name === 'Verify' || name === 'Error') {
                page.layout = null;
            } else if (page.layout === undefined) {
                page.layout = AppShell;
            }

            return page;
        }),

    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () =>
                h('div', {}, [
                    h(HorizonErrorDialog),
                    h(App, props),
                ]),
        });

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


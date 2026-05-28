<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

const props = defineProps({
  logs: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  actionOptions: { type: Array, default: () => [] },
})

const selectedAction = ref(props.filters?.action ?? '')
const search = ref(props.filters?.search ?? '')
const rows = computed(() => props.logs?.data ?? [])
const links = computed(() => props.logs?.links ?? [])

function applyFilters() {
  router.get(route('admin.archive.audit.index'), {
    action: selectedAction.value || undefined,
    search: search.value || undefined,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

function clearFilters() {
  selectedAction.value = ''
  search.value = ''
  router.get(route('admin.archive.audit.index'), {}, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

function actorName(log) {
  return log.user?.rsi_handle || log.user?.discord_name || 'Unknown user'
}

function prettyMeta(meta) {
  return JSON.stringify(meta ?? {}, null, 2)
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <section class="rounded-[2rem] border border-white/[0.055] bg-white/[0.024] p-6 md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Archive Security Trail</div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">Archive Audit Log</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Review topic, entry, category, and tag changes made through the Archive admin tools.
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <Link :href="route('admin.archive.index')" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Back to Archive</Link>
            <Link :href="route('admin.archive.taxonomy.index')" class="rounded-xl border border-white/[0.055] bg-white/[0.042] px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-magenta)]/20">Categories & Tags</Link>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-white/[0.055] bg-white/[0.024] p-5">
        <form class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)_auto] lg:items-end" @submit.prevent="applyFilters">
          <HorizonSelect v-model="selectedAction" label="Action" :options="actionOptions" />
          <HorizonInput v-model="search" label="Search" placeholder="Search title, name, slug, action..." />

          <div class="flex gap-2">
            <HorizonButton type="submit">Filter</HorizonButton>
            <HorizonButton type="button" variant="ghost" @click="clearFilters">Clear</HorizonButton>
          </div>
        </form>
      </section>

      <section class="space-y-4">
        <article v-for="log in rows" :key="log.id" class="overflow-hidden rounded-3xl border border-white/[0.055] bg-white/[0.024]">
          <div class="grid gap-4 border-b border-white/10 p-5 lg:grid-cols-[minmax(0,1fr)_auto]">
            <div>
              <div class="flex flex-wrap gap-2">
                <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-bold text-[color:var(--horizon-sunset-blue)]">{{ log.action }}</span>
                <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">{{ log.created_label }}</span>
              </div>

              <h2 class="mt-3 text-xl font-black text-horizon-white">{{ log.summary }}</h2>
              <p class="mt-2 text-sm text-text-secondary">
                Actor: <span class="font-bold text-horizon-white">{{ actorName(log) }}</span>
                <span v-if="log.ip_address"> · IP: {{ log.ip_address }}</span>
              </p>
            </div>
          </div>

          <details class="group">
            <summary class="cursor-pointer px-5 py-3 text-sm font-bold text-text-secondary hover:text-horizon-white">View metadata</summary>
            <pre class="max-h-96 overflow-auto border-t border-white/10 bg-black/30 p-5 text-xs leading-5 text-text-secondary">{{ prettyMeta(log.meta) }}</pre>
          </details>
        </article>

        <div v-if="!rows.length" class="rounded-3xl border border-white/[0.055] bg-white/[0.024] p-6 text-sm text-text-secondary">
          No archive audit logs matched your filters.
        </div>
      </section>

      <nav v-if="links.length > 3" class="flex flex-wrap gap-2">
        <Link
          v-for="link in links"
          :key="link.label"
          :href="link.url || ''"
          preserve-scroll
          preserve-state
          class="rounded-xl border px-3 py-2 text-sm font-bold"
          :class="[
            link.active ? 'border-[color:var(--horizon-sunset-blue)]/40 bg-[color:var(--horizon-sunset-blue)]/15 text-horizon-white' : 'border-white/10 text-text-secondary hover:text-horizon-white',
            !link.url ? 'pointer-events-none opacity-50' : ''
          ]"
          v-html="link.label"
        />
      </nav>
    </div>
  </HorizonContainer>
</template>









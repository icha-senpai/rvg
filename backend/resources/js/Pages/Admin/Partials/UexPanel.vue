<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  uex: {
    type: Object,
    default: () => ({
      commands: {},
      groups: [],
      total_rows: 0,
      last_run: null,
    }),
  },
})

const copiedCommandKey = ref(null)
const runningSyncKey = ref(null)
const activeSection = ref('overview')
const activeGroup = ref(props.uex?.groups?.[0]?.key ?? 'locations')
const selectedResource = ref(props.uex?.groups?.[0]?.resources?.[0]?.resource ?? null)
const viewerSearch = ref('')
const viewerSearchInput = ref(null)
const viewerLoading = ref(false)
const viewerError = ref('')
const lastAppliedSearch = ref('')
const viewerData = ref({
  resource: null,
  label: '',
  group: '',
  table: '',
  columns: [],
  rows: {
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: null,
    to: null,
  },
})
const page = usePage()
const dashboardFlash = computed(() => ({
  success: page.props?.flash?.success ?? '',
  error: page.props?.flash?.error ?? '',
}))

const groupMap = computed(() => new Map((props.uex?.groups ?? []).map((group) => [group.key, group])))
const syncActions = computed(() => props.uex?.sync_actions ?? {})
const activeGroupData = computed(() => groupMap.value.get(activeGroup.value) ?? props.uex?.groups?.[0] ?? null)
const activeResources = computed(() => activeGroupData.value?.resources ?? [])
const selectedResourceMeta = computed(() => {
  return activeResources.value.find((resource) => resource.resource === selectedResource.value) ?? activeResources.value[0] ?? null
})
const visibleRows = computed(() => viewerData.value?.rows?.data ?? [])
const visibleColumns = computed(() => viewerData.value?.columns ?? [])

function formatDate(value) {
  if (!value) return 'Not run yet'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function formatScope(value) {
  if (!value) return 'Unknown'
  return String(value).replace(/_/g, ' ')
}

function statusClass(status) {
  switch (status) {
    case 'success':
      return 'border-emerald-300/35 bg-emerald-300/10 text-emerald-100'
    case 'partial_failure':
      return 'border-amber-300/35 bg-amber-300/10 text-amber-100'
    case 'failed':
      return 'border-red-300/35 bg-red-300/10 text-red-100'
    default:
      return 'border-white/[0.055] bg-white/[0.024] text-text-secondary'
  }
}

function sectionClass(section) {
  return activeSection.value === section
    ? 'border-amber-300/35 bg-amber-300/10 text-horizon-white'
    : 'border-white/[0.055] bg-white/[0.024] text-text-secondary hover:bg-white/[0.04] hover:text-horizon-white'
}

function focusViewerSearchInput() {
  nextTick(() => {
    viewerSearchInput.value?.focus?.()
  })
}

function setActiveSection(section) {
  activeSection.value = section

  if (section === 'browser') {
    focusViewerSearchInput()
  }
}

async function copyCommand(key, command) {
  if (!command || !navigator?.clipboard?.writeText) return

  try {
    await navigator.clipboard.writeText(command)
    copiedCommandKey.value = key
    window.setTimeout(() => {
      if (copiedCommandKey.value === key) {
        copiedCommandKey.value = null
      }
    }, 1800)
  } catch {
    copiedCommandKey.value = null
  }
}

function runSync(key) {
  const action = syncActions.value?.[key]

  if (!action) {
    return
  }

  runningSyncKey.value = key

  router.post(route('admin.uex.sync'), {
    scope: action.scope ?? 'all',
    resources: action.resources ?? [],
  }, {
    preserveScroll: true,
    onFinish: () => {
      runningSyncKey.value = null
    },
  })
}

function selectGroup(groupKey) {
  activeGroup.value = groupKey
  selectedResource.value = groupMap.value.get(groupKey)?.resources?.[0]?.resource ?? null
  viewerSearch.value = ''
  loadViewerData(1)
  focusViewerSearchInput()
}

function selectResource(resourceKey) {
  selectedResource.value = resourceKey
  loadViewerData(1)
  focusViewerSearchInput()
}

async function loadViewerData(page = 1) {
  if (!selectedResource.value) return

  viewerLoading.value = true
  viewerError.value = ''

  const params = new URLSearchParams()
  params.set('page', String(page))
  params.set('per_page', '20')

  if (viewerSearch.value.trim()) {
    params.set('search', viewerSearch.value.trim())
  }

  try {
    const response = await fetch(`/admin/uex/resources/${selectedResource.value}?${params.toString()}`, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })

    const json = await response.json()

    if (!response.ok || json?.status !== 'success') {
      viewerError.value = json?.message ?? 'Failed to load synced UEX data.'
      return
    }

    viewerData.value = json.payload
    lastAppliedSearch.value = viewerSearch.value.trim()
  } catch {
    viewerError.value = 'Failed to load synced UEX data.'
  } finally {
    viewerLoading.value = false
  }
}

function runViewerSearch() {
  loadViewerData(1)
}

function clearViewerSearch() {
  viewerSearch.value = ''
  loadViewerData(1)
}

function formatCell(value) {
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}

onMounted(() => {
  if (selectedResource.value) {
    loadViewerData(1)
  }
})
</script>

<template>
  <div class="space-y-5">
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
          UEX Snapshot Console
        </div>

        <h3 class="mt-1 text-2xl font-black text-horizon-white">
          Manual Sync Coverage
        </h3>

        <p class="mt-2 max-w-3xl text-sm text-text-secondary">
          The site reads from local UEX snapshot tables. Run syncs from the command line when you want to refresh public trade, location, vehicle, and refinery data.
        </p>

        <div
          v-if="dashboardFlash.success || dashboardFlash.error"
          class="mt-4 rounded-[1rem] border px-4 py-3 text-sm"
          :class="dashboardFlash.error
            ? 'border-red-300/25 bg-red-300/10 text-red-100'
            : 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100'"
        >
          {{ dashboardFlash.error || dashboardFlash.success }}
        </div>
      </div>

      <div class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.024] px-4 py-3 text-right">
        <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
          Local Rows
        </div>

        <div class="mt-1 text-2xl font-black text-horizon-white">
          {{ uex.total_rows ?? 0 }}
        </div>
      </div>
    </div>

    <section class="flex flex-wrap gap-2">
      <button
        type="button"
        class="rounded-full border px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] transition"
        :class="sectionClass('overview')"
        @click="setActiveSection('overview')"
      >
        Overview
      </button>

      <button
        type="button"
        class="rounded-full border px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] transition"
        :class="sectionClass('browser')"
        @click="setActiveSection('browser')"
      >
        Data Browser
      </button>
    </section>

    <template v-if="activeSection === 'overview'">
      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.024] p-4">
          <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Last Run</div>
          <div class="mt-2 text-lg font-black text-horizon-white">#{{ uex.last_run?.id ?? '—' }}</div>
          <div class="mt-1 text-sm text-text-secondary">{{ formatDate(uex.last_run?.finished_at ?? uex.last_run?.started_at) }}</div>
        </div>

        <div class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.024] p-4">
          <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Scope</div>
          <div class="mt-2 text-lg font-black text-horizon-white">{{ formatScope(uex.last_run?.scope ?? 'all') }}</div>
          <div class="mt-1 text-sm text-text-secondary">
            {{ uex.last_run?.successful_resources ?? 0 }} success / {{ uex.last_run?.failed_resources ?? 0 }} failed
          </div>
        </div>

        <div class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.024] p-4">
          <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Processed</div>
          <div class="mt-2 text-lg font-black text-horizon-white">{{ uex.last_run?.total_records ?? 0 }}</div>
          <div class="mt-1 text-sm text-text-secondary">Rows touched in the latest sync run.</div>
        </div>

        <div
          class="rounded-[1.25rem] border p-4"
          :class="statusClass(uex.last_run?.status)"
        >
          <div class="text-xs font-bold uppercase tracking-[0.16em]">
            Status
          </div>

          <div class="mt-2 text-lg font-black">
            {{ formatScope(uex.last_run?.status ?? 'not_run') }}
          </div>

          <div class="mt-1 text-sm">
            {{ uex.last_run?.error_message ?? 'No sync errors recorded.' }}
          </div>
        </div>
      </section>

      <section class="rounded-[1.75rem] border border-white/[0.055] bg-white/[0.02] p-5">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Recommended Commands</div>
            <div class="mt-1 text-lg font-black text-horizon-white">Manual Sync Triggers</div>
          </div>

          <div class="text-sm text-text-secondary">
            Run these from the backend folder.
          </div>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-2">
          <div
            v-for="(command, key) in uex.commands"
            :key="key"
            class="rounded-[1.25rem] border border-white/[0.055] bg-white/[0.024] p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
                  {{ String(key).replace(/_/g, ' ') }}
                </div>

                <code class="mt-2 block break-all text-sm text-horizon-white">
                  {{ command }}
                </code>
              </div>

              <button
                v-if="syncActions[key]"
                type="button"
                class="rounded-full border border-amber-300/30 bg-amber-300/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-amber-100 transition hover:bg-amber-300/16 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="runningSyncKey !== null"
                @click="runSync(key)"
              >
                {{ runningSyncKey === key ? 'Syncing' : 'Run Sync' }}
              </button>

              <button
                type="button"
                class="rounded-full border border-white/[0.08] bg-white/[0.03] px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-text-secondary transition hover:bg-white/[0.06] hover:text-horizon-white"
                @click="copyCommand(key, command)"
              >
                {{ copiedCommandKey === key ? 'Copied' : 'Copy' }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-4 xl:grid-cols-2">
        <article
          v-for="group in uex.groups ?? []"
          :key="group.key"
          class="rounded-[1.75rem] border border-white/[0.055] bg-white/[0.024] p-5"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                {{ group.label }}
              </div>

              <h4 class="mt-1 text-xl font-black text-horizon-white">
                {{ group.row_count }} Rows
              </h4>
            </div>

            <div class="rounded-full border border-white/[0.055] bg-white/[0.03] px-3 py-1 text-xs font-bold text-horizon-white">
              {{ group.resource_count }} resources
            </div>
          </div>

          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div
              v-for="resource in group.resources"
              :key="resource.resource"
              class="rounded-[1rem] border border-white/[0.055] bg-black/10 px-4 py-3"
            >
              <div class="text-xs font-bold uppercase tracking-[0.14em] text-text-muted">
                {{ resource.label }}
              </div>

              <div class="mt-1 text-lg font-black text-horizon-white">
                {{ resource.rows }}
              </div>

              <div class="mt-1 text-xs text-text-secondary">
                {{ resource.table }}
              </div>
            </div>
          </div>
        </article>
      </section>

      <section
        v-if="uex.last_run?.resource_results?.length"
        class="rounded-[1.75rem] border border-white/[0.055] bg-white/[0.02] p-5"
      >
        <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">Latest Run Detail</div>
        <h4 class="mt-1 text-xl font-black text-horizon-white">Per-Resource Results</h4>

        <div class="mt-4 grid gap-3 xl:grid-cols-2">
          <article
            v-for="result in uex.last_run.resource_results"
            :key="`${result.resource}-${result.started_at}`"
            class="rounded-[1.25rem] border p-4"
            :class="statusClass(result.status)"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm font-black uppercase tracking-[0.12em]">
                {{ result.label }}
              </div>

              <div class="text-xs font-bold uppercase tracking-[0.14em]">
                {{ result.status }}
              </div>
            </div>

            <div class="mt-2 text-sm">
              {{ result.records }} row(s)
            </div>

            <div class="mt-2 text-xs opacity-80">
              {{ result.resource }}
            </div>

            <div
              v-if="result.error"
              class="mt-3 rounded-xl border border-black/10 bg-black/10 px-3 py-2 text-xs leading-5"
            >
              {{ result.error }}
            </div>
          </article>
        </div>
      </section>
    </template>

    <template v-else>
      <section class="grid gap-5 xl:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="rounded-[1.75rem] border border-white/[0.055] bg-white/[0.024] p-4">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">UEX Groups</div>
          <div class="mt-4 space-y-3">
            <button
              v-for="group in uex.groups ?? []"
              :key="group.key"
              type="button"
              class="w-full rounded-[1.25rem] border p-4 text-left transition"
              :class="activeGroup === group.key
                ? 'border-amber-300/35 bg-amber-300/10'
                : 'border-white/[0.055] bg-black/10 hover:bg-white/[0.04]'"
              @click="selectGroup(group.key)"
            >
              <div class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">{{ group.label }}</div>
              <div class="mt-1 text-lg font-black text-horizon-white">{{ group.row_count }} Rows</div>
              <div class="mt-1 text-xs text-text-secondary">{{ group.resource_count }} resources</div>
            </button>
          </div>
        </aside>

        <section class="space-y-5">
          <div class="rounded-[1.75rem] border border-white/[0.055] bg-white/[0.024] p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  {{ activeGroupData?.label ?? 'UEX Data' }}
                </div>
                <h4 class="mt-1 text-xl font-black text-horizon-white">
                  Browse Synced Records
                </h4>
                <p class="mt-2 text-sm text-text-secondary">
                  Pick a resource to inspect the rows currently stored in your local snapshot tables.
                </p>
              </div>

              <div class="xl:min-w-[26rem]">
                <form
                  class="relative z-10 flex flex-col gap-2 xl:items-end"
                  @submit.prevent="runViewerSearch"
                >
                  <label
                    for="uex-data-browser-search"
                    class="text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted"
                  >
                    Filter Rows
                  </label>

                  <div class="flex w-full flex-col gap-2 sm:flex-row">
                    <input
                      id="uex-data-browser-search"
                      ref="viewerSearchInput"
                      v-model="viewerSearch"
                      type="search"
                      autocomplete="off"
                      spellcheck="false"
                      placeholder="Search this resource..."
                      class="hz-input min-w-0 flex-1"
                      @keydown.enter.prevent="runViewerSearch"
                    />

                    <button
                      type="submit"
                      class="rounded-full border border-white/[0.08] bg-white/[0.03] px-4 py-2 text-xs font-bold uppercase tracking-[0.14em] text-text-secondary transition hover:bg-white/[0.06] hover:text-horizon-white disabled:cursor-not-allowed disabled:opacity-40"
                      :disabled="viewerLoading"
                    >
                      Search
                    </button>

                    <button
                      type="button"
                      class="rounded-full border border-white/[0.08] bg-white/[0.03] px-4 py-2 text-xs font-bold uppercase tracking-[0.14em] text-text-secondary transition hover:bg-white/[0.06] hover:text-horizon-white disabled:cursor-not-allowed disabled:opacity-40"
                      :disabled="viewerLoading || (!viewerSearch && !lastAppliedSearch)"
                      @click="clearViewerSearch"
                    >
                      Clear
                    </button>
                  </div>
                </form>
              </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
              <button
                v-for="resource in activeResources"
                :key="resource.resource"
                type="button"
                class="rounded-full border px-4 py-2 text-xs font-bold uppercase tracking-[0.14em] transition"
                :class="selectedResource === resource.resource
                  ? 'border-amber-300/35 bg-amber-300/10 text-horizon-white'
                  : 'border-white/[0.055] bg-white/[0.024] text-text-secondary hover:bg-white/[0.04] hover:text-horizon-white'"
                @click="selectResource(resource.resource)"
              >
                {{ resource.label }}
              </button>
            </div>

            <div
              v-if="lastAppliedSearch"
              class="mt-3 text-xs font-semibold uppercase tracking-[0.14em] text-text-muted"
            >
              Active search: {{ lastAppliedSearch }}
            </div>
          </div>

          <section class="rounded-[1.75rem] border border-white/[0.055] bg-white/[0.02] p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  {{ viewerData.label || selectedResourceMeta?.label || 'Resource' }}
                </div>
                <h4 class="mt-1 text-xl font-black text-horizon-white">
                  {{ viewerData.table || selectedResourceMeta?.table || 'Loading...' }}
                </h4>
                <p class="mt-2 text-sm text-text-secondary">
                  {{ viewerData.rows?.total ?? 0 }} total row(s)
                  <span v-if="viewerData.rows?.from">
                    • showing {{ viewerData.rows.from }}-{{ viewerData.rows.to }}
                  </span>
                </p>
              </div>

              <div
                v-if="viewerLoading"
                class="rounded-full border border-amber-300/30 bg-amber-300/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-amber-100"
              >
                Loading
              </div>
            </div>

            <div
              v-if="viewerError"
              class="mt-4 rounded-[1.25rem] border border-red-300/25 bg-red-300/10 px-4 py-3 text-sm text-red-100"
            >
              {{ viewerError }}
            </div>

            <div
              v-else-if="!visibleRows.length && !viewerLoading"
              class="mt-4 rounded-[1.25rem] border border-dashed border-white/15 bg-white/[0.02] p-8 text-center"
            >
              <div class="text-sm font-bold uppercase tracking-[0.18em] text-text-muted">No Rows Found</div>
              <p class="mt-2 text-sm text-text-secondary">
                This resource does not have any synced rows yet, or your current search returned nothing.
              </p>
            </div>

            <div
              v-else
              class="mt-4 overflow-hidden rounded-[1.25rem] border border-white/[0.055]"
            >
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/[0.055]">
                  <thead class="bg-black/20">
                    <tr>
                      <th
                        v-for="column in visibleColumns"
                        :key="column"
                        class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.18em] text-text-muted"
                      >
                        {{ column }}
                      </th>
                    </tr>
                  </thead>

                  <tbody class="divide-y divide-white/[0.055] bg-white/[0.02]">
                    <tr
                      v-for="(row, index) in visibleRows"
                      :key="`${viewerData.resource}-${index}`"
                    >
                      <td
                        v-for="column in visibleColumns"
                        :key="column"
                        class="max-w-[16rem] px-4 py-3 align-top text-sm text-horizon-white"
                      >
                        <span class="block break-words">
                          {{ formatCell(row[column]) }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div
              v-if="viewerData.rows?.last_page > 1"
              class="mt-4 flex flex-wrap items-center justify-between gap-3"
            >
              <div class="text-sm text-text-secondary">
                Page {{ viewerData.rows.current_page }} of {{ viewerData.rows.last_page }}
              </div>

              <div class="flex gap-2">
                <button
                  type="button"
                  class="rounded-full border border-white/[0.08] bg-white/[0.03] px-4 py-2 text-xs font-bold uppercase tracking-[0.14em] text-text-secondary transition hover:bg-white/[0.06] hover:text-horizon-white disabled:cursor-not-allowed disabled:opacity-40"
                  :disabled="viewerData.rows.current_page <= 1 || viewerLoading"
                  @click="loadViewerData(viewerData.rows.current_page - 1)"
                >
                  Prev
                </button>

                <button
                  type="button"
                  class="rounded-full border border-white/[0.08] bg-white/[0.03] px-4 py-2 text-xs font-bold uppercase tracking-[0.14em] text-text-secondary transition hover:bg-white/[0.06] hover:text-horizon-white disabled:cursor-not-allowed disabled:opacity-40"
                  :disabled="viewerData.rows.current_page >= viewerData.rows.last_page || viewerLoading"
                  @click="loadViewerData(viewerData.rows.current_page + 1)"
                >
                  Next
                </button>
              </div>
            </div>
          </section>
        </section>
      </section>
    </template>
  </div>
</template>

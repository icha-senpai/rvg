<script setup>
import { computed, onMounted, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonCheckbox from '@/Components/HorizonCheckbox.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonDrawer from '@/Components/HorizonDrawer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import ArchiveMediaPicker from './Components/ArchiveMediaPicker.vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  topics: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
  rankOptions: { type: Array, default: () => [] },
  previewRankLevel: { type: [Number, null], default: null },
})

const topicDrawerOpen = ref(false)
const editingTopicId = ref(null)
const previewRank = ref(props.previewRankLevel)
const deleteTopicDialog = ref(null)
const topicPendingDelete = ref(null)
const openGroupKeys = ref(new Set())

const openGroupStorageKey = 'admin.archive.index.open-groups'

const blankTopic = {
  archive_category_id: null,
  title: '',
  slug: '',
  description: '',
  card_image_path: '',
  banner_image_path: '',
  sort_order: 0,
  minimum_rank_level: null,
  is_published: true,
}

const topicForm = useForm({ ...blankTopic })

const categories = computed(() => props.categories ?? [])
const sortedTopics = computed(() => props.topics ?? [])
const categoryOptions = computed(() => props.categoryOptions ?? [])
const categoryPositionLookup = computed(() => {
  return new Map(categoryOptions.value.map((option, index) => [String(option.value), index]))
})
const groupedTopics = computed(() => {
  const groups = new Map()

  for (const category of categories.value) {
    const key = String(category.id)

    groups.set(key, {
      key,
      label: category.name,
      description: category.description,
      topics: [],
      directEntries: category.direct_entries ?? [],
      entriesCount: 0,
      directEntriesCount: Number(category.direct_entries_count ?? 0),
      entriesAdminHref: category.entries_admin_href,
      createEntryAdminHref: category.create_entry_admin_href,
      publicHref: category.public_href,
    })
  }

  for (const topic of sortedTopics.value) {
    const key = String(topic.archive_category_id ?? topic.category_label ?? 'uncategorized')
    const label = topic.category_label || 'Uncategorized'

    if (!groups.has(key)) {
      groups.set(key, {
        key,
        label,
        description: null,
        topics: [],
        directEntries: [],
        entriesCount: 0,
        directEntriesCount: 0,
        entriesAdminHref: null,
        createEntryAdminHref: null,
        publicHref: null,
      })
    }

    const group = groups.get(key)
    group.topics.push(topic)
    group.entriesCount += Number(topic.entries_count ?? 0)
  }

  return Array.from(groups.values()).sort((left, right) => {
    const leftPosition = categoryPositionLookup.value.get(left.key) ?? Number.MAX_SAFE_INTEGER
    const rightPosition = categoryPositionLookup.value.get(right.key) ?? Number.MAX_SAFE_INTEGER

    if (leftPosition !== rightPosition) {
      return leftPosition - rightPosition
    }

    return left.label.localeCompare(right.label)
  })
})
const previewOptions = computed(() => props.rankOptions ?? [])
const previewLabel = computed(() => previewOptions.value.find(option => option.value === previewRank.value)?.label ?? 'All verified members')
const editingTopic = computed(() => sortedTopics.value.find(topic => topic.id === editingTopicId.value) ?? null)
const isEditingTopic = computed(() => Boolean(editingTopicId.value))
const drawerTitle = computed(() => isEditingTopic.value ? 'Edit Archive Topic' : 'New Archive Topic')
const drawerEyebrow = computed(() => isEditingTopic.value ? 'Topic Editor' : 'Topic Builder')
const drawerDescription = computed(() => isEditingTopic.value
  ? 'Update the topic card, images, publishing state, and rank visibility.'
  : 'Create a topic card that can hold Archive entries and member-facing knowledge content.'
)

onMounted(() => {
  loadOpenGroupKeys()
})

function changePreviewRank() {
  router.get(route('admin.archive.index'), {
    preview_rank_level: previewRank.value ?? '',
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

function loadOpenGroupKeys() {
  if (typeof window === 'undefined') return

  try {
    const raw = window.localStorage.getItem(openGroupStorageKey)

    if (!raw) {
      openGroupKeys.value = new Set()
      return
    }

    const parsed = JSON.parse(raw)
    openGroupKeys.value = new Set(Array.isArray(parsed) ? parsed.map(value => String(value)) : [])
  } catch {
    openGroupKeys.value = new Set()
  }
}

function persistOpenGroupKeys() {
  if (typeof window === 'undefined') return

  window.localStorage.setItem(openGroupStorageKey, JSON.stringify(Array.from(openGroupKeys.value)))
}

function groupIsOpen(groupKey) {
  return openGroupKeys.value.has(String(groupKey))
}

function toggleGroup(groupKey) {
  const next = new Set(openGroupKeys.value)
  const normalizedKey = String(groupKey)

  if (next.has(normalizedKey)) {
    next.delete(normalizedKey)
  } else {
    next.add(normalizedKey)
  }

  openGroupKeys.value = next
  persistOpenGroupKeys()
}

function hydrateTopicForm(topic = null) {
  const source = topic ?? blankTopic

  topicForm.archive_category_id = source.archive_category_id ?? null
  topicForm.title = source.title ?? ''
  topicForm.slug = source.slug ?? ''
  topicForm.description = source.description ?? ''
  topicForm.card_image_path = source.card_image_path ?? ''
  topicForm.banner_image_path = source.banner_image_path ?? ''
  topicForm.sort_order = source.sort_order ?? 0
  topicForm.minimum_rank_level = source.minimum_rank_level ?? null
  topicForm.is_published = Boolean(source.is_published ?? true)
  topicForm.clearErrors()
}

function openCreateDrawer() {
  editingTopicId.value = null
  hydrateTopicForm()
  topicDrawerOpen.value = true
}

function startEdit(topic) {
  editingTopicId.value = topic.id
  hydrateTopicForm(topic)
  topicDrawerOpen.value = true
}

function closeTopicDrawer() {
  topicDrawerOpen.value = false
  editingTopicId.value = null
  topicForm.reset()
  topicForm.clearErrors()
}

function submitTopic() {
  if (isEditingTopic.value && editingTopic.value) {
    topicForm.put(route('admin.archive.topics.update', editingTopic.value.id), {
      preserveScroll: true,
      onSuccess: () => closeTopicDrawer(),
    })
    return
  }

  topicForm.post(route('admin.archive.topics.store'), {
    preserveScroll: true,
    onSuccess: () => closeTopicDrawer(),
  })
}

function askDeleteTopic(topic) {
  topicPendingDelete.value = topic
  deleteTopicDialog.value?.show()
}

function confirmDeleteTopic({ close }) {
  if (!topicPendingDelete.value) return

  router.delete(route('admin.archive.topics.destroy', topicPendingDelete.value.id), {
    preserveScroll: true,
    onFinish: () => {
      topicPendingDelete.value = null
      close()
    },
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <section class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-6 md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Director Tools</div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">Archive Management</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Build the archive in its new hierarchy: create Categories first, place Topics inside them, and use the entry controls here to manage both topic entries and direct category entries.
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <HorizonButton type="button" @click="openCreateDrawer">New Topic</HorizonButton>
            <Link :href="route('admin.archive.taxonomy.index')" class="rounded-xl border border-white/[0.055] bg-white/[0.042] px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-magenta)]/20">Manage Categories & Tags</Link>
            <Link :href="route('admin.archive.trash.index')" class="rounded-xl border border-red-300/25 bg-red-300/10 px-4 py-2 text-sm font-bold text-red-100 hover:bg-red-300/15">Trash</Link>
            <Link :href="route('admin.archive.audit.index')" class="rounded-xl border border-[color:var(--horizon-sunset-blue)]/35 bg-white/[0.042] px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-blue)]/20">Audit Log</Link>
            <Link :href="route('archive.index')" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45">View Archive</Link>
            <Link :href="route('admin.dashboard')" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Admin Dashboard</Link>
          </div>
        </div>
      </section>

      <section class="hz-surface-welcome rounded-3xl border border-white/[0.055] p-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Rank Preview</div>
            <p class="mt-1 text-sm text-text-secondary">Currently previewing topic visibility as <span class="font-bold text-horizon-white">{{ previewLabel }}</span>.</p>
          </div>

          <div class="w-full md:w-72">
            <HorizonSelect v-model="previewRank" :options="previewOptions" @update:model-value="changePreviewRank" />
          </div>
        </div>
      </section>

      <section class="space-y-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Category → Topic Structure</div>
            <h2 class="text-2xl font-black text-horizon-white">{{ groupedTopics.length }} categor{{ groupedTopics.length === 1 ? 'y' : 'ies' }} · {{ sortedTopics.length }} topic{{ sortedTopics.length === 1 ? '' : 's' }}</h2>
            <p class="mt-1 text-sm text-text-secondary">Direct category entries now live inline with their category so you can scan them without drilling into a second screen first.</p>
          </div>

          <HorizonButton type="button" variant="ghost" @click="openCreateDrawer">Create Topic</HorizonButton>
        </div>

        <div v-if="groupedTopics.length" class="space-y-6">
          <section v-for="group in groupedTopics" :key="group.key" class="hz-surface-welcome rounded-[2rem] border border-white/[0.055] p-5 md:p-6">
            <div
              class="flex cursor-pointer flex-col gap-3 border-b border-white/10 pb-4 md:flex-row md:items-end md:justify-between"
              role="button"
              tabindex="0"
              :aria-expanded="groupIsOpen(group.key)"
              @click="toggleGroup(group.key)"
              @keydown.enter.prevent="toggleGroup(group.key)"
              @keydown.space.prevent="toggleGroup(group.key)"
            >
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Category</div>
                <h3 class="mt-1 text-2xl font-black text-horizon-white">{{ group.label }}</h3>
                <p v-if="group.description" class="mt-2 max-w-3xl text-sm leading-6 text-text-secondary">{{ group.description }}</p>
              </div>

              <div class="flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
                <span class="rounded-full border border-white/10 px-3 py-1">{{ group.topics.length }} topic{{ group.topics.length === 1 ? '' : 's' }}</span>
                <span class="rounded-full border border-white/10 px-3 py-1">{{ group.directEntriesCount }} direct entr{{ group.directEntriesCount === 1 ? 'y' : 'ies' }}</span>
                <span class="rounded-full border border-white/10 px-3 py-1">{{ group.entriesCount }} topic entr{{ group.entriesCount === 1 ? 'y' : 'ies' }}</span>
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-full border border-white/[0.055] px-3 py-1 text-horizon-white hover:bg-white/[0.05]"
                  @click.stop="toggleGroup(group.key)"
                >
                  <span>{{ groupIsOpen(group.key) ? 'Collapse' : 'Expand' }}</span>
                  <span class="text-[10px] transition-transform duration-200" :class="groupIsOpen(group.key) ? 'rotate-180' : ''">⌄</span>
                </button>
                <Link v-if="group.createEntryAdminHref" :href="group.createEntryAdminHref" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/35 px-3 py-1 text-horizon-white hover:bg-[color:var(--horizon-sunset-magenta)]/20" @click.stop>New Direct Entry</Link>
                <Link v-if="group.entriesAdminHref" :href="group.entriesAdminHref" class="rounded-full border border-white/[0.055] px-3 py-1 text-horizon-white hover:bg-white/[0.05]" @click.stop>Manage Direct Entries</Link>
                <Link v-if="group.publicHref" :href="group.publicHref" class="rounded-full border border-white/[0.055] px-3 py-1 text-text-secondary hover:text-horizon-white" @click.stop>View Category</Link>
              </div>
            </div>

            <template v-if="groupIsOpen(group.key)">
              <div class="hz-surface-welcome mt-5 rounded-3xl border border-white/[0.055] p-4 md:p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                  <div>
                    <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Direct Entries</div>
                    <p class="mt-1 text-sm text-text-secondary">Category-level documents that are not nested under a topic.</p>
                  </div>

                  <div class="flex flex-wrap gap-2">
                    <Link v-if="group.createEntryAdminHref" :href="group.createEntryAdminHref" class="rounded-xl border border-[color:var(--horizon-sunset-magenta)]/35 bg-white/[0.042] px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-magenta)]/20">Create Direct Entry</Link>
                    <Link v-if="group.entriesAdminHref" :href="group.entriesAdminHref" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Open Direct Entry Editor</Link>
                  </div>
                </div>

                <div v-if="group.directEntries.length" class="mt-4 grid gap-4 xl:grid-cols-2">
                  <article v-for="entry in group.directEntries" :key="`direct-${group.key}-${entry.id}`" class="hz-surface-welcome rounded-3xl border border-white/[0.055] p-5">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                      <div>
                        <div class="flex flex-wrap gap-2">
                          <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">{{ entry.minimum_rank_label }}</span>
                          <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold" :class="entry.is_published ? 'text-emerald-300' : 'text-red-300'">{{ entry.is_published ? 'Published' : 'Draft' }}</span>
                          <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">Sort {{ entry.sort_order }}</span>
                          <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="entry.preview_visible ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200' : 'border-red-300/25 bg-red-300/10 text-red-200'">
                            {{ entry.preview_visible ? 'Visible in preview' : 'Hidden in preview' }}
                          </span>
                        </div>

                        <h4 class="mt-3 text-xl font-black text-horizon-white">{{ entry.title }}</h4>
                        <p class="mt-2 text-sm leading-6 text-text-secondary">{{ entry.excerpt || 'No excerpt yet.' }}</p>
                        <div class="mt-3 text-xs text-text-muted">/{{ group.label }}/{{ entry.slug }}</div>
                      </div>

                      <div class="flex flex-wrap gap-2 lg:flex-col">
                        <Link :href="entry.edit_admin_href" class="rounded-xl border border-[color:var(--horizon-sunset-magenta)]/35 bg-white/[0.042] px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-magenta)]/20">Edit</Link>
                        <Link :href="entry.public_href" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">View</Link>
                      </div>
                    </div>
                  </article>
                </div>

                <div v-else class="hz-surface-welcome mt-4 rounded-3xl border border-dashed border-white/10 p-5 text-sm text-text-secondary">
                  No direct entries yet. Use <span class="font-bold text-horizon-white">Create Direct Entry</span> to add one without making a topic first.
                </div>
              </div>

              <div v-if="group.topics.length" class="mt-5 space-y-4">
                <article v-for="topic in group.topics" :key="topic.id" class="hz-surface-welcome rounded-3xl border border-white/[0.055] p-5">
                  <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                    <div>
                      <div class="flex flex-wrap gap-2">
                        <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">{{ topic.minimum_rank_label }}</span>
                        <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold" :class="topic.is_published ? 'text-emerald-300' : 'text-red-300'">{{ topic.is_published ? 'Published' : 'Draft' }}</span>
                        <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">{{ topic.entries_count }} entries</span>
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="topic.preview_visible ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200' : 'border-red-300/25 bg-red-300/10 text-red-200'">
                          {{ topic.preview_visible ? 'Visible in preview' : 'Hidden in preview' }}
                        </span>
                      </div>

                      <h2 class="mt-3 text-2xl font-black text-horizon-white">{{ topic.title }}</h2>
                      <p class="mt-2 text-sm leading-6 text-text-secondary">{{ topic.description }}</p>
                      <div class="mt-3 text-xs text-text-muted">/{{ topic.slug }} · Sort {{ topic.sort_order }}</div>
                    </div>

                    <div class="flex flex-wrap gap-2 lg:flex-col">
                      <Link :href="topic.entries_admin_href" class="rounded-xl border border-[color:var(--horizon-sunset-blue)]/35 bg-white/[0.042] px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-blue)]/20">Manage Topic Entries</Link>
                      <Link :href="topic.public_href" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">View</Link>
                      <HorizonButton type="button" variant="ghost" size="sm" @click="startEdit(topic)">Edit</HorizonButton>
                      <HorizonButton type="button" variant="danger" size="sm" @click="askDeleteTopic(topic)">Delete</HorizonButton>
                    </div>
                  </div>
                </article>
              </div>

              <div v-else class="hz-surface-welcome mt-5 rounded-3xl border border-white/[0.055] p-6 text-sm text-text-secondary">
                No topics exist in this category yet. You can still use the direct entry actions above to add category-level documents right now.
              </div>
            </template>
          </section>
        </div>

        <div v-else class="hz-surface-welcome rounded-3xl border border-white/[0.055] p-6 text-sm text-text-secondary">
          No archive topics exist yet. Use New Topic to create the first card.
        </div>
      </section>

      <HorizonDrawer v-if="topicDrawerOpen" close-label="Close archive topic drawer" @close="closeTopicDrawer">
        <template #header>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">{{ drawerEyebrow }}</div>
          <h2 class="mt-1 text-2xl font-black text-horizon-white">{{ drawerTitle }}</h2>
          <p class="mt-1 text-sm text-text-secondary">{{ drawerDescription }}</p>
        </template>

        <form id="archive-topic-form" class="space-y-5" @submit.prevent="submitTopic">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <HorizonSelect v-model="topicForm.archive_category_id" label="Category" :options="categoryOptions" />
              <div v-if="topicForm.errors.archive_category_id" class="mt-1 text-xs text-red-300">{{ topicForm.errors.archive_category_id }}</div>
            </div>

            <div>
              <HorizonInput v-model="topicForm.title" label="Title" />
              <div v-if="topicForm.errors.title" class="mt-1 text-xs text-red-300">{{ topicForm.errors.title }}</div>
            </div>

            <div>
              <HorizonInput v-model="topicForm.slug" label="Slug" placeholder="auto-from-title if blank" />
              <div v-if="topicForm.errors.slug" class="mt-1 text-xs text-red-300">{{ topicForm.errors.slug }}</div>
            </div>
          </div>

          <div>
            <HorizonInput v-model="topicForm.description" label="Description" type="textarea" rows="4" />
            <div v-if="topicForm.errors.description" class="mt-1 text-xs text-red-300">{{ topicForm.errors.description }}</div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <HorizonInput v-model="topicForm.sort_order" label="Sort" type="number" min="0" />
            <HorizonSelect v-model="topicForm.minimum_rank_level" label="Minimum Rank" :options="rankOptions" />
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <ArchiveMediaPicker v-model="topicForm.card_image_path" label="Topic Card Image" />
            <ArchiveMediaPicker v-model="topicForm.banner_image_path" label="Topic Banner Image" />
          </div>

          <HorizonCheckbox
            v-model="topicForm.is_published"
            variant="toggle"
            label="Published"
          />
        </form>

        <template #footer>
          <div class="flex flex-wrap justify-end gap-2">
            <HorizonButton type="button" variant="ghost" @click="closeTopicDrawer">Cancel</HorizonButton>
            <HorizonButton type="submit" form="archive-topic-form" :disabled="topicForm.processing">
              {{ topicForm.processing ? 'Saving...' : isEditingTopic ? 'Save Topic' : 'Create Topic' }}
            </HorizonButton>
          </div>
        </template>
      </HorizonDrawer>

      <HorizonConfirmDialog
        ref="deleteTopicDialog"
        title="Delete Archive Topic"
        confirm-label="Delete Topic"
        cancel-label="Cancel"
        variant="danger"
        :close-on-confirm="false"
        :message="`Delete archive topic '${topicPendingDelete?.title ?? ''}'? This also moves its entries to trash.`"
        @confirm="confirmDeleteTopic"
      />
    </div>
  </HorizonContainer>
</template>






<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonCheckbox from '@/Components/HorizonCheckbox.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonDrawer from '@/Components/HorizonDrawer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonRichTextEditor from '@/Components/HorizonRichTextEditor.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import ArchiveMediaPicker from './Components/ArchiveMediaPicker.vue'

const props = defineProps({
  category: { type: Object, required: true },
  entries: { type: Array, default: () => [] },
  tagOptions: { type: Array, default: () => [] },
  rankOptions: { type: Array, default: () => [] },
  previewRankLevel: { type: [Number, null], default: null },
})

const entryDrawerOpen = ref(false)
const editingEntryId = ref(null)
const previewRank = ref(props.previewRankLevel)
const deleteEntryDialog = ref(null)
const entryPendingDelete = ref(null)
const page = usePage()

const blankEntry = {
  title: '',
  slug: '',
  excerpt: '',
  body: '',
  banner_image_path: '',
  sort_order: 0,
  minimum_rank_level: null,
  tag_ids: [],
  is_published: true,
}

const entryForm = useForm({ ...blankEntry, tag_ids: [] })

const sortedEntries = computed(() => props.entries ?? [])
const hasTags = computed(() => (props.tagOptions ?? []).length > 0)
const tagSelectOptions = computed(() => (props.tagOptions ?? []).map(tag => ({ value: tag.id, label: tag.name })))
const previewOptions = computed(() => (props.rankOptions ?? []).map(option => ({ ...option, label: option.value === null ? 'All verified members' : option.label })))
const previewLabel = computed(() => previewOptions.value.find(option => option.value === previewRank.value)?.label ?? 'All verified members')
const editingEntry = computed(() => sortedEntries.value.find(entry => entry.id === editingEntryId.value) ?? null)
const isEditingEntry = computed(() => Boolean(editingEntryId.value))
const drawerTitle = computed(() => isEditingEntry.value ? 'Edit Category Entry' : 'New Category Entry')
const drawerEyebrow = computed(() => isEditingEntry.value ? 'Category Entry Editor' : 'Category Entry Builder')
const drawerDescription = computed(() => isEditingEntry.value
  ? 'Update the direct category entry body, tags, publishing state, media, and rank visibility.'
  : `Create a new direct entry inside ${props.category.name}.`
)

function changePreviewRank() {
  router.get(route('admin.archive.categories.entries.index', props.category.id), {
    preview_rank_level: previewRank.value ?? '',
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

function hydrateEntryForm(entry = null) {
  const source = entry ?? blankEntry

  entryForm.title = source.title ?? ''
  entryForm.slug = source.slug ?? ''
  entryForm.excerpt = source.excerpt ?? ''
  entryForm.body = source.body ?? ''
  entryForm.banner_image_path = source.banner_image_path ?? ''
  entryForm.sort_order = source.sort_order ?? 0
  entryForm.minimum_rank_level = source.minimum_rank_level ?? null
  entryForm.tag_ids = [...(source.tag_ids ?? [])]
  entryForm.is_published = Boolean(source.is_published ?? true)
  entryForm.clearErrors()
}

function openCreateDrawer() {
  editingEntryId.value = null
  hydrateEntryForm()
  entryDrawerOpen.value = true
}

function startEdit(entry) {
  editingEntryId.value = entry.id
  hydrateEntryForm(entry)
  entryDrawerOpen.value = true
}

function closeEntryDrawer() {
  entryDrawerOpen.value = false
  editingEntryId.value = null
  entryForm.reset()
  entryForm.clearErrors()
}

function submitEntry() {
  if (isEditingEntry.value && editingEntry.value) {
    entryForm.put(route('admin.archive.categories.entries.update', [props.category.id, editingEntry.value.id]), {
      preserveScroll: true,
      onSuccess: () => closeEntryDrawer(),
    })
    return
  }

  entryForm.post(route('admin.archive.categories.entries.store', props.category.id), {
    preserveScroll: true,
    onSuccess: () => closeEntryDrawer(),
  })
}

function askDeleteEntry(entry) {
  entryPendingDelete.value = entry
  deleteEntryDialog.value?.show()
}

function confirmDeleteEntry({ close }) {
  if (!entryPendingDelete.value) return

  router.delete(route('admin.archive.categories.entries.destroy', [props.category.id, entryPendingDelete.value.id]), {
    preserveScroll: true,
    onFinish: () => {
      entryPendingDelete.value = null
      close()
    },
  })
}

function clearEntryIntentFromUrl(searchParams) {
  const nextUrl = new URL(window.location.href)

  nextUrl.searchParams.delete('create')
  nextUrl.searchParams.delete('entry')

  const previewRankLevel = searchParams.get('preview_rank_level')

  if (previewRankLevel !== null && previewRankLevel !== '') {
    nextUrl.searchParams.set('preview_rank_level', previewRankLevel)
  }

  window.history.replaceState({}, '', `${nextUrl.pathname}${nextUrl.search}${nextUrl.hash}`)
}

function handleEntryIntent(url) {
  const [, search = ''] = String(url ?? '').split('?')
  const searchParams = new URLSearchParams(search)
  const requestedEntryId = Number(searchParams.get('entry') ?? '')
  const requestedCreate = searchParams.get('create')

  if (Number.isInteger(requestedEntryId) && requestedEntryId > 0) {
    const entry = sortedEntries.value.find(candidate => candidate.id === requestedEntryId)

    if (entry) {
      startEdit(entry)
      clearEntryIntentFromUrl(searchParams)
      return
    }
  }

  if (requestedCreate === '1') {
    openCreateDrawer()
    clearEntryIntentFromUrl(searchParams)
  }
}

watch(() => page.url, handleEntryIntent, { immediate: true })
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <section class="hz-surface-welcome relative overflow-hidden rounded-[2rem] border border-white/[0.055] p-6 md:p-8">
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-48 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-64 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Category Entries</div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">{{ category.name }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Manage direct archive documents that live on the same level as topics inside this category.
            </p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
              <span class="rounded-full border border-white/10 px-3 py-1">/{{ category.slug }}</span>
              <span class="rounded-full border border-white/10 px-3 py-1">Direct category entries</span>
              <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-[color:var(--horizon-text-primary)]">
                {{ sortedEntries.length }} entr{{ sortedEntries.length === 1 ? 'y' : 'ies' }}
              </span>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <HorizonButton type="button" @click="openCreateDrawer">New Category Entry</HorizonButton>
            <Link :href="route('admin.archive.taxonomy.index')" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Back to Categories</Link>
            <Link :href="category.public_href" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45">View Category</Link>
            <Link :href="route('admin.dashboard')" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Admin Dashboard</Link>
          </div>
        </div>
      </section>

      <section class="hz-surface-welcome rounded-3xl border border-white/[0.055] p-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Rank Preview</div>
            <p class="mt-1 text-sm text-text-secondary">
              Currently previewing direct category entry visibility as <span class="font-bold text-horizon-white">{{ previewLabel }}</span>.
            </p>
          </div>

          <div class="w-full md:w-72">
            <HorizonSelect v-model="previewRank" :options="previewOptions" @update:model-value="changePreviewRank" />
          </div>
        </div>
      </section>

      <section class="space-y-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Direct Entries</div>
            <h2 class="text-2xl font-black text-horizon-white">{{ sortedEntries.length }} entr{{ sortedEntries.length === 1 ? 'y' : 'ies' }}</h2>
            <p class="mt-1 text-sm text-text-secondary">Category-level documents live beside topics, so this screen should feel like the same admin archive system instead of a separate tool.</p>
          </div>

          <HorizonButton type="button" variant="ghost" @click="openCreateDrawer">Create Category Entry</HorizonButton>
        </div>

        <div v-if="sortedEntries.length" class="space-y-4">
          <article v-for="entry in sortedEntries" :key="entry.id" class="hz-surface-welcome rounded-3xl border border-white/[0.055] p-5">
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

                <h2 class="mt-3 text-2xl font-black text-horizon-white">{{ entry.title }}</h2>
                <p class="mt-2 text-sm leading-6 text-text-secondary">{{ entry.excerpt || 'No excerpt yet.' }}</p>

                <div v-if="entry.tags?.length" class="mt-3 flex flex-wrap gap-2">
                  <span v-for="tag in entry.tags" :key="`tag-${tag.id}`" class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-magenta)]">#{{ tag.name }}</span>
                </div>

                <div class="mt-3 text-xs text-text-muted">/{{ category.slug }}/{{ entry.slug }}</div>
              </div>

              <div class="flex flex-wrap gap-2 lg:flex-col">
                <Link :href="entry.public_href" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">View</Link>
                <HorizonButton type="button" variant="ghost" size="sm" @click="startEdit(entry)">Edit</HorizonButton>
                <HorizonButton type="button" variant="danger" size="sm" @click="askDeleteEntry(entry)">Delete</HorizonButton>
              </div>
            </div>
          </article>
        </div>

        <div v-else class="hz-surface-welcome rounded-3xl border border-white/[0.055] p-6 text-sm text-text-secondary">
          No direct category entries exist yet. Use New Category Entry to create the first document.
        </div>
      </section>

      <HorizonDrawer v-if="entryDrawerOpen" close-label="Close category entry drawer" @close="closeEntryDrawer">
        <template #header>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">{{ drawerEyebrow }}</div>
          <h2 class="mt-1 text-2xl font-black text-horizon-white">{{ drawerTitle }}</h2>
          <p class="mt-1 text-sm text-text-secondary">{{ drawerDescription }}</p>
        </template>

        <form id="archive-category-entry-form" class="space-y-5" @submit.prevent="submitEntry">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <HorizonInput v-model="entryForm.title" label="Title" />
              <div v-if="entryForm.errors.title" class="mt-1 text-xs text-red-300">{{ entryForm.errors.title }}</div>
            </div>

            <div>
              <HorizonInput v-model="entryForm.slug" label="Slug" placeholder="auto-from-title if blank" />
              <div v-if="entryForm.errors.slug" class="mt-1 text-xs text-red-300">{{ entryForm.errors.slug }}</div>
            </div>
          </div>

          <div>
            <HorizonInput v-model="entryForm.excerpt" label="Excerpt" type="textarea" rows="3" />
            <div v-if="entryForm.errors.excerpt" class="mt-1 text-xs text-red-300">{{ entryForm.errors.excerpt }}</div>
          </div>

          <div>
            <label class="text-xs font-bold uppercase tracking-[0.16em] text-text-muted">Body</label>
            <HorizonRichTextEditor v-model="entryForm.body" placeholder="Write the category entry body..." :rows="16" />
            <div v-if="entryForm.errors.body" class="mt-1 text-xs text-red-300">{{ entryForm.errors.body }}</div>
          </div>

          <ArchiveMediaPicker v-model="entryForm.banner_image_path" label="Entry Banner Image" />

          <div class="grid gap-4 md:grid-cols-2">
            <div v-if="hasTags">
              <HorizonSelect v-model="entryForm.tag_ids" label="Tags" multiple :options="tagSelectOptions" />
              <p class="mt-1 text-xs text-text-muted">Click items to toggle them on or off.</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <HorizonInput v-model="entryForm.sort_order" label="Sort" type="number" min="0" />
            <HorizonSelect v-model="entryForm.minimum_rank_level" label="Minimum Rank" :options="rankOptions" />
          </div>

          <HorizonCheckbox
            v-model="entryForm.is_published"
            variant="toggle"
            label="Published"
          />
        </form>

        <template #footer>
          <div class="flex flex-wrap justify-end gap-2">
            <HorizonButton type="button" variant="ghost" @click="closeEntryDrawer">Cancel</HorizonButton>
            <HorizonButton type="submit" form="archive-category-entry-form" :disabled="entryForm.processing">
              {{ entryForm.processing ? 'Saving...' : isEditingEntry ? 'Save Entry' : 'Create Entry' }}
            </HorizonButton>
          </div>
        </template>
      </HorizonDrawer>

      <HorizonConfirmDialog
        ref="deleteEntryDialog"
        title="Delete Category Entry"
        confirm-label="Delete Entry"
        cancel-label="Cancel"
        variant="danger"
        :close-on-confirm="false"
        :message="`Delete category entry '${entryPendingDelete?.title ?? ''}'? It will move to Archive Trash.`"
        @confirm="confirmDeleteEntry"
      />
    </div>
  </HorizonContainer>
</template>

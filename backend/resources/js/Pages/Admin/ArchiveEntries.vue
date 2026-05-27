<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonRichTextEditor from '@/Components/HorizonRichTextEditor.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import OperationDrawer from '@/Pages/Operations/Components/OperationDrawer.vue'
import ArchiveMediaPicker from './Components/ArchiveMediaPicker.vue'

const props = defineProps({
  topic: { type: Object, required: true },
  entries: { type: Array, default: () => [] },
  categoryOptions: { type: Array, default: () => [] },
  tagOptions: { type: Array, default: () => [] },
  rankOptions: { type: Array, default: () => [] },
  previewRankLevel: { type: [Number, null], default: null },
})

const entryDrawerOpen = ref(false)
const editingEntryId = ref(null)
const previewRank = ref(props.previewRankLevel)
const deleteEntryDialog = ref(null)
const entryPendingDelete = ref(null)

const blankEntry = {
  title: '',
  slug: '',
  excerpt: '',
  body: '',
  banner_image_path: '',
  sort_order: 0,
  minimum_rank_level: null,
  category_ids: [],
  tag_ids: [],
  is_published: true,
}

const entryForm = useForm({ ...blankEntry, category_ids: [], tag_ids: [] })

const sortedEntries = computed(() => props.entries ?? [])
const hasCategories = computed(() => (props.categoryOptions ?? []).length > 0)
const hasTags = computed(() => (props.tagOptions ?? []).length > 0)
const categorySelectOptions = computed(() => (props.categoryOptions ?? []).map(category => ({ value: category.id, label: category.name })))
const tagSelectOptions = computed(() => (props.tagOptions ?? []).map(tag => ({ value: tag.id, label: tag.name })))
const previewOptions = computed(() => (props.rankOptions ?? []).map(option => ({ ...option, label: option.value === null ? 'All verified members' : option.label })))
const previewLabel = computed(() => previewOptions.value.find(option => option.value === previewRank.value)?.label ?? 'All verified members')
const editingEntry = computed(() => sortedEntries.value.find(entry => entry.id === editingEntryId.value) ?? null)
const isEditingEntry = computed(() => Boolean(editingEntryId.value))
const drawerTitle = computed(() => isEditingEntry.value ? 'Edit Archive Entry' : 'New Archive Entry')
const drawerEyebrow = computed(() => isEditingEntry.value ? 'Entry Editor' : 'Entry Builder')
const drawerDescription = computed(() => isEditingEntry.value
  ? 'Update the entry body, taxonomy, publishing state, media, and rank visibility.'
  : `Create a new document inside ${props.topic.title}.`
)

function changePreviewRank() {
  router.get(route('admin.archive.topics.entries.index', props.topic.id), {
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
  entryForm.category_ids = [...(source.category_ids ?? [])]
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
    entryForm.put(route('admin.archive.topics.entries.update', [props.topic.id, editingEntry.value.id]), {
      preserveScroll: true,
      onSuccess: () => closeEntryDrawer(),
    })
    return
  }

  entryForm.post(route('admin.archive.topics.entries.store', props.topic.id), {
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

  router.delete(route('admin.archive.topics.entries.destroy', [props.topic.id, entryPendingDelete.value.id]), {
    preserveScroll: true,
    onFinish: () => {
      entryPendingDelete.value = null
      close()
    },
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <section class="rounded-[2rem] border border-white/10 bg-white/[0.035] p-6 md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Archive Entries</div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">{{ topic.title }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Manage the visible documents inside this archive topic. Draft and rank-gated entries stay hidden from members until published and permitted.
            </p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-text-muted">
              <span class="rounded-full border border-white/10 px-3 py-1">{{ topic.minimum_rank_label }}</span>
              <span class="rounded-full border border-white/10 px-3 py-1">{{ topic.is_published ? 'Topic published' : 'Topic draft' }}</span>
              <span class="rounded-full border px-3 py-1" :class="topic.preview_visible ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-200' : 'border-red-300/25 bg-red-300/10 text-red-200'">
                {{ topic.preview_visible ? 'Topic visible in preview' : 'Topic hidden in preview' }}
              </span>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <HorizonButton type="button" @click="openCreateDrawer">New Entry</HorizonButton>
            <Link :href="route('admin.archive.index')" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Back to Topics</Link>
            <Link :href="topic.public_href" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45">View Topic</Link>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Rank Preview</div>
            <p class="mt-1 text-sm text-text-secondary">
              Currently previewing entry visibility as <span class="font-bold text-horizon-white">{{ previewLabel }}</span>. Entries also inherit the topic's visibility gate.
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
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Entries</div>
            <h2 class="text-2xl font-black text-horizon-white">{{ sortedEntries.length }} entr{{ sortedEntries.length === 1 ? 'y' : 'ies' }}</h2>
          </div>

          <HorizonButton type="button" variant="ghost" @click="openCreateDrawer">Create Entry</HorizonButton>
        </div>

        <div v-if="sortedEntries.length" class="space-y-4">
          <article v-for="entry in sortedEntries" :key="entry.id" class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
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

                <div v-if="entry.categories?.length || entry.tags?.length" class="mt-3 flex flex-wrap gap-2">
                  <span v-for="category in entry.categories" :key="`category-${category.id}`" class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-blue)]">{{ category.name }}</span>
                  <span v-for="tag in entry.tags" :key="`tag-${tag.id}`" class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-magenta)]">#{{ tag.name }}</span>
                </div>

                <div class="mt-3 text-xs text-text-muted">/{{ topic.slug }}/{{ entry.slug }}</div>
              </div>

              <div class="flex flex-wrap gap-2 lg:flex-col">
                <Link :href="entry.public_href" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">View</Link>
                <HorizonButton type="button" variant="ghost" size="sm" @click="startEdit(entry)">Edit</HorizonButton>
                <HorizonButton type="button" variant="danger" size="sm" @click="askDeleteEntry(entry)">Delete</HorizonButton>
              </div>
            </div>
          </article>
        </div>

        <div v-else class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">
          No archive entries exist for this topic yet. Use New Entry to create the first document.
        </div>
      </section>

      <OperationDrawer v-if="entryDrawerOpen" @close="closeEntryDrawer">
        <template #header>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">{{ drawerEyebrow }}</div>
          <h2 class="mt-1 text-2xl font-black text-horizon-white">{{ drawerTitle }}</h2>
          <p class="mt-1 text-sm text-text-secondary">{{ drawerDescription }}</p>
        </template>

        <form id="archive-entry-form" class="space-y-5" @submit.prevent="submitEntry">
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
            <HorizonRichTextEditor v-model="entryForm.body" placeholder="Write the archive entry body..." :rows="16" />
            <div v-if="entryForm.errors.body" class="mt-1 text-xs text-red-300">{{ entryForm.errors.body }}</div>
          </div>

          <ArchiveMediaPicker v-model="entryForm.banner_image_path" label="Entry Banner Image" />

          <div class="grid gap-4 md:grid-cols-2">
            <div v-if="hasCategories">
              <HorizonSelect v-model="entryForm.category_ids" label="Categories" multiple :options="categorySelectOptions" />
              <p class="mt-1 text-xs text-text-muted">Click items to toggle them on or off.</p>
            </div>

            <div v-if="hasTags">
              <HorizonSelect v-model="entryForm.tag_ids" label="Tags" multiple :options="tagSelectOptions" />
              <p class="mt-1 text-xs text-text-muted">Click items to toggle them on or off.</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <HorizonInput v-model="entryForm.sort_order" label="Sort" type="number" min="0" />
            <HorizonSelect v-model="entryForm.minimum_rank_level" label="Minimum Rank" :options="rankOptions" />
          </div>

          <label class="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[0.035] p-4 text-sm font-semibold text-text-secondary">
            <input v-model="entryForm.is_published" type="checkbox" /> Published
          </label>
        </form>

        <template #footer>
          <div class="flex flex-wrap justify-end gap-2">
            <HorizonButton type="button" variant="ghost" @click="closeEntryDrawer">Cancel</HorizonButton>
            <HorizonButton type="submit" form="archive-entry-form" :disabled="entryForm.processing">
              {{ entryForm.processing ? 'Saving...' : isEditingEntry ? 'Save Entry' : 'Create Entry' }}
            </HorizonButton>
          </div>
        </template>
      </OperationDrawer>

      <HorizonConfirmDialog
        ref="deleteEntryDialog"
        title="Delete Archive Entry"
        confirm-label="Delete Entry"
        cancel-label="Cancel"
        variant="danger"
        :close-on-confirm="false"
        :message="`Delete archive entry '${entryPendingDelete?.title ?? ''}'? It will move to Archive Trash.`"
        @confirm="confirmDeleteEntry"
      />
    </div>
  </HorizonContainer>
</template>

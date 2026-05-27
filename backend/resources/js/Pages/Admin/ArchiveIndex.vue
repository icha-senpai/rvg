<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'
import OperationDrawer from '@/Pages/Operations/Components/OperationDrawer.vue'
import ArchiveMediaPicker from './Components/ArchiveMediaPicker.vue'

const props = defineProps({
  topics: { type: Array, default: () => [] },
  rankOptions: { type: Array, default: () => [] },
  previewRankLevel: { type: [Number, null], default: null },
})

const topicDrawerOpen = ref(false)
const editingTopicId = ref(null)
const previewRank = ref(props.previewRankLevel)
const deleteTopicDialog = ref(null)
const topicPendingDelete = ref(null)

const blankTopic = {
  title: '',
  slug: '',
  description: '',
  category_label: '',
  card_image_path: '',
  banner_image_path: '',
  sort_order: 0,
  minimum_rank_level: null,
  is_published: true,
}

const topicForm = useForm({ ...blankTopic })

const sortedTopics = computed(() => props.topics ?? [])
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

function changePreviewRank() {
  router.get(route('admin.archive.index'), {
    preview_rank_level: previewRank.value ?? '',
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

function hydrateTopicForm(topic = null) {
  const source = topic ?? blankTopic

  topicForm.title = source.title ?? ''
  topicForm.slug = source.slug ?? ''
  topicForm.description = source.description ?? ''
  topicForm.category_label = source.category_label ?? ''
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
      <section class="rounded-[2rem] border border-white/10 bg-white/[0.035] p-6 md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Director Tools</div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">Archive Management</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Create and manage Archive topic cards. Use Manage Entries to add the actual documents inside each topic, or manage Categories & Tags to organize entries across the library.
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <HorizonButton type="button" @click="openCreateDrawer">New Topic</HorizonButton>
            <Link :href="route('admin.archive.taxonomy.index')" class="rounded-xl border border-[color:var(--horizon-sunset-magenta)]/35 bg-[color:var(--horizon-sunset-magenta)]/10 px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-magenta)]/20">Manage Categories & Tags</Link>
            <Link :href="route('admin.archive.trash.index')" class="rounded-xl border border-red-300/25 bg-red-300/10 px-4 py-2 text-sm font-bold text-red-100 hover:bg-red-300/15">Trash</Link>
            <Link :href="route('admin.archive.audit.index')" class="rounded-xl border border-[color:var(--horizon-sunset-blue)]/35 bg-[color:var(--horizon-sunset-blue)]/10 px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-blue)]/20">Audit Log</Link>
            <Link :href="route('archive.index')" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45">View Archive</Link>
            <Link :href="route('admin.dashboard')" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Admin Dashboard</Link>
          </div>
        </div>
      </section>

      <section class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
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
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Topics</div>
            <h2 class="text-2xl font-black text-horizon-white">{{ sortedTopics.length }} topic{{ sortedTopics.length === 1 ? '' : 's' }}</h2>
          </div>

          <HorizonButton type="button" variant="ghost" @click="openCreateDrawer">Create Topic</HorizonButton>
        </div>

        <div v-if="sortedTopics.length" class="space-y-4">
          <article v-for="topic in sortedTopics" :key="topic.id" class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
              <div>
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">{{ topic.category_label || 'Archive' }}</span>
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
                <Link :href="topic.entries_admin_href" class="rounded-xl border border-[color:var(--horizon-sunset-blue)]/35 bg-[color:var(--horizon-sunset-blue)]/10 px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-blue)]/20">Manage Entries</Link>
                <Link :href="topic.public_href" class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">View</Link>
                <HorizonButton type="button" variant="ghost" size="sm" @click="startEdit(topic)">Edit</HorizonButton>
                <HorizonButton type="button" variant="danger" size="sm" @click="askDeleteTopic(topic)">Delete</HorizonButton>
              </div>
            </div>
          </article>
        </div>

        <div v-else class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">
          No archive topics exist yet. Use New Topic to create the first card.
        </div>
      </section>

      <OperationDrawer v-if="topicDrawerOpen" @close="closeTopicDrawer">
        <template #header>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">{{ drawerEyebrow }}</div>
          <h2 class="mt-1 text-2xl font-black text-horizon-white">{{ drawerTitle }}</h2>
          <p class="mt-1 text-sm text-text-secondary">{{ drawerDescription }}</p>
        </template>

        <form id="archive-topic-form" class="space-y-5" @submit.prevent="submitTopic">
          <div class="grid gap-4 md:grid-cols-2">
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

          <div class="grid gap-4 md:grid-cols-3">
            <HorizonInput v-model="topicForm.category_label" label="Category Label" />
            <HorizonInput v-model="topicForm.sort_order" label="Sort" type="number" min="0" />
            <HorizonSelect v-model="topicForm.minimum_rank_level" label="Minimum Rank" :options="rankOptions" />
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <ArchiveMediaPicker v-model="topicForm.card_image_path" label="Topic Card Image" />
            <ArchiveMediaPicker v-model="topicForm.banner_image_path" label="Topic Banner Image" />
          </div>

          <label class="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[0.035] p-4 text-sm font-semibold text-text-secondary">
            <input v-model="topicForm.is_published" type="checkbox" /> Published
          </label>
        </form>

        <template #footer>
          <div class="flex flex-wrap justify-end gap-2">
            <HorizonButton type="button" variant="ghost" @click="closeTopicDrawer">Cancel</HorizonButton>
            <HorizonButton type="submit" form="archive-topic-form" :disabled="topicForm.processing">
              {{ topicForm.processing ? 'Saving...' : isEditingTopic ? 'Save Topic' : 'Create Topic' }}
            </HorizonButton>
          </div>
        </template>
      </OperationDrawer>

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

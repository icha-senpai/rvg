<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
})

const editingCategoryId = ref(null)
const editingTagId = ref(null)

const categoryCreateForm = useForm({ name: '', slug: '', description: '', sort_order: 0 })
const categoryEditForm = useForm({ name: '', slug: '', description: '', sort_order: 0 })
const tagCreateForm = useForm({ name: '', slug: '' })
const tagEditForm = useForm({ name: '', slug: '' })

function startCategoryEdit(category) {
  editingCategoryId.value = category.id
  categoryEditForm.name = category.name ?? ''
  categoryEditForm.slug = category.slug ?? ''
  categoryEditForm.description = category.description ?? ''
  categoryEditForm.sort_order = category.sort_order ?? 0
}

function cancelCategoryEdit() {
  editingCategoryId.value = null
  categoryEditForm.reset()
  categoryEditForm.clearErrors()
}

function submitCategoryCreate() {
  categoryCreateForm.post(route('admin.archive.taxonomy.categories.store'), {
    preserveScroll: true,
    onSuccess: () => categoryCreateForm.reset(),
  })
}

function submitCategoryEdit(category) {
  categoryEditForm.put(route('admin.archive.taxonomy.categories.update', category.id), {
    preserveScroll: true,
    onSuccess: () => cancelCategoryEdit(),
  })
}

function deleteCategory(category) {
  if (!window.confirm(`Delete archive category "${category.name}"? Entries will keep existing content, but lose this category label.`)) return

  router.delete(route('admin.archive.taxonomy.categories.destroy', category.id), {
    preserveScroll: true,
  })
}

function startTagEdit(tag) {
  editingTagId.value = tag.id
  tagEditForm.name = tag.name ?? ''
  tagEditForm.slug = tag.slug ?? ''
}

function cancelTagEdit() {
  editingTagId.value = null
  tagEditForm.reset()
  tagEditForm.clearErrors()
}

function submitTagCreate() {
  tagCreateForm.post(route('admin.archive.taxonomy.tags.store'), {
    preserveScroll: true,
    onSuccess: () => tagCreateForm.reset(),
  })
}

function submitTagEdit(tag) {
  tagEditForm.put(route('admin.archive.taxonomy.tags.update', tag.id), {
    preserveScroll: true,
    onSuccess: () => cancelTagEdit(),
  })
}

function deleteTag(tag) {
  if (!window.confirm(`Delete archive tag "${tag.name}"? Entries will keep existing content, but lose this tag label.`)) return

  router.delete(route('admin.archive.taxonomy.tags.destroy', tag.id), {
    preserveScroll: true,
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <section class="rounded-[2rem] border border-white/10 bg-white/[0.035] p-6 md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Archive Taxonomy</div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">Categories & Tags</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Manage the labels that organize Archive entries. Categories are broader buckets. Tags are smaller searchable signals attached to individual entries.
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <Link :href="route('admin.archive.index')" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Back to Archive Admin</Link>
            <Link :href="route('admin.archive.audit.index')" class="rounded-xl border border-[color:var(--horizon-sunset-blue)]/35 bg-[color:var(--horizon-sunset-blue)]/10 px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-blue)]/20">Audit Log</Link>
            <Link :href="route('archive.index')" class="rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45">View Archive</Link>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-2">
        <div class="space-y-4">
          <form class="rounded-3xl border border-white/10 bg-white/[0.035] p-5" @submit.prevent="submitCategoryCreate">
            <div class="text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--horizon-sunset-blue)]">New Category</div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <HorizonInput v-model="categoryCreateForm.name" label="Name" />
                <div v-if="categoryCreateForm.errors.name" class="mt-1 text-xs text-red-300">{{ categoryCreateForm.errors.name }}</div>
              </div>

              <div>
                <HorizonInput v-model="categoryCreateForm.slug" label="Slug" placeholder="auto-from-name if blank" />
                <div v-if="categoryCreateForm.errors.slug" class="mt-1 text-xs text-red-300">{{ categoryCreateForm.errors.slug }}</div>
              </div>
            </div>

            <div class="mt-4">
              <HorizonInput v-model="categoryCreateForm.description" label="Description" type="textarea" rows="3" />
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
              <HorizonInput v-model="categoryCreateForm.sort_order" label="Sort Order" type="number" min="0" />
              <HorizonButton type="submit" :disabled="categoryCreateForm.processing">Create Category</HorizonButton>
            </div>
          </form>

          <article v-for="category in categories" :key="category.id" class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
            <div v-if="editingCategoryId !== category.id" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]">
              <div>
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-blue)]">{{ category.entries_count }} entries</span>
                  <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">Sort {{ category.sort_order }}</span>
                </div>

                <h2 class="mt-3 text-2xl font-black text-horizon-white">{{ category.name }}</h2>
                <p class="mt-2 text-sm leading-6 text-text-secondary">{{ category.description || 'No description yet.' }}</p>
                <div class="mt-3 text-xs text-text-muted">/{{ category.slug }}</div>
              </div>

              <div class="flex flex-wrap gap-2 md:flex-col">
                <HorizonButton type="button" variant="ghost" size="sm" @click="startCategoryEdit(category)">Edit</HorizonButton>
                <HorizonButton type="button" variant="danger" size="sm" @click="deleteCategory(category)">Delete</HorizonButton>
              </div>
            </div>

            <form v-else class="space-y-4" @submit.prevent="submitCategoryEdit(category)">
              <div class="grid gap-4 md:grid-cols-2">
                <HorizonInput v-model="categoryEditForm.name" label="Name" />
                <HorizonInput v-model="categoryEditForm.slug" label="Slug" />
              </div>

              <HorizonInput v-model="categoryEditForm.description" label="Description" type="textarea" rows="3" />
              <HorizonInput v-model="categoryEditForm.sort_order" label="Sort Order" type="number" min="0" />

              <div class="flex flex-wrap gap-2">
                <HorizonButton type="submit">Save</HorizonButton>
                <HorizonButton type="button" variant="ghost" @click="cancelCategoryEdit">Cancel</HorizonButton>
              </div>
            </form>
          </article>

          <div v-if="!categories.length" class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">No categories exist yet.</div>
        </div>

        <div class="space-y-4">
          <form class="rounded-3xl border border-white/10 bg-white/[0.035] p-5" @submit.prevent="submitTagCreate">
            <div class="text-xs font-bold uppercase tracking-[0.22em] text-[color:var(--horizon-sunset-magenta)]">New Tag</div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <HorizonInput v-model="tagCreateForm.name" label="Name" />
                <div v-if="tagCreateForm.errors.name" class="mt-1 text-xs text-red-300">{{ tagCreateForm.errors.name }}</div>
              </div>

              <div>
                <HorizonInput v-model="tagCreateForm.slug" label="Slug" placeholder="auto-from-name if blank" />
                <div v-if="tagCreateForm.errors.slug" class="mt-1 text-xs text-red-300">{{ tagCreateForm.errors.slug }}</div>
              </div>
            </div>

            <HorizonButton type="submit" class="mt-4 w-full" :disabled="tagCreateForm.processing">Create Tag</HorizonButton>
          </form>

          <article v-for="tag in tags" :key="tag.id" class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
            <div v-if="editingTagId !== tag.id" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]">
              <div>
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full border border-[color:var(--horizon-sunset-magenta)]/25 bg-[color:var(--horizon-sunset-magenta)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-magenta)]">{{ tag.entries_count }} entries</span>
                </div>

                <h2 class="mt-3 text-2xl font-black text-horizon-white">#{{ tag.name }}</h2>
                <div class="mt-3 text-xs text-text-muted">/{{ tag.slug }}</div>
              </div>

              <div class="flex flex-wrap gap-2 md:flex-col">
                <HorizonButton type="button" variant="ghost" size="sm" @click="startTagEdit(tag)">Edit</HorizonButton>
                <HorizonButton type="button" variant="danger" size="sm" @click="deleteTag(tag)">Delete</HorizonButton>
              </div>
            </div>

            <form v-else class="space-y-4" @submit.prevent="submitTagEdit(tag)">
              <div class="grid gap-4 md:grid-cols-2">
                <HorizonInput v-model="tagEditForm.name" label="Name" />
                <HorizonInput v-model="tagEditForm.slug" label="Slug" />
              </div>

              <div class="flex flex-wrap gap-2">
                <HorizonButton type="submit">Save</HorizonButton>
                <HorizonButton type="button" variant="ghost" @click="cancelTagEdit">Cancel</HorizonButton>
              </div>
            </form>
          </article>

          <div v-if="!tags.length" class="rounded-3xl border border-white/10 bg-white/[0.035] p-6 text-sm text-text-secondary">No tags exist yet.</div>
        </div>
      </section>
    </div>
  </HorizonContainer>
</template>

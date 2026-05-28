<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonDrawer from '@/Components/HorizonDrawer.vue'
import HorizonInput from '@/Components/HorizonInput.vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
})

const categoryDrawerOpen = ref(false)
const tagDrawerOpen = ref(false)
const editingCategoryId = ref(null)
const editingTagId = ref(null)
const deleteCategoryDialog = ref(null)
const deleteTagDialog = ref(null)
const categoryPendingDelete = ref(null)
const tagPendingDelete = ref(null)

const categoryForm = useForm({ name: '', slug: '', description: '', sort_order: 0 })
const tagForm = useForm({ name: '', slug: '' })

const sortedCategories = computed(() => props.categories ?? [])
const sortedTags = computed(() => props.tags ?? [])
const editingCategory = computed(() => sortedCategories.value.find(category => category.id === editingCategoryId.value) ?? null)
const editingTag = computed(() => sortedTags.value.find(tag => tag.id === editingTagId.value) ?? null)
const isEditingCategory = computed(() => Boolean(editingCategoryId.value))
const isEditingTag = computed(() => Boolean(editingTagId.value))
const categoryDrawerTitle = computed(() => isEditingCategory.value ? 'Edit Archive Category' : 'New Archive Category')
const tagDrawerTitle = computed(() => isEditingTag.value ? 'Edit Archive Tag' : 'New Archive Tag')

function hydrateCategoryForm(category = null) {
  categoryForm.name = category?.name ?? ''
  categoryForm.slug = category?.slug ?? ''
  categoryForm.description = category?.description ?? ''
  categoryForm.sort_order = category?.sort_order ?? 0
  categoryForm.clearErrors()
}

function hydrateTagForm(tag = null) {
  tagForm.name = tag?.name ?? ''
  tagForm.slug = tag?.slug ?? ''
  tagForm.clearErrors()
}

function openCategoryCreateDrawer() {
  editingCategoryId.value = null
  hydrateCategoryForm()
  categoryDrawerOpen.value = true
}

function openTagCreateDrawer() {
  editingTagId.value = null
  hydrateTagForm()
  tagDrawerOpen.value = true
}

function startCategoryEdit(category) {
  editingCategoryId.value = category.id
  hydrateCategoryForm(category)
  categoryDrawerOpen.value = true
}

function startTagEdit(tag) {
  editingTagId.value = tag.id
  hydrateTagForm(tag)
  tagDrawerOpen.value = true
}

function closeCategoryDrawer() {
  categoryDrawerOpen.value = false
  editingCategoryId.value = null
  categoryForm.reset()
  categoryForm.clearErrors()
}

function closeTagDrawer() {
  tagDrawerOpen.value = false
  editingTagId.value = null
  tagForm.reset()
  tagForm.clearErrors()
}

function submitCategory() {
  if (isEditingCategory.value && editingCategory.value) {
    categoryForm.put(route('admin.archive.taxonomy.categories.update', editingCategory.value.id), {
      preserveScroll: true,
      onSuccess: () => closeCategoryDrawer(),
    })
    return
  }

  categoryForm.post(route('admin.archive.taxonomy.categories.store'), {
    preserveScroll: true,
    onSuccess: () => closeCategoryDrawer(),
  })
}

function submitTag() {
  if (isEditingTag.value && editingTag.value) {
    tagForm.put(route('admin.archive.taxonomy.tags.update', editingTag.value.id), {
      preserveScroll: true,
      onSuccess: () => closeTagDrawer(),
    })
    return
  }

  tagForm.post(route('admin.archive.taxonomy.tags.store'), {
    preserveScroll: true,
    onSuccess: () => closeTagDrawer(),
  })
}

function askDeleteCategory(category) {
  categoryPendingDelete.value = category
  deleteCategoryDialog.value?.show()
}

function confirmDeleteCategory({ close }) {
  if (!categoryPendingDelete.value) return

  router.delete(route('admin.archive.taxonomy.categories.destroy', categoryPendingDelete.value.id), {
    preserveScroll: true,
    onFinish: () => {
      categoryPendingDelete.value = null
      close()
    },
  })
}

function askDeleteTag(tag) {
  tagPendingDelete.value = tag
  deleteTagDialog.value?.show()
}

function confirmDeleteTag({ close }) {
  if (!tagPendingDelete.value) return

  router.delete(route('admin.archive.taxonomy.tags.destroy', tagPendingDelete.value.id), {
    preserveScroll: true,
    onFinish: () => {
      tagPendingDelete.value = null
      close()
    },
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <section class="rounded-[2rem] border border-white/[0.055] bg-white/[0.024] p-6 md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Archive Taxonomy</div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">Categories & Tags</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Manage the labels that organize Archive entries. Categories are broader buckets. Tags are smaller searchable signals attached to individual entries.
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <HorizonButton type="button" @click="openCategoryCreateDrawer">New Category</HorizonButton>
            <HorizonButton type="button" variant="ghost" @click="openTagCreateDrawer">New Tag</HorizonButton>
            <Link :href="route('admin.archive.index')" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Back to Archive Admin</Link>
            <Link :href="route('admin.archive.audit.index')" class="rounded-xl border border-[color:var(--horizon-sunset-blue)]/35 bg-white/[0.042] px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-blue)]/20">Audit Log</Link>
            <Link :href="route('archive.index')" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-bold text-horizon-white hover:border-[color:var(--horizon-sunset-blue)]/45">View Archive</Link>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-2">
        <div class="space-y-4">
          <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Categories</div>
              <h2 class="text-2xl font-black text-horizon-white">{{ sortedCategories.length }} categor{{ sortedCategories.length === 1 ? 'y' : 'ies' }}</h2>
            </div>

            <HorizonButton type="button" variant="ghost" @click="openCategoryCreateDrawer">Create Category</HorizonButton>
          </div>

          <article v-for="category in sortedCategories" :key="category.id" class="rounded-3xl border border-white/[0.055] bg-white/[0.024] p-5">
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]">
              <div>
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-blue)]">{{ category.entries_count }} entries</span>
                  <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">Sort {{ category.sort_order }}</span>
                </div>

                <h2 class="mt-3 text-2xl font-black text-horizon-white">{{ category.name }}</h2>
                <p class="mt-2 text-sm leading-6 text-text-secondary">{{ category.description || 'No description yet.' }}</p>
                <div class="mt-3 text-xs text-text-muted">/{{ category.slug }}</div>
              </div>

              <div class="flex flex-wrap gap-2 md:flex-col">
                <HorizonButton type="button" variant="ghost" size="sm" @click="startCategoryEdit(category)">Edit</HorizonButton>
                <HorizonButton type="button" variant="danger" size="sm" @click="askDeleteCategory(category)">Delete</HorizonButton>
              </div>
            </div>
          </article>

          <div v-if="!sortedCategories.length" class="rounded-3xl border border-white/[0.055] bg-white/[0.024] p-6 text-sm text-text-secondary">
            No categories exist yet. Use New Category to create the first broad bucket.
          </div>
        </div>

        <div class="space-y-4">
          <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
              <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Tags</div>
              <h2 class="text-2xl font-black text-horizon-white">{{ sortedTags.length }} tag{{ sortedTags.length === 1 ? '' : 's' }}</h2>
            </div>

            <HorizonButton type="button" variant="ghost" @click="openTagCreateDrawer">Create Tag</HorizonButton>
          </div>

          <article v-for="tag in sortedTags" :key="tag.id" class="rounded-3xl border border-white/[0.055] bg-white/[0.024] p-5">
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]">
              <div>
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-semibold text-[color:var(--horizon-sunset-magenta)]">{{ tag.entries_count }} entries</span>
                </div>

                <h2 class="mt-3 text-2xl font-black text-horizon-white">#{{ tag.name }}</h2>
                <div class="mt-3 text-xs text-text-muted">/{{ tag.slug }}</div>
              </div>

              <div class="flex flex-wrap gap-2 md:flex-col">
                <HorizonButton type="button" variant="ghost" size="sm" @click="startTagEdit(tag)">Edit</HorizonButton>
                <HorizonButton type="button" variant="danger" size="sm" @click="askDeleteTag(tag)">Delete</HorizonButton>
              </div>
            </div>
          </article>

          <div v-if="!sortedTags.length" class="rounded-3xl border border-white/[0.055] bg-white/[0.024] p-6 text-sm text-text-secondary">
            No tags exist yet. Use New Tag to create the first searchable signal.
          </div>
        </div>
      </section>

      <HorizonDrawer v-if="categoryDrawerOpen" close-label="Close archive category drawer" @close="closeCategoryDrawer">
        <template #header>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Category Editor</div>
          <h2 class="mt-1 text-2xl font-black text-horizon-white">{{ categoryDrawerTitle }}</h2>
          <p class="mt-1 text-sm text-text-secondary">Categories are broad buckets that help entries group into readable sections.</p>
        </template>

        <form id="archive-category-form" class="space-y-5" @submit.prevent="submitCategory">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <HorizonInput v-model="categoryForm.name" label="Name" />
              <div v-if="categoryForm.errors.name" class="mt-1 text-xs text-red-300">{{ categoryForm.errors.name }}</div>
            </div>

            <div>
              <HorizonInput v-model="categoryForm.slug" label="Slug" placeholder="auto-from-name if blank" />
              <div v-if="categoryForm.errors.slug" class="mt-1 text-xs text-red-300">{{ categoryForm.errors.slug }}</div>
            </div>
          </div>

          <div>
            <HorizonInput v-model="categoryForm.description" label="Description" type="textarea" rows="3" />
            <div v-if="categoryForm.errors.description" class="mt-1 text-xs text-red-300">{{ categoryForm.errors.description }}</div>
          </div>

          <HorizonInput v-model="categoryForm.sort_order" label="Sort Order" type="number" min="0" />
        </form>

        <template #footer>
          <div class="flex flex-wrap justify-end gap-2">
            <HorizonButton type="button" variant="ghost" @click="closeCategoryDrawer">Cancel</HorizonButton>
            <HorizonButton type="submit" form="archive-category-form" :disabled="categoryForm.processing">
              {{ categoryForm.processing ? 'Saving...' : isEditingCategory ? 'Save Category' : 'Create Category' }}
            </HorizonButton>
          </div>
        </template>
      </HorizonDrawer>

      <HorizonDrawer v-if="tagDrawerOpen" close-label="Close archive tag drawer" @close="closeTagDrawer">
        <template #header>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Tag Editor</div>
          <h2 class="mt-1 text-2xl font-black text-horizon-white">{{ tagDrawerTitle }}</h2>
          <p class="mt-1 text-sm text-text-secondary">Tags are small searchable signals attached to Archive entries.</p>
        </template>

        <form id="archive-tag-form" class="space-y-5" @submit.prevent="submitTag">
          <div>
            <HorizonInput v-model="tagForm.name" label="Name" />
            <div v-if="tagForm.errors.name" class="mt-1 text-xs text-red-300">{{ tagForm.errors.name }}</div>
          </div>

          <div>
            <HorizonInput v-model="tagForm.slug" label="Slug" placeholder="auto-from-name if blank" />
            <div v-if="tagForm.errors.slug" class="mt-1 text-xs text-red-300">{{ tagForm.errors.slug }}</div>
          </div>
        </form>

        <template #footer>
          <div class="flex flex-wrap justify-end gap-2">
            <HorizonButton type="button" variant="ghost" @click="closeTagDrawer">Cancel</HorizonButton>
            <HorizonButton type="submit" form="archive-tag-form" :disabled="tagForm.processing">
              {{ tagForm.processing ? 'Saving...' : isEditingTag ? 'Save Tag' : 'Create Tag' }}
            </HorizonButton>
          </div>
        </template>
      </HorizonDrawer>

      <HorizonConfirmDialog
        ref="deleteCategoryDialog"
        title="Delete Archive Category"
        confirm-label="Delete Category"
        cancel-label="Cancel"
        variant="danger"
        :close-on-confirm="false"
        :message="`Delete archive category '${categoryPendingDelete?.name ?? ''}'? Entries will keep their content but lose this category label.`"
        @confirm="confirmDeleteCategory"
      />

      <HorizonConfirmDialog
        ref="deleteTagDialog"
        title="Delete Archive Tag"
        confirm-label="Delete Tag"
        cancel-label="Cancel"
        variant="danger"
        :close-on-confirm="false"
        :message="`Delete archive tag '${tagPendingDelete?.name ?? ''}'? Entries will keep their content but lose this tag label.`"
        @confirm="confirmDeleteTag"
      />
    </div>
  </HorizonContainer>
</template>









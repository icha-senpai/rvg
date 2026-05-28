<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'

const props = defineProps({
  topics: { type: Array, default: () => [] },
  entries: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
})

const restoreDialog = ref(null)
const forceDeleteDialog = ref(null)
const pendingItem = ref(null)
const pendingAction = ref(null)

const groups = computed(() => [
  {
    key: 'topics',
    title: 'Deleted Topics',
    items: props.topics,
    empty: 'No deleted archive topics.',
    hint: 'Deleted topics appear here with their bundled entries attached.',
  },
  {
    key: 'entries',
    title: 'Deleted Entries',
    items: props.entries,
    empty: 'No standalone deleted archive entries.',
    hint: 'Entries deleted as part of a topic stay bundled under the topic instead of appearing twice.',
  },
  {
    key: 'categories',
    title: 'Deleted Categories',
    items: props.categories,
    empty: 'No deleted archive categories.',
    hint: 'Restoring a category makes it selectable again for Archive entries.',
  },
  {
    key: 'tags',
    title: 'Deleted Tags',
    items: props.tags,
    empty: 'No deleted archive tags.',
    hint: 'Restoring a tag makes it selectable again for Archive entries.',
  },
])

const totalTrashCount = computed(() => props.topics.length + props.entries.length + props.categories.length + props.tags.length)
const trashIsEmpty = computed(() => totalTrashCount.value === 0)

function askRestore(item) {
  pendingItem.value = item
  pendingAction.value = 'restore'
  restoreDialog.value?.show()
}

function askForceDelete(item) {
  pendingItem.value = item
  pendingAction.value = 'force-delete'
  forceDeleteDialog.value?.show()
}

function finishPending(close) {
  pendingItem.value = null
  pendingAction.value = null
  close()
}

function confirmRestore({ close }) {
  if (!pendingItem.value?.restore_href) return

  router.post(pendingItem.value.restore_href, {}, {
    preserveScroll: true,
    onFinish: () => finishPending(close),
  })
}

function confirmForceDelete({ close }) {
  if (!pendingItem.value?.force_delete_href) return

  router.delete(pendingItem.value.force_delete_href, {
    preserveScroll: true,
    onFinish: () => finishPending(close),
  })
}
</script>

<template>
  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto max-w-7xl space-y-8">
      <section class="rounded-[2rem] border border-white/[0.055] bg-white/[0.024] p-6 md:p-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">Archive Recovery Bay</div>
            <h1 class="mt-2 text-3xl font-black text-horizon-white md:text-5xl">Archive Trash</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-text-secondary md:text-base">
              Restore soft-deleted Archive content or permanently delete records that should be removed forever. Topic deletes are bundled with their entries so whole sections can be recovered cleanly.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
              <div class="rounded-full border border-white/[0.055] bg-white/[0.024] px-3 py-1 text-xs font-semibold text-text-secondary inline-flex">
                {{ totalTrashCount }} deleted item{{ totalTrashCount === 1 ? '' : 's' }}
              </div>
              <div v-if="trashIsEmpty" class="rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1 text-xs font-semibold text-emerald-200 inline-flex">
                Recovery bay clear
              </div>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <Link :href="route('admin.archive.index')" class="rounded-xl border border-white/[0.055] bg-white/[0.024] px-4 py-2 text-sm font-bold text-text-secondary hover:text-horizon-white">Back to Archive Admin</Link>
            <Link :href="route('admin.archive.audit.index')" class="rounded-xl border border-[color:var(--horizon-sunset-blue)]/35 bg-white/[0.042] px-4 py-2 text-sm font-bold text-horizon-white hover:bg-[color:var(--horizon-sunset-blue)]/20">Audit Log</Link>
          </div>
        </div>
      </section>

      <section v-if="trashIsEmpty" class="rounded-[2rem] border border-emerald-300/20 bg-emerald-300/10 p-6 md:p-8">
        <div class="text-xs font-bold uppercase tracking-[0.24em] text-emerald-200/80">Nothing to restore</div>
        <h2 class="mt-2 text-2xl font-black text-emerald-100">The Archive trash is empty.</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-100/80">
          Deleted Archive content will appear here after soft-delete. Until then, the recovery bay is quiet, tidy, and suspiciously well-behaved.
        </p>
      </section>

      <section v-for="group in groups" :key="group.key" class="space-y-4">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-text-muted">{{ group.title }}</div>
          <h2 class="text-2xl font-black text-horizon-white">{{ group.items.length }} item{{ group.items.length === 1 ? '' : 's' }}</h2>
          <p class="mt-1 text-sm text-text-secondary">{{ group.hint }}</p>
        </div>

        <div v-if="group.items.length" class="grid gap-4 lg:grid-cols-2">
          <article v-for="item in group.items" :key="`${group.key}-${item.id}`" class="rounded-3xl border border-white/[0.055] bg-white/[0.024] p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
              <div class="min-w-0">
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full border border-white/[0.055] bg-white/[0.042] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-[color:var(--horizon-sunset-blue)]">{{ item.type }}</span>
                  <span class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">Deleted {{ item.deleted_label }}</span>
                  <span v-if="item.entries_count !== undefined" class="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold text-text-muted">{{ item.entries_count }} related entries</span>
                </div>

                <h3 class="mt-3 text-xl font-black text-horizon-white">{{ item.type === 'Tag' ? `#${item.title}` : item.title }}</h3>
                <p v-if="item.topic_title" class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">Topic: {{ item.topic_title }}</p>
                <p v-if="item.description" class="mt-2 line-clamp-3 text-sm leading-6 text-text-secondary">{{ item.description }}</p>
                <div class="mt-3 text-xs text-text-muted">/{{ item.slug }}</div>
              </div>

              <div class="flex shrink-0 flex-wrap gap-2 md:flex-col">
                <HorizonButton type="button" size="sm" @click="askRestore(item)">Restore</HorizonButton>
                <HorizonButton type="button" variant="danger" size="sm" @click="askForceDelete(item)">Delete Forever</HorizonButton>
              </div>
            </div>
          </article>
        </div>

        <div v-else class="rounded-3xl border border-white/[0.055] bg-white/[0.024] p-6 text-sm text-text-secondary">
          {{ group.empty }}
        </div>
      </section>

      <HorizonConfirmDialog
        ref="restoreDialog"
        title="Restore Archive Item"
        confirm-label="Restore"
        cancel-label="Cancel"
        variant="success"
        :close-on-confirm="false"
        :message="`Restore ${pendingItem?.type ?? 'item'} '${pendingItem?.title ?? ''}' back into the active Archive?`"
        @confirm="confirmRestore"
      />

      <HorizonConfirmDialog
        ref="forceDeleteDialog"
        title="Permanently Delete Archive Item"
        confirm-label="Delete Forever"
        cancel-label="Cancel"
        variant="danger"
        :close-on-confirm="false"
        requires-text-input
        text-input-label="Type DELETE to permanently remove this item"
        text-input-placeholder="DELETE"
        confirm-text="DELETE"
        :message="`Permanently delete ${pendingItem?.type ?? 'item'} '${pendingItem?.title ?? ''}'? This bypasses trash and cannot be undone.`"
        @confirm="confirmForceDelete"
      />
    </div>
  </HorizonContainer>
</template>









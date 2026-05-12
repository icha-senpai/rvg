<template>
  <section class="rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-void-700)]/70 p-4 shadow-[0_0_32px_rgba(30,64,175,0.10)] md:p-5">
    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
          Asset Grid
        </div>

        <h3 class="mt-1 text-xl font-black text-horizon-white">
          Media Records
        </h3>

        <p class="mt-1 text-sm text-text-secondary">
          Select an asset to inspect URLs, filename, alt text, uploader, and delete controls.
        </p>
      </div>

      <div
        v-if="pagination.lastPage > 1"
        class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-text-muted"
      >
        Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
      </div>
    </div>

    <div
      v-if="loading"
      class="rounded-[1.5rem] border border-white/10 bg-white/[0.025] p-10 text-center"
    >
      <div class="text-2xl font-black text-horizon-white">
        Loading Media…
      </div>

      <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
        Fetching current asset records.
      </p>
    </div>

    <div
      v-else-if="!media.length"
      class="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.025] p-10 text-center"
    >
      <div class="text-2xl font-black text-horizon-white">
        No Media Found
      </div>

      <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
        Upload a new asset or clear filters to return to the full media library.
      </p>
    </div>

    <div
      v-else
      class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
    >
      <article
        v-for="item in media"
        :key="item.id"
        class="group relative overflow-hidden rounded-[1.5rem] border border-white/10 bg-[linear-gradient(135deg,rgba(30,64,175,0.10),var(--horizon-void-700)_42%,var(--horizon-void-900))] shadow-[0_0_24px_rgba(30,64,175,0.08)] transition duration-200 hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-magenta)]/40 hover:shadow-[0_0_36px_rgba(192,38,211,0.12)]"
        @click="$emit('select', item)"
      >
        <div class="aspect-[16/10] bg-black/30">
          <img
            :src="item.thumbnail_url || item.medium_url || item.url"
            :alt="item.alt_text || item.original_filename"
            class="h-full w-full object-cover"
            loading="lazy"
          />
        </div>

        <div class="space-y-3 p-4">
          <div>
            <div class="truncate text-sm font-bold text-horizon-white">
              {{ item.original_filename }}
            </div>

            <div class="mt-1 text-xs text-text-muted">
              {{ item.human_size }} · {{ collectionLabel(item.collection) }}
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/20 bg-[color:var(--horizon-sunset-blue)]/10 px-2.5 py-1 text-[11px] font-semibold text-text-secondary">
              #{{ item.id }}
            </span>

            <span class="max-w-full truncate rounded-full border border-white/10 bg-white/[0.035] px-2.5 py-1 text-[11px] font-semibold text-text-secondary">
              {{ collectionLabel(item.collection) }}
            </span>
          </div>
        </div>
      </article>
    </div>

    <div
      v-if="pagination.lastPage > 1"
      class="mt-6 flex items-center justify-between border-t border-white/10 pt-5"
    >
      <HorizonButton
        variant="ghost"
        size="sm"
        :disabled="!pagination.prevUrl"
        @click="$emit('page', pagination.currentPage - 1)"
      >
        Previous
      </HorizonButton>

      <div class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">
        Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
      </div>

      <HorizonButton
        variant="ghost"
        size="sm"
        :disabled="!pagination.nextUrl"
        @click="$emit('page', pagination.currentPage + 1)"
      >
        Next
      </HorizonButton>
    </div>
  </section>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'

defineProps({
  loading: { type: Boolean, required: true },
  media: { type: Array, required: true },
  pagination: { type: Object, required: true },
  collectionLabel: { type: Function, required: true },
})

defineEmits(['select', 'page'])
</script>
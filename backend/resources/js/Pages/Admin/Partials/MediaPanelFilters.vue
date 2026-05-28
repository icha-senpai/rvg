<template>
  <section class="relative z-30 overflow-visible rounded-[2rem] border border-white/[0.055] bg-[color:var(--horizon-void-700)]/70 p-5 ">
    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
      <div>
        <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
          Asset Filters
        </div>

        <h3 class="mt-1 text-xl font-black text-horizon-white">
          Search Media
        </h3>

        <p class="mt-1 text-sm text-text-secondary">
          Filter by filename, alt text, or media collection.
        </p>
      </div>

      <div
        v-if="collection"
        class="rounded-2xl border border-white/[0.055] bg-white/[0.042] px-4 py-3"
      >
        <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
          Collection
        </div>

        <div class="mt-1 text-sm font-semibold text-horizon-white">
          {{ activeCollectionLabel }}
        </div>
      </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_16rem_auto] lg:items-end">
      <div>
        <label class="mb-1 block text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
          Search
        </label>

        <input
          :value="search"
          type="text"
          class="hz-input w-full"
          placeholder="Search filename or alt text..."
          @input="$emit('update:search', $event.target.value)"
          @keyup.enter="$emit('search')"
        />
      </div>

      <div class="relative z-[9999]">
        <HorizonSelect
          :model-value="collection"
          :options="collectionOptions"
          label="Collection"
          class="w-full"
          @update:model-value="$emit('update:collection', $event)"
        />
      </div>

      <div class="flex flex-wrap gap-2">
        <HorizonButton
          variant="primary"
          size="sm"
          @click="$emit('search')"
        >
          Search
        </HorizonButton>

        <HorizonButton
          variant="ghost"
          size="sm"
          @click="$emit('clear')"
        >
          Clear
        </HorizonButton>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

const props = defineProps({
  search: { type: String, required: true },
  collection: { type: String, default: null },
  collectionOptions: { type: Array, required: true },
})

defineEmits(['update:search', 'update:collection', 'search', 'clear'])

const activeCollectionLabel = computed(() => {
  return props.collectionOptions.find(option => option.value === props.collection)?.label ?? props.collection
})
</script>








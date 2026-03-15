<template>
  <div class="hz-panel hz-stack-sm border! border-(--horizon-sunset-blue)!">
    <div class="hz-row" style="flex-wrap: wrap;">
      <input
        :value="search"
        type="text"
        class="hz-input"
        style="max-width: 280px;"
        placeholder="Search filename or alt text..."
        @input="$emit('update:search', $event.target.value)"
        @keyup.enter="$emit('search')"
      />

      <HorizonSelect
        :model-value="collection"
        :options="collectionOptions"
        label=""
        style="max-width: 200px;"
        @update:model-value="$emit('update:collection', $event)"
      />

      <HorizonButton variant="primary" size="sm" @click="$emit('search')">
        Search
      </HorizonButton>

      <HorizonButton variant="ghost" size="sm" @click="$emit('clear')">
        Clear
      </HorizonButton>
    </div>
  </div>
</template>

<script setup>
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonSelect from '@/Components/HorizonSelect.vue'

defineProps({
  search: { type: String, required: true },
  collection: { type: String, default: null },
  collectionOptions: { type: Array, required: true },
})

defineEmits(['update:search', 'update:collection', 'search', 'clear'])
</script>

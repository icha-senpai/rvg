<template>
  <HorizonContainer class="space-y-10">

    <!-- HEADER -->
    <HorizonSectionHeader
      label="Operations"
      title="Your Available Operations"
    />

    <!-- LIST -->
    <div class="space-y-4">
      <OperationAccordion
        v-for="op in sortedOperations"
        :key="op.id"
        :operation="op"
      />
    </div>

  </HorizonContainer>
</template>

<script setup>
import { computed } from 'vue';
import OperationAccordion from '@/Components/Operations/OperationAccordion.vue';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';

const props = defineProps({
  operations: Array,
});

// Earliest start time first
const sortedOperations = computed(() => {
  return [...(props.operations ?? [])].sort((a, b) => {
    const aDate = parseDate(a.starts_at);
    const bDate = parseDate(b.starts_at);

    if (!aDate && !bDate) return 0;
    if (!aDate) return 1; 
    if (!bDate) return -1;

    return aDate.getTime() - bDate.getTime();
  });
});
console.log("Raw starts_at:", props.operations.map(o => o.starts_at));
console.log("Parsed starts_at:", props.operations.map(o => parseDate(o.starts_at)));
function parseDate(value) {
  if (!value) return null;

  // Normalize format for JS (remove microseconds, ensure Z)
  let v = value.replace(/\.\d+Z$/, 'Z').replace(/\.\d+$/, 'Z');

  // If no timezone exists, assume UTC
  if (!v.endsWith('Z') && !v.includes('+')) v = v + 'Z';

  const d = new Date(v);
  return isNaN(d.getTime()) ? null : d;
}


</script>


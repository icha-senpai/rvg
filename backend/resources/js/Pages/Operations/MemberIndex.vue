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
        v-for="op in operations"
        :key="op.id"
        :operation="op"
      />
    </div>

  </HorizonContainer>
</template>

<script setup>
import OperationAccordion from '@/Components/Operations/OperationAccordion.vue';
import HorizonContainer from '@/Components/HorizonContainer.vue';
import HorizonSectionHeader from '@/Components/HorizonSectionHeader.vue';

defineProps({
  operations: Array
});
</script>

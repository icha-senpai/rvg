<template>
  <HorizonContainer>
    <HorizonSectionHeader label="Squadrons" title="Squadron Management" />
    <HorizonPanel class="p-6">
      <div class="hz-title-lg">Squadrons Loaded:</div>
      <pre>{{ squadrons }}</pre>
    </HorizonPanel>
  </HorizonContainer>
</template>

<script setup>
const props = defineProps({
  squadrons: Array
})
</script>

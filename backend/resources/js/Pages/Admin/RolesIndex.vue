<template>
  <HorizonContainer>
    <HorizonSectionHeader
      label="Admin"
      title="Roles"
    />

    <HorizonPanel class="p-6 space-y-4">
      <div class="hz-title-lg mb-4">Defined Roles</div>

      <div v-for="r in roles" :key="r.id" class="p-4 hz-overlay-light rounded-xl">
        <div class="hz-title">{{ r.name }}</div>
        <div class="hz-caption text-horizon-muted">{{ r.slug }}</div>
      </div>
    </HorizonPanel>
  </HorizonContainer>
</template>

<script setup>
defineProps({ roles: Array });
</script>

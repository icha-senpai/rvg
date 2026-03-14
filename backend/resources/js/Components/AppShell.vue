<script setup>
import SideNav from '@/Components/SideNav.vue'
</script>

<template>
  <div class="min-h-screen flex bg-grid-horizon_3 text-text-primary">
    <SideNav />

    <main class="flex-1 min-w-0">
      <slot />
    </main>
  </div>
</template>

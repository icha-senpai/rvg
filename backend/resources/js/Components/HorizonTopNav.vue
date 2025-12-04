<template>
  <nav
    class="w-full bg-[var(--color-bg-surface)] hz-shadow-soft flex items-center justify-between px-6 py-4"
  >

    <!-- Brand -->
    <div class="flex items-center gap-3">
      <div
        class="w-2.5 h-2.5 rounded-full"
        style="background: var(--color-horizon-blue-light);"
      ></div>

      <h1 class="hz-title-md text-horizon-white">
        {{ brand }}
      </h1>
    </div>

    <!-- Action Slot -->
    <div class="flex items-center gap-4">
      <slot />
    </div>

  </nav>
</template>

<script setup>
defineProps({
  brand: String,
});
</script>

<script setup>
defineProps({
  open: Boolean
});
</script>
<template>
  <div
    v-if="open"
    class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto bg-black/75 p-3 backdrop-blur-md sm:p-4"
  >
    <div
      class="hz-surface-welcome relative z-10 flex w-full max-w-4xl flex-col overflow-hidden rounded-[1.75rem] border border-white/[0.055] shadow-[0_24px_80px_rgba(0,0,0,0.45)]"
    >
      <div class="pointer-events-none absolute inset-0 opacity-40">
        <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
        <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
      </div>

      <div class="relative p-5 sm:p-6">
        <slot />
      </div>
    </div>
  </div>
</template>

<template>
  <div
    class="hz-card hz-shadow-soft w-40 h-52 rounded-3xl flex flex-col items-center justify-center text-center px-4 hz-stack"
  >
    <!-- Logo -->
    <img
      :src="logo"
      alt=""
      class="w-16 h-16 mb-2 rounded-lg object-cover"
    />

    <!-- Name -->
    <h3 class="hz-title-md text-horizon-white">
      {{ name }}
    </h3>

    <!-- Motto -->
    <p class="hz-tiny hz-text-muted">
      {{ motto }}
    </p>
  </div>
</template>

<script setup>
defineProps({
  name: String,
  motto: String,
  logo: String,
});
</script>









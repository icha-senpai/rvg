<template>
  <div class="hz-panel hz-shadow-ambient hz-stack">

    <!-- Sender -->
    <h3 class="hz-title-md text-horizon-white">
      {{ sender }}
    </h3>

    <!-- Message Body -->
    <p class="hz-body">
      {{ message }}
    </p>

    <!-- Divider -->
    <div class="border-t border-[var(--color-bg-hover)] my-2"></div>

    <!-- Timestamp -->
    <div class="hz-tiny text-right hz-text-muted">
      {{ timestamp }}
    </div>

  </div>
</template>

<script setup>
defineProps({
  sender: String,
  message: String,
  timestamp: String,
});
</script>

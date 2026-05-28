<template>
  <div class="hz-panel hz-shadow-ambient hz-stack">

    <!-- Title -->
    <h2 class="hz-title-lg">
      {{ title }}
    </h2>

    <!-- Summary -->
    <div class="hz-stack-sm">
      <div class="hz-section-label">Summary</div>
      <p class="hz-body">{{ summary }}</p>
    </div>

    <!-- Divider -->
    <div class="border-t border-[var(--color-bg-hover)] my-2"></div>

    <!-- Key Events -->
    <div class="hz-stack-sm">
      <div class="hz-section-label">Key Events</div>

      <ul class="hz-stack-sm">
        <li
          v-for="item in events"
          :key="item"
          class="hz-body"
        >
          {{ item }}
        </li>
      </ul>
    </div>

    <!-- Slotted content -->
    <slot />
  </div>
</template>

<script setup>
defineProps({
  title: String,
  summary: String,
  events: Array,
});
</script>









<template>
  <div class="hz-panel overflow-hidden">

    <table class="w-full">
      
      <!-- Header -->
      <thead class="bg-[var(--color-bg-hover)]">
        <tr>
          <th
            v-for="h in headers"
            :key="h"
            class="px-4 py-3 hz-tiny uppercase tracking-wide text-left text-[var(--color-text-secondary)]"
          >
            {{ h }}
          </th>
        </tr>
      </thead>

      <!-- Body -->
      <tbody>
        <tr
          v-for="row in rows"
          :key="row.id"
          class="border-b border-[var(--color-bg-hover)] transition-colors duration-150 hover:bg-[var(--color-bg-elevated)]"
        >
          <td
            v-for="key in keys"
            :key="key"
            class="px-4 py-3 hz-body"
          >
            {{ row[key] }}
          </td>
        </tr>
      </tbody>

    </table>

  </div>
</template>

<script setup>
defineProps({
  headers: Array,
  rows: Array,
  keys: Array,
});
</script>

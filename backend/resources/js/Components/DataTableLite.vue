<template>
  <div class="bg-bg-surface rounded-xl hz-panel overflow-hidden">
    <table class="w-full">
      <thead class="bg-bg-hover hz-overlay-blue">
        <tr>
          <th v-for="h in headers" :key="h" class="text-left px-4 py-3 hz-caption uppercase tracking-wide">
            {{ h }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="row in rows"
          :key="row.id"
          class="border-b border-bg-hover hover:bg-bg-hover hz-motion"
        >
          <td v-for="key in keys" :key="key" class="px-4 py-3 hz-body">
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

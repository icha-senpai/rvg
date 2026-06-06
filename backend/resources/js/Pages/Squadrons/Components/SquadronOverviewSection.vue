<script setup>
import { computed } from 'vue'
import { normalizeRichTextHtml } from '@/richText'

const props = defineProps({
  squadron: Object,
})

const squadronDescriptionHtml = computed(() => normalizeRichTextHtml(props.squadron?.description))
</script>

<template>
  <div class="hz-stack">
    <div class="hz-section-label">Overview</div>
    <div
      v-if="squadron?.description"
      class="hz-soft hz-rte-content space-y-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:underline [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:text-base [&_h3]:font-semibold [&_h4]:text-sm [&_h4]:font-semibold [&_h5]:text-sm [&_h5]:font-medium [&_h6]:text-xs [&_h6]:font-medium [&_blockquote]:border-l-2 [&_blockquote]:border-(--color-bg-hover) [&_blockquote]:pl-3 [&_blockquote]:opacity-90 [&_hr]:my-3 [&_hr]:border-(--color-bg-hover) [&_code]:rounded [&_code]:px-1 [&_code]:py-0.5 [&_code]:bg-bg-elevated [&_pre]:rounded [&_pre]:p-3 [&_pre]:bg-bg-elevated [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-(--color-bg-hover) [&_th]:bg-bg-hover [&_th]:p-2 [&_td]:border [&_td]:border-(--color-bg-hover) [&_td]:bg-bg-elevated [&_td]:p-2 [&_mark]:rounded [&_mark]:px-1 [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg"
      v-html="squadronDescriptionHtml"
    />
    <p v-else class="hz-soft">
      No description provided.
    </p>
  </div>
</template>

<style scoped>
:deep(.hz-rte-content .hz-rte-callout) {
  border-radius: 0 !important;
}
</style>







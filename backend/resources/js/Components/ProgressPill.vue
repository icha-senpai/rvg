<template>
  <div
    :class="[
      'px-3 py-1 rounded-full text-xs font-semibold',
      variantMap[variant]
    ]"
  >
    <slot />
  </div>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'blue' }
});

/*
  Pure Horizon variants — no random Tailwind colors.
  Built strictly on your real design tokens.
*/
const variantMap = {
  blue: `
    bg-[color-mix(in srgb, var(--color-horizon-blue-light) 12%, transparent)]
    text-[var(--color-horizon-blue-light)]
    border border-[color-mix(in srgb, var(--color-horizon-blue) 40%, transparent)]
  `,

  green: `
    bg-[rgba(21,128,61,0.15)]
    text-[#22c55e]
    border border-[rgba(21,128,61,0.35)]
  `,

  red: `
    bg-[rgba(239,68,68,0.15)]
    text-[#ef4444]
    border border-[rgba(239,68,68,0.35)]
  `,

  yellow: `
    bg-[rgba(234,179,8,0.12)]
    text-[rgb(234 179 8)]
    border border-[rgba(234,179,8,0.35)]
  `,
};
</script>

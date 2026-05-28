<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import HorizonButton from '@/Components/HorizonButton.vue';

const props = defineProps({
  status: {
    type: Number,
    required: true,
  },
});

const errorMeta = computed(() => {
  const status = Number(props.status);

  if (status === 404) {
    return {
      title: 'Signal Lost',
      subtitle: "This location isn't on any known chart.",
      message: 'The page you requested does not exist, or it has been moved.',
    };
  }

  if (status === 403) {
    return {
      title: 'Access Denied',
      subtitle: 'Authorization failed at the airlock.',
      message: "You don't have permission to access this sector.",
    };
  }

  if (status === 419) {
    return {
      title: 'Session Drift',
      subtitle: 'Your session desynced from command.',
      message: 'Please refresh and try again.',
    };
  }

  if (status === 429) {
    return {
      title: 'Throttle Engaged',
      subtitle: 'Too many requests in a short window.',
      message: 'Give it a moment, then try again.',
    };
  }

  if (status === 503) {
    return {
      title: 'Maintenance Window',
      subtitle: 'Horizon systems are undergoing calibration.',
      message: 'Try again shortly.',
    };
  }

  return {
    title: 'Anomaly Detected',
    subtitle: 'Something went sideways in the black.',
    message: 'Try again. If this keeps happening, report it to command.',
  };
});

const pageTitle = computed(() => `${props.status} - ${errorMeta.value.title}`);

function goBack() {
  try {
    if (window.history.length > 1) {
      window.history.back();
      return;
    }
  } catch (e) {
    // ignore
  }

  window.location.href = '/';
}
</script>

<template>
  <Head :title="pageTitle" />

  <div class="min-h-screen flex items-center justify-center bg-grid-horizon_3 text-text-primary px-6 py-12">
    <div class="w-full max-w-3xl">
      <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-bg-surface shadow-xl">
        <div class="absolute inset-0 pointer-events-none opacity-[0.22]">
          <div class="absolute -top-48 left-1/2 -translate-x-1/2 w-[90%] h-[520px] rounded-full blur-[160px] bg-[var(--horizon-sunset-blue)]"></div>
          <div class="absolute -bottom-48 left-1/2 -translate-x-1/2 w-[90%] h-[520px] rounded-full blur-[180px] bg-[var(--horizon-sunset-pink)] opacity-70"></div>
        </div>

        <div class="relative p-8 sm:p-10">
          <div class="flex items-center justify-between gap-6">
            <div class="flex items-center gap-4">
              <img
                src="/images/PNG_Symbol%20logo.png"
                alt="Horizon"
                class="h-12 w-12 rounded-xl border border-white/10 bg-bg-elevated p-2"
              />
              <div class="hz-stack-sm">
                <div class="hz-title-md tracking-wide">Horizon Navigation</div>
                <div class="hz-body hz-text-soft">Status code {{ status }}</div>
              </div>
            </div>

            <div class="hidden sm:block text-right">
              <div class="hz-title-xl hz-glyph tracking-widest text-horizon-white">
                {{ status }}
              </div>
              <div class="hz-body hz-text-soft">{{ errorMeta.subtitle }}</div>
            </div>
          </div>

          <div class="mt-8 hz-stack">
            <div class="hz-title-xl text-horizon-white">{{ errorMeta.title }}</div>
            <div class="hz-body text-text-secondary max-w-2xl">{{ errorMeta.message }}</div>
          </div>

          <div class="mt-8 flex flex-col sm:flex-row gap-3">
            <Link
              href="/"
              class="hz-btn hz-btn-primary hz-btn-sm inline-flex items-center justify-center"
            >
              Return to Home
            </Link>

            <HorizonButton variant="ghost" size="sm" @click="goBack">
              Go Back
            </HorizonButton>

            <a
              href="/verify"
              class="hz-btn hz-btn-secondary hz-btn-sm inline-flex items-center justify-center"
            >
              Verify / Reconnect
            </a>
          </div>

          <div class="mt-8 pt-6 border-t border-white/10">
            <div class="hz-body hz-text-soft">
              If you got here by accident, check the URL or head back to a known sector.
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6 text-center hz-body hz-text-soft">
        HORIZON INTERSTELLAR · COMMAND NETWORK
      </div>
    </div>
  </div>
</template>









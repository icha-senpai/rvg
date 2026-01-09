<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import SideNav from '@/Components/SideNav.vue';
import HorizonButton from '@/Components/HorizonButton.vue';

const showVerifyCta = ref(false);
const verifyUrl = 'https://horizoninterstellar.com/verify';

function goToVerify() {
  window.location.href = verifyUrl;
}

function handleUnauthenticated() {
  showVerifyCta.value = true;
}

onMounted(() => {
  window.addEventListener('hz:unauthenticated', handleUnauthenticated);
});

onBeforeUnmount(() => {
  window.removeEventListener('hz:unauthenticated', handleUnauthenticated);
});
</script>

<template>
  <div class="min-h-screen flex bg-grid-horizon_3 text-text-primary">
    <div
      v-if="showVerifyCta"
      class="fixed top-3 left-3 right-3 z-50 md:left-1/2 md:right-auto md:w-xl md:-translate-x-1/2"
    >
      <div class="hz-alert hz-alert-danger">
        <div class="hz-alert-title">Verification Required</div>
        <div class="hz-alert-body">
          Your session is missing/expired. To continue, please verify your account.
          <a
            :href="verifyUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="underline"
          >
            Verify here
          </a>
        </div>
        <div class="hz-row mt-2 gap-2">
          <HorizonButton variant="primary" size="sm" @click="goToVerify">
            Go to Verification
          </HorizonButton>
        </div>
      </div>
    </div>

    <SideNav />

    <main :class="['flex-1 min-w-0', showVerifyCta ? 'pt-36' : '']">
      <slot />
    </main>
  </div>
</template>

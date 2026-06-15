<template>
  <Head title="Welcome" />

  <HorizonContainer class="py-8 md:py-10">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8">
      <section class="relative overflow-hidden rounded-[2rem] border border-white/[0.08] bg-[color:var(--color-surface-accent)] px-5 py-6 shadow-[0_24px_80px_rgba(0,0,0,0.34)] sm:px-7 sm:py-8 lg:px-10 lg:py-10">
        <div class="pointer-events-none absolute inset-0">
          <div class="absolute inset-y-0 left-0 w-full bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.09),transparent_52%)]"></div>
          <div class="absolute -left-20 top-[-5.5rem] h-64 w-64 rounded-full bg-[color:var(--horizon-sunset-blue)]/18 blur-[110px]"></div>
          <div class="absolute right-[-4.5rem] top-10 h-56 w-56 rounded-full bg-[color:var(--horizon-sunset-magenta)]/14 blur-[105px]"></div>
          <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-white/14 to-transparent"></div>
        </div>

        <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1.4fr)_minmax(19rem,0.9fr)] lg:items-center">
          <div class="space-y-5">
            <div class="flex flex-wrap items-center gap-3">
              <HorizonBadge
                variant="info"
                size="md"
                uppercase
              >
                Horizon Command Hub
              </HorizonBadge>
            </div>

            <div class="space-y-4">
              <img
                src="/images/PNG_Horizontally_Logo.png"
                alt="Horizon Interstellar"
                class="h-24 w-auto max-w-full object-contain sm:h-28 lg:h-32"
              />

              <div class="max-w-3xl space-y-4">
                <h1 class="hz-title-xxl max-w-3xl text-balance leading-[1.04]">
                  Welcome back to the Horizon command deck.
                </h1>

                <p class="hz-body max-w-3xl text-text-secondary">
                  Operations, people, records, and assets in one place.
                </p>
              </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
              <Link href="/operations">
                <HorizonButton size="lg">
                  View Operations
                </HorizonButton>
              </Link>

              <Link href="/archive">
                <HorizonButton
                  variant="ghost"
                  size="lg"
                >
                  Open Archive
                </HorizonButton>
              </Link>

              <Link href="/members">
                <HorizonButton
                  variant="secondary"
                  size="lg"
                >
                  Browse Members
                </HorizonButton>
              </Link>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            <HorizonFormBlock
              label="Mission Flow"
              helper="Live ops, signups, rosters, and reporting."
              panel-class="backdrop-blur-sm"
            >
              <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                  <p class="hz-body-strong">Operations</p>
                  <HorizonBadge variant="info">
                    Live
                  </HorizonBadge>
                </div>
                <p class="text-sm leading-6 text-text-secondary">
                  Go straight to the run.
                </p>
              </div>
            </HorizonFormBlock>

            <HorizonFormBlock
              label="Org Structure"
              helper="People, units, and reference."
              panel-class="backdrop-blur-sm"
            >
              <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                  <p class="hz-body-strong">People + Records</p>
                  <HorizonBadge variant="accent">
                    Ready
                  </HorizonBadge>
                </div>
                <p class="text-sm leading-6 text-text-secondary">
                  Roster, squadrons, archive.
                </p>
              </div>
            </HorizonFormBlock>
          </div>
        </div>
      </section>

      <section class="grid gap-4 lg:grid-cols-3">
        <Link
          href="/operations"
          class="group"
        >
          <div class="hz-surface-welcome h-full rounded-[1.6rem] border border-white/[0.06] p-5 transition duration-200 group-hover:-translate-y-0.5 group-hover:border-[color:var(--horizon-sunset-blue)]/28 group-hover:shadow-[0_18px_46px_rgba(0,0,0,0.22)]">
            <div class="mb-4 flex items-center justify-between gap-3">
              <HorizonBadge
                variant="info"
                uppercase
              >
                Operations
              </HorizonBadge>
              <span class="text-lg text-text-muted transition group-hover:text-[color:var(--horizon-text-primary)]">-></span>
            </div>
            <h2 class="hz-title-md">
              Jump into the next mission
            </h2>
            <p class="mt-3 text-sm leading-6 text-text-secondary">
              Run, roster, briefing.
            </p>
          </div>
        </Link>

        <Link
          href="/members"
          class="group"
        >
          <div class="hz-surface-welcome h-full rounded-[1.6rem] border border-white/[0.06] p-5 transition duration-200 group-hover:-translate-y-0.5 group-hover:border-[color:var(--horizon-sunset-magenta)]/28 group-hover:shadow-[0_18px_46px_rgba(0,0,0,0.22)]">
            <div class="mb-4 flex items-center justify-between gap-3">
              <HorizonBadge
                variant="accent"
                uppercase
              >
                Members
              </HorizonBadge>
              <span class="text-lg text-text-muted transition group-hover:text-[color:var(--horizon-text-primary)]">-></span>
            </div>
            <h2 class="hz-title-md">
              Find your people fast
            </h2>
            <p class="mt-3 text-sm leading-6 text-text-secondary">
              Roster, profiles, squadrons.
            </p>
          </div>
        </Link>

        <Link
          href="/archive"
          class="group"
        >
          <div class="hz-surface-welcome h-full rounded-[1.6rem] border border-white/[0.06] p-5 transition duration-200 group-hover:-translate-y-0.5 group-hover:border-[color:var(--horizon-sunset-indigo)]/28 group-hover:shadow-[0_18px_46px_rgba(0,0,0,0.22)]">
            <div class="mb-4 flex items-center justify-between gap-3">
              <HorizonBadge
                variant="muted"
                uppercase
              >
                Archive
              </HorizonBadge>
              <span class="text-lg text-text-muted transition group-hover:text-[color:var(--horizon-text-primary)]">-></span>
            </div>
            <h2 class="hz-title-md">
              Pull up doctrine and records
            </h2>
            <p class="mt-3 text-sm leading-6 text-text-secondary">
              Docs, records, reference.
            </p>
          </div>
        </Link>
      </section>
    </div>
  </HorizonContainer>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import HorizonBadge from '@/Components/HorizonBadge.vue'
import HorizonButton from '@/Components/HorizonButton.vue'
import HorizonContainer from '@/Components/HorizonContainer.vue'
import HorizonFormBlock from '@/Components/HorizonFormBlock.vue'
</script>

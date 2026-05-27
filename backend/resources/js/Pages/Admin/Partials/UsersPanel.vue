<template>
  <div class="mx-auto max-w-6xl space-y-6">
    <!-- Search command panel -->
    <section class="relative z-30 overflow-visible rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-void-700)]/70 p-5 shadow-[0_0_32px_rgba(30,64,175,0.10)]">
      <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            Personnel Search
          </div>

          <h2 class="mt-1 text-xl font-black text-horizon-white">
            User Administration
          </h2>

          <p class="mt-1 text-sm text-text-secondary">
            Search users by name, RSI handle, Discord name, or internal ID.
          </p>
        </div>

        <div class="rounded-2xl border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-4 py-3">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
            Registry
          </div>

          <div class="mt-1 text-sm font-semibold text-horizon-white">
            {{ totalUsers }} Users
          </div>
        </div>
      </div>

      <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
        <input
          v-model="search"
          type="text"
          class="hz-input"
          placeholder="Search by name, RSI handle, Discord name, or ID..."
          @keyup.enter="applySearch"
        />

        <div class="flex flex-wrap gap-2">
          <HorizonButton
            variant="primary"
            size="sm"
            @click="applySearch"
          >
            Search
          </HorizonButton>

          <HorizonButton
            variant="ghost"
            size="sm"
            @click="clearSearch"
          >
            Clear
          </HorizonButton>
        </div>
      </div>
    </section>

    <!-- Users command list -->
    <section class="rounded-[2rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-void-700)]/70 p-4 shadow-[0_0_32px_rgba(30,64,175,0.10)] md:p-5">
      <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
            User Registry
          </div>

          <h2 class="mt-1 text-xl font-black text-horizon-white">
            {{ totalUsers }} User Records
          </h2>

          <p class="mt-1 text-sm text-text-secondary">
            Edit RSI handles, ranks, verification status, profile fields, and role assignments.
          </p>
        </div>

        <div class="rounded-full border border-white/10 bg-white/[0.035] px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-text-muted">
          Page {{ currentPage }} of {{ lastPage }}
        </div>
      </div>

      <div class="space-y-4">
        <article
          v-for="u in users.data"
          :key="u.id"
          class="group relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-[linear-gradient(135deg,rgba(30,64,175,0.10),var(--horizon-void-700)_42%,var(--horizon-void-900))] p-5 shadow-[0_0_28px_rgba(30,64,175,0.10)] transition duration-200 hover:-translate-y-0.5 hover:border-[color:var(--horizon-sunset-magenta)]/40 hover:shadow-[0_0_42px_rgba(192,38,211,0.14)]"
        >
          <div class="pointer-events-none absolute inset-0 opacity-0 transition duration-200 group-hover:opacity-100">
            <div class="absolute inset-x-8 top-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
            <div class="absolute inset-x-10 bottom-0 h-px bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
          </div>

          <div class="relative grid gap-5 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-center">
            <!-- Avatar -->
            <Link
              :href="u.rsi_handle ? route('member.profile', u.rsi_handle) : `/user/${u.id}`"
              class="block shrink-0"
              title="View profile"
            >
              <img
                v-if="u.discord_avatar"
                :src="u.discord_avatar"
                alt=""
                class="h-20 w-20 rounded-2xl border border-[color:var(--horizon-sunset-blue)]/25 object-cover shadow-[0_0_24px_rgba(30,64,175,0.14)]"
              />

              <div
                v-else
                class="flex h-20 w-20 items-center justify-center rounded-2xl border border-[color:var(--horizon-sunset-blue)]/25 bg-white/[0.04] text-2xl font-black text-horizon-white shadow-[0_0_24px_rgba(30,64,175,0.14)]"
              >
                {{ String(u.rsi_handle || u.discord_name || 'M').slice(0, 1).toUpperCase() }}
              </div>
            </Link>

            <!-- Main info -->
            <div class="min-w-0 space-y-3">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-text-muted">
                  User Record #{{ u.id }}
                </div>

                <Link
                  :href="u.rsi_handle ? route('member.profile', u.rsi_handle) : `/user/${u.id}`"
                  class="mt-1 block truncate text-2xl font-black tracking-tight text-horizon-white hover:underline"
                  :style="userNameColor(u) ? { color: userNameColor(u) } : undefined"
                >
                  {{ u.rsi_handle || u.discord_name || 'Unknown' }}
                </Link>
              </div>

              <div class="flex flex-wrap gap-2">
                <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                  Rank {{ formatRankLabel(u.rank) }}
                </span>

                <span class="rounded-full border border-[color:var(--horizon-sunset-indigo)]/25 bg-[color:var(--horizon-sunset-indigo)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                  Level {{ u.rank_level || '-' }}
                </span>

                <span
                  class="rounded-full border px-3 py-1 text-xs font-semibold"
                  :class="u.global_status === 'active'
                    ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100'
                    : 'border-amber-300/25 bg-amber-300/10 text-amber-100'"
                >
                  {{ formatGlobalStatusLabel(u.global_status) }}
                </span>
              </div>

              <div class="rounded-2xl border border-white/10 bg-white/[0.025] p-3">
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                  Roles
                </div>

                <div class="mt-1 text-sm text-text-secondary">
                  <span v-if="!u.roles?.length">None</span>
                  <span v-else class="text-horizon-white">{{ formatRoleList(u.roles) }}</span>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-2 lg:justify-end">
              <Link
                :href="u.rsi_handle ? route('member.profile', u.rsi_handle) : `/user/${u.id}`"
                class="inline-flex items-center justify-center rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-text-secondary transition hover:border-[color:var(--horizon-sunset-blue)]/30 hover:bg-white/[0.055] hover:text-horizon-white"
              >
                View
              </Link>

              <HorizonButton
                size="sm"
                variant="primary"
                @click="openUserEditor(u)"
              >
                Edit
              </HorizonButton>
            </div>
          </div>
        </article>

        <div
          v-if="!users.data?.length"
          class="rounded-[1.5rem] border border-dashed border-white/15 bg-white/[0.025] p-10 text-center"
        >
          <div class="text-2xl font-black text-horizon-white">
            No Users Found
          </div>

          <p class="mx-auto mt-2 max-w-xl text-sm text-text-secondary">
            Adjust the search query or clear the filter to return to the full admin registry.
          </p>

          <HorizonButton
            class="mt-4"
            variant="ghost"
            size="sm"
            @click="clearSearch"
          >
            Clear Search
          </HorizonButton>
        </div>
      </div>

      <!-- Pagination -->
      <div
        v-if="lastPage > 1"
        class="mt-6 flex items-center justify-between border-t border-white/10 pt-5"
      >
        <HorizonButton
          variant="ghost"
          size="sm"
          :disabled="!prevUrl"
          @click="goTo(prevUrl)"
        >
          Previous
        </HorizonButton>

        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-text-muted">
          Page {{ currentPage }} of {{ lastPage }}
        </div>

        <HorizonButton
          variant="ghost"
          size="sm"
          :disabled="!nextUrl"
          @click="goTo(nextUrl)"
        >
          Next
        </HorizonButton>
      </div>
    </section>
    <!-- MODAL -->
    <div
      v-if="editingUser"
      class="fixed inset-0 z-[90] flex items-center justify-center bg-black/75 p-4 backdrop-blur-md"
      @click.self="closeUserEditor"
    >
      <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute left-1/4 top-10 h-96 w-96 rounded-full bg-[color:var(--horizon-sunset-blue)]/16 blur-3xl"></div>
        <div class="absolute bottom-10 right-1/4 h-96 w-96 rounded-full bg-[color:var(--horizon-sunset-magenta)]/14 blur-3xl"></div>
      </div>

      <div
        class="relative z-10 flex max-h-[88vh] w-full max-w-5xl flex-col overflow-hidden rounded-[2rem] border border-[color:var(--horizon-sunset-indigo)]/45 bg-[linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))] shadow-[0_0_72px_rgba(67,56,202,0.28)] hz-animate-pop"
      >
        <div class="pointer-events-none absolute inset-0 opacity-40">
          <div class="absolute left-8 top-0 h-px w-56 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-blue)] to-transparent"></div>
          <div class="absolute bottom-0 right-10 h-px w-72 bg-gradient-to-r from-transparent via-[color:var(--horizon-sunset-magenta)] to-transparent"></div>
        </div>

        <!-- HEADER -->
        <header class="relative shrink-0 border-b border-[color:var(--horizon-sunset-blue)]/20 bg-white/[0.025] p-5">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 gap-4">
              <Link
                :href="editingUser.rsi_handle ? route('member.profile', editingUser.rsi_handle) : `/user/${editingUser.id}`"
                class="block shrink-0"
                title="View profile"
              >
                <img
                  v-if="editingUser.discord_avatar"
                  :src="editingUser.discord_avatar"
                  alt=""
                  class="h-16 w-16 rounded-2xl border border-[color:var(--horizon-sunset-blue)]/30 object-cover shadow-[0_0_20px_rgba(30,64,175,0.16)]"
                />

                <div
                  v-else
                  class="flex h-16 w-16 items-center justify-center rounded-2xl border border-[color:var(--horizon-sunset-blue)]/30 bg-white/[0.04] text-2xl font-black text-horizon-white"
                >
                  {{ String(editingUser.rsi_handle || editingUser.discord_name || 'M').slice(0, 1).toUpperCase() }}
                </div>
              </Link>

              <div class="min-w-0">
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                  Admin User Editor
                </div>

                <div
                  class="mt-1 truncate text-2xl font-black text-horizon-white md:text-3xl"
                  :style="userNameColor(editingUser) ? { color: userNameColor(editingUser) } : undefined"
                >
                  {{ editingUser.rsi_handle || editingUser.discord_name || 'Unknown' }}
                </div>

                <div class="mt-2 flex flex-wrap gap-2">
                  <span class="rounded-full border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-sunset-blue)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                    ID {{ editingUser.id }}
                  </span>

                  <span class="rounded-full border border-[color:var(--horizon-sunset-indigo)]/25 bg-[color:var(--horizon-sunset-indigo)]/10 px-3 py-1 text-xs font-semibold text-[color:var(--horizon-text-primary)]">
                    {{ formatRankLabel(editingUser.rank) }}
                  </span>

                  <span
                    class="rounded-full border px-3 py-1 text-xs font-semibold"
                    :class="editingUser.global_status === 'active'
                      ? 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100'
                      : 'border-amber-300/25 bg-amber-300/10 text-amber-100'"
                  >
                    {{ formatGlobalStatusLabel(editingUser.global_status) }}
                  </span>
                </div>
              </div>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2 lg:justify-end">
              <Link
                :href="editingUser.rsi_handle ? route('member.profile', editingUser.rsi_handle) : `/user/${editingUser.id}`"
                class="inline-flex items-center justify-center rounded-xl border border-white/10 bg-white/[0.035] px-4 py-2 text-sm font-bold text-text-secondary transition hover:border-[color:var(--horizon-sunset-blue)]/30 hover:bg-white/[0.055] hover:text-horizon-white"
              >
                View Profile
              </Link>

              <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/[0.035] text-lg font-bold text-text-secondary transition hover:border-[color:var(--horizon-sunset-magenta)]/35 hover:bg-[color:var(--horizon-sunset-magenta)]/10 hover:text-horizon-white"
                aria-label="Close user editor"
                @click="closeUserEditor"
              >
                ✕
              </button>
            </div>
          </div>
        </header>

        <div class="relative flex-1 overflow-y-auto p-5">
    <!-- FORM -->
          <div class="space-y-6">
            <!-- Identity + access -->
            <section class="rounded-[1.75rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-void-700)]/70 p-5 shadow-[0_0_28px_rgba(30,64,175,0.10)]">
              <div class="mb-5">
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                  Identity + Access
                </div>

                <h3 class="mt-1 text-xl font-black text-horizon-white">
                  Core User Record
                </h3>

                <p class="mt-1 text-sm text-text-secondary">
                  Update the public RSI handle, rank, rank level, and global account state.
                </p>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-[1.25rem] border border-white/10 bg-white/[0.025] p-4">
                  <label class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    RSI Handle
                  </label>

                  <input
                    v-model="form.rsi_handle"
                    class="hz-input"
                    placeholder="RSI handle..."
                  />
                </div>

                <div class="rounded-[1.25rem] border border-white/10 bg-white/[0.025] p-4">
                  <label class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    Global Status
                  </label>

                  <select
                    v-model="form.global_status"
                    class="hz-input"
                  >
                    <option
                      v-for="opt in globalStatusOptions"
                      :key="opt.value"
                      :value="opt.value"
                    >
                      {{ opt.label }}
                    </option>
                  </select>
                </div>

                <div class="rounded-[1.25rem] border border-[color:var(--horizon-sunset-blue)]/20 bg-[color:var(--horizon-sunset-blue)]/10 p-4">
                  <label class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    Rank
                  </label>

                  <select
                    v-model="form.rank"
                    class="hz-input"
                  >
                    <option
                      v-for="opt in rankOptions"
                      :key="opt.value"
                      :value="opt.value"
                    >
                      {{ opt.label }}
                    </option>
                  </select>
                </div>

                <div class="rounded-[1.25rem] border border-[color:var(--horizon-sunset-indigo)]/20 bg-[color:var(--horizon-sunset-indigo)]/10 p-4">
                  <label class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    Rank Level
                  </label>

                  <input
                    v-model.number="form.rank_level"
                    type="number"
                    class="hz-input"
                    disabled
                  />

                  <p class="mt-2 text-xs text-text-muted">
                    Rank level is calculated from the selected rank.
                  </p>
                </div>
              </div>
            </section>

            <!-- Verification -->
            <section class="rounded-[1.75rem] border border-[color:var(--horizon-sunset-magenta)]/25 bg-[radial-gradient(circle_at_top_right,var(--horizon-glow-magenta),transparent_46%),rgba(255,255,255,0.035)] p-5">
              <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                  <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                    Verification State
                  </div>

                  <h3 class="mt-1 text-xl font-black text-horizon-white">
                    RSI Verification Timestamp
                  </h3>

                  <p class="mt-1 text-sm text-text-secondary">
                    Set or clear the local verification timestamp used by Horizon.
                  </p>
                </div>

                <div
                  class="rounded-2xl border px-4 py-3"
                  :class="rsiVerifiedAtLocal
                    ? 'border-emerald-300/25 bg-emerald-300/10'
                    : 'border-amber-300/25 bg-amber-300/10'"
                >
                  <div class="text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    Status
                  </div>

                  <div class="mt-1 text-sm font-semibold text-horizon-white">
                    {{ rsiVerifiedAtLocal ? 'Verified' : 'Not Verified' }}
                  </div>
                </div>
              </div>

              <div class="relative" ref="rsiVerifiedAtPickerContainer">
                <button
                  type="button"
                  class="hz-input flex w-full cursor-pointer items-center justify-between gap-3 bg-[color:var(--horizon-void-800)] text-left"
                  @click="toggleRsiVerifiedAtPicker"
                >
                  <span class="truncate">
                    {{ rsiVerifiedAtDisplay }}
                  </span>

                  <span class="shrink-0 rounded-full border border-white/10 bg-white/[0.035] px-2 py-1 text-xs text-[var(--color-text-secondary)]">
                    Edit
                  </span>
                </button>

                <div
                  v-if="rsiVerifiedAtPickerOpen"
                  class="mt-3 w-full overflow-visible rounded-2xl border border-[color:var(--horizon-sunset-blue)]/35 bg-[color:var(--horizon-void-800)] shadow-2xl"
                >
                  <div class="flex items-center justify-between gap-2 border-b border-white/10 px-3 py-3">
                    <button
                      type="button"
                      class="rounded-lg border border-white/10 bg-white/[0.035] px-3 py-2 text-sm text-text-secondary transition hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white"
                      @click="goRsiVerifiedAtPrevMonth"
                    >
                      ‹
                    </button>

                    <div class="text-sm font-semibold text-horizon-white">
                      {{ rsiVerifiedAtMonthLabel }} {{ rsiVerifiedAtPickerYear }}
                    </div>

                    <button
                      type="button"
                      class="rounded-lg border border-white/10 bg-white/[0.035] px-3 py-2 text-sm text-text-secondary transition hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white"
                      @click="goRsiVerifiedAtNextMonth"
                    >
                      ›
                    </button>
                  </div>

                  <div class="px-3 pt-3">
                    <div class="grid grid-cols-7 gap-1 text-[11px] text-[var(--color-text-secondary)]">
                      <div
                        v-for="d in weekdayLabels"
                        :key="d"
                        class="text-center"
                      >
                        {{ d }}
                      </div>
                    </div>

                    <div class="mt-2 grid grid-cols-7 gap-1">
                      <button
                        v-for="cell in rsiVerifiedAtCalendarCells"
                        :key="cell.key"
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-transparent text-sm"
                        :class="[
                          cell.isBlank
                            ? 'pointer-events-none opacity-0'
                            : (cell.isSelected
                              ? 'bg-[color:var(--horizon-sunset-blue)] text-horizon-white'
                              : 'text-[var(--color-text-primary)] hover:bg-[var(--color-horizon-blue-10)]'),
                        ]"
                        @click="!cell.isBlank && selectRsiVerifiedAtDay(cell.day)"
                      >
                        {{ cell.day }}
                      </button>
                    </div>
                  </div>

                  <div class="border-t border-white/10 px-3 py-3">
                    <div class="space-y-3">
                      <div class="flex flex-wrap items-end gap-2">
                        <div class="w-20 space-y-1">
                          <div class="text-[11px] text-[var(--color-text-secondary)]">
                            Hour
                          </div>

                          <button
                            ref="rsiVerifiedAtHourButton"
                            type="button"
                            class="hz-input flex w-20 items-center justify-between bg-[color:var(--horizon-void-900)] px-3 py-2 text-left"
                            @click="toggleRsiVerifiedAtHourMenu"
                          >
                            <span>{{ String(rsiVerifiedAtHour).padStart(2, '0') }}</span>
                            <span class="text-[10px] text-[var(--color-text-secondary)]">▾</span>
                          </button>

                          <div
                            v-if="rsiVerifiedAtHourMenuOpen"
                            class="fixed z-50 max-h-40 overflow-y-auto rounded-lg border border-white/10 bg-[color:var(--horizon-void-800)] p-1 shadow-2xl"
                            :style="rsiVerifiedAtHourMenuStyle"
                          >
                            <button
                              v-for="h in 24"
                              :key="h"
                              type="button"
                              class="w-full rounded-md px-2 py-1 text-left text-sm"
                              :class="(rsiVerifiedAtHour === (h - 1))
                                ? 'bg-[color:var(--horizon-sunset-blue)] text-horizon-white'
                                : 'text-[var(--color-text-primary)] hover:bg-[var(--color-horizon-blue-10)]'"
                              @click="selectRsiVerifiedAtHour(h - 1)"
                            >
                              {{ String(h - 1).padStart(2, '0') }}
                            </button>
                          </div>
                        </div>

                        <div class="w-24 space-y-1">
                          <div class="text-[11px] text-[var(--color-text-secondary)]">
                            Minute
                          </div>

                          <button
                            ref="rsiVerifiedAtMinuteButton"
                            type="button"
                            class="hz-input flex w-24 items-center justify-between bg-[color:var(--horizon-void-900)] px-3 py-2 text-left"
                            @click="toggleRsiVerifiedAtMinuteMenu"
                          >
                            <span>{{ String(rsiVerifiedAtMinute).padStart(2, '0') }}</span>
                            <span class="text-[10px] text-[var(--color-text-secondary)]">▾</span>
                          </button>

                          <div
                            v-if="rsiVerifiedAtMinuteMenuOpen"
                            class="fixed z-50 max-h-40 overflow-y-auto rounded-lg border border-white/10 bg-[color:var(--horizon-void-800)] p-1 shadow-2xl"
                            :style="rsiVerifiedAtMinuteMenuStyle"
                          >
                            <button
                              v-for="m in 60"
                              :key="m"
                              type="button"
                              class="w-full rounded-md px-2 py-1 text-left text-sm"
                              :class="(rsiVerifiedAtMinute === (m - 1))
                                ? 'bg-[color:var(--horizon-sunset-blue)] text-horizon-white'
                                : 'text-[var(--color-text-primary)] hover:bg-[var(--color-horizon-blue-10)]'"
                              @click="selectRsiVerifiedAtMinute(m - 1)"
                            >
                              {{ String(m - 1).padStart(2, '0') }}
                            </button>
                          </div>
                        </div>

                        <div class="ml-auto flex flex-wrap gap-2">
                          <button
                            type="button"
                            class="rounded-lg border border-white/10 bg-white/[0.035] px-3 py-2 text-sm text-text-secondary transition hover:border-[color:var(--horizon-sunset-blue)]/35 hover:text-horizon-white"
                            @click="setRsiVerifiedAtNow"
                          >
                            Now
                          </button>

                          <button
                            type="button"
                            class="rounded-lg border border-red-300/20 bg-red-300/10 px-3 py-2 text-sm text-red-100 transition hover:bg-red-300/15"
                            @click="clearRsiVerifiedAt"
                          >
                            Clear
                          </button>
                        </div>
                      </div>

                      <div class="text-xs text-text-muted">
                        Clearing this marks the user as not RSI verified.
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <!-- Profile fields -->
            <section class="rounded-[1.75rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[color:var(--horizon-void-700)]/70 p-5 shadow-[0_0_28px_rgba(30,64,175,0.10)]">
              <div class="mb-5">
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                  Profile Fields
                </div>

                <h3 class="mt-1 text-xl font-black text-horizon-white">
                  Availability + Biography
                </h3>

                <p class="mt-1 text-sm text-text-secondary">
                  Update timezone, availability, LOA note, and public profile bio.
                </p>
              </div>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-[1.25rem] border border-white/10 bg-white/[0.025] p-4">
                  <label class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    Timezone
                  </label>

                  <input
                    v-model="form.timezone"
                    class="hz-input"
                    placeholder="America/Chicago, UTC, EU evening..."
                  />
                </div>

                <div class="rounded-[1.25rem] border border-white/10 bg-white/[0.025] p-4">
                  <label class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    Availability Status
                  </label>

                  <input
                    v-model="form.availability_status"
                    class="hz-input"
                    placeholder="Available, limited, LOA..."
                  />
                </div>

                <div class="rounded-[1.25rem] border border-white/10 bg-white/[0.025] p-4 md:col-span-2">
                  <label class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    LOA Note
                  </label>

                  <textarea
                    v-model="form.loa_note"
                    class="hz-textarea min-h-28"
                    placeholder="Leave of absence note..."
                  ></textarea>
                </div>

                <div class="rounded-[1.25rem] border border-white/10 bg-white/[0.025] p-4 md:col-span-2">
                  <label class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-text-muted">
                    Bio
                  </label>

                  <textarea
                    v-model="form.bio"
                    class="hz-textarea min-h-36"
                    placeholder="Member biography..."
                  ></textarea>
                </div>
              </div>
            </section>

            <!-- Roles -->
            <section class="rounded-[1.75rem] border border-[color:var(--horizon-sunset-indigo)]/30 bg-[color:var(--horizon-void-700)]/80 p-5 shadow-[0_0_28px_rgba(67,56,202,0.12)]">
              <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                  <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                    Role Assignments
                  </div>

                  <h3 class="mt-1 text-xl font-black text-horizon-white">
                    Platform Roles
                  </h3>

                  <p class="mt-1 text-sm text-text-secondary">
                    Assign or remove user roles, then save role assignments separately.
                  </p>
                </div>

                <HorizonButton
                  variant="ghost"
                  size="sm"
                  @click="saveUserRoles"
                >
                  Save Roles
                </HorizonButton>
              </div>

              <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label
                  v-for="role in sortedRoles"
                  :key="role.id"
                  class="flex cursor-pointer items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.025] p-3 transition hover:border-[color:var(--horizon-sunset-blue)]/30 hover:bg-white/[0.045]"
                >
                  <input
                    v-model="form.role_ids"
                    type="checkbox"
                    :value="role.id"
                    class="h-4 w-4 accent-[color:var(--horizon-sunset-blue)]"
                  />

                  <span class="text-sm font-semibold text-text-secondary">
                    {{ role.name }}
                  </span>
                </label>
              </div>
            </section>
          </div>

          <!-- ACTION ROW -->
          <footer class="sticky bottom-0 z-20 mt-6 rounded-[1.75rem] border border-[color:var(--horizon-sunset-blue)]/25 bg-[linear-gradient(135deg,var(--horizon-void-700),var(--horizon-void-900))] p-4 shadow-[0_-12px_48px_rgba(0,0,0,0.35)]">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-[color:var(--horizon-text-secondary)]">
                  Finalize User Changes
                </div>

                <p class="mt-1 text-sm text-text-secondary">
                  Save profile fields, save roles separately, or unverify the user if they must re-complete verification.
                </p>
              </div>

              <div class="flex flex-wrap gap-2 lg:justify-end">
                <HorizonButton
                  variant="ghost"
                  size="sm"
                  @click="closeUserEditor"
                >
                  Cancel
                </HorizonButton>

                <HorizonButton
                  variant="danger"
                  size="sm"
                  @click="unverifyUser"
                >
                  Unverify User
                </HorizonButton>

                <HorizonButton
                  variant="primary"
                  size="sm"
                  @click="saveUser"
                >
                  Save Changes
                </HorizonButton>
              </div>
            </div>
          </footer>

        </div>
      </div>
    </div>


  </div>

  <HorizonConfirmDialog
    ref="unverifyConfirmDialog"
    title="Unverify User"
    confirm-label="Unverify"
    cancel-label="Cancel"
    variant="danger"
    message="They will be forced back through verification and their API tokens will be revoked."
    @confirm="confirmUnverifyUser"
  />
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import HorizonButton from '@/Components/HorizonButton.vue';
import HorizonConfirmDialog from '@/Components/HorizonConfirmDialog.vue';

import { getHighestOrgRoleSlug, getOrgRoleColor } from '@/roleColors'

const props = defineProps({
  users: Object,
  roles: Array,
  filters: Object,
});

const users = computed(() => props.users);
const search = ref(props.filters?.search ?? '');

let searchDebounceId = null;

function runSearch(value) {
  const trimmed = String(value ?? '').trim();

  router.visit(route('admin.dashboard'), {
    data: trimmed ? { search: trimmed } : {},
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: ['users', 'filters'],
  });
}

/* ============================================================
   COMPUTED
============================================================ */
const totalUsers = computed(() => users.value?.total ?? 0);
const currentPage = computed(() => users.value?.current_page ?? 1);
const lastPage = computed(() => users.value?.last_page ?? 1);
const prevUrl = computed(() => users.value?.prev_page_url || null);
const nextUrl = computed(() => users.value?.next_page_url || null);

const roleSortOrder = [
  'director',
  'tech_director',
  'grand_admiral',
  'admiral',
  'wing_commander',
  'cit',
  'commander',
  'lieutenant',
  'member',
  'tech_team',
  'viewer',
];

const roleOrderIndex = new Map(roleSortOrder.map((slug, index) => [slug, index]));

const rankOptions = [
  { label: 'Member', value: 'member', level: 1 },
  { label: 'Lieutenant', value: 'lieutenant', level: 2 },
  { label: 'C.I.T (Commander in Training)', value: 'cit', level: 3 },
  { label: 'Commander', value: 'commander', level: 4 },
  { label: 'Wing Commander', value: 'wing_commander', level: 5 },
  { label: 'Admiral', value: 'admiral', level: 6 },
  { label: 'Grand Admiral', value: 'grand_admiral', level: 7 },
  { label: 'Director', value: 'director', level: 8 },
];

const rankLabelBySlug = new Map(rankOptions.map(o => [o.value, o.label]));
const rankLevelBySlug = new Map(rankOptions.map(o => [o.value, o.level]));

const globalStatusOptions = [
  { label: 'Pending', value: 'pending' },
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
];

const globalStatusLabelByValue = new Map(globalStatusOptions.map(o => [o.value, o.label]));

function titleCaseIdentifier(value) {
  const raw = String(value ?? '').trim();
  if (!raw) return '';

  return raw
    .split('_')
    .filter(Boolean)
    .map(part => part.slice(0, 1).toUpperCase() + part.slice(1))
    .join(' ');
}

function formatRankLabel(value) {
  if (!value) return 'None';
  return rankLabelBySlug.get(value) ?? titleCaseIdentifier(value);
}

function formatGlobalStatusLabel(value) {
  if (!value) return 'Unset';
  return globalStatusLabelByValue.get(value) ?? titleCaseIdentifier(value);
}

function toDatetimeLocal(value) {
  if (!value) return '';

  const d = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(d.getTime())) return '';

  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function toBackendDatetimeFromLocalInput(value) {
  if (!value) return null;
  return `${String(value).replace('T', ' ')}:00`;
}

function parseLocalDatetime(value) {
  if (!value) return null;
  const [datePart, timePart] = String(value).split('T');
  if (!datePart || !timePart) return null;

  const [year, month, day] = datePart.split('-').map((v) => Number(v));
  const [hour, minute] = timePart.split(':').map((v) => Number(v));

  if (!year || !month || !day) return null;
  if (Number.isNaN(hour) || Number.isNaN(minute)) return null;

  return { year, month, day, hour, minute };
}

function pad2(n) {
  return String(n).padStart(2, '0');
}

function buildLocalDatetime(parts) {
  return `${parts.year}-${pad2(parts.month)}-${pad2(parts.day)}T${pad2(parts.hour)}:${pad2(parts.minute)}`;
}

function compareRoles(a, b) {
  const aKey = a?.slug ?? '';
  const bKey = b?.slug ?? '';

  const aOrder = roleOrderIndex.has(aKey) ? roleOrderIndex.get(aKey) : Number.POSITIVE_INFINITY;
  const bOrder = roleOrderIndex.has(bKey) ? roleOrderIndex.get(bKey) : Number.POSITIVE_INFINITY;

  if (aOrder !== bOrder) return aOrder - bOrder;

  const aName = String(a?.name ?? '').toLowerCase();
  const bName = String(b?.name ?? '').toLowerCase();
  return aName.localeCompare(bName);
}

const sortedRoles = computed(() => [...(props.roles ?? [])].sort(compareRoles));

function formatRoleList(userRoles) {
  const sorted = [...(userRoles ?? [])].sort(compareRoles);
  return sorted.map(r => r.name).join(', ');
}

function userNameColor(u) {
  const slug = getHighestOrgRoleSlug(u?.roles, u?.rank)
  return getOrgRoleColor(slug)
}

/* ============================================================
   SEARCH
============================================================ */
function applySearch() {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId);
    searchDebounceId = null;
  }

  runSearch(search.value);
}

function clearSearch() {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId);
    searchDebounceId = null;
  }

  search.value = '';
  runSearch('');
}

watch(search, (value) => {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId);
  }

  searchDebounceId = setTimeout(() => {
    runSearch(value);
  }, 250);
});

/* ============================================================
   PAGINATION
============================================================ */
function goTo(url) {
  if (url) {
    router.visit(url, { preserveScroll: true });
  }
}

/* ============================================================
   MODAL & FORM
============================================================ */
const editingUser = ref(null);

function handleKeydown(event) {
  if (event.key !== 'Escape') return;
  if (!editingUser.value) return;

  closeUserEditor();
}

const form = ref({
  id: null,
  rsi_handle: '',
  rank: '',
  rank_level: 1,
  global_status: '',
  rsi_verified_at: null,
  timezone: '',
  availability_status: '',
  loa_note: '',
  bio: '',
  role_ids: [],
});

const rsiVerifiedAtLocal = ref('');

const rsiVerifiedAtPickerOpen = ref(false);
const rsiVerifiedAtPickerYear = ref(new Date().getFullYear());
const rsiVerifiedAtPickerMonthIndex = ref(new Date().getMonth());
const rsiVerifiedAtPickerContainer = ref(null);

const rsiVerifiedAtHourMenuOpen = ref(false);
const rsiVerifiedAtMinuteMenuOpen = ref(false);
const rsiVerifiedAtHourButton = ref(null);
const rsiVerifiedAtMinuteButton = ref(null);
const rsiVerifiedAtHourMenuStyle = ref({});
const rsiVerifiedAtMinuteMenuStyle = ref({});

const weekdayLabels = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const monthLabels = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];

const rsiVerifiedAtMonthLabel = computed(() => monthLabels[rsiVerifiedAtPickerMonthIndex.value] ?? '');

const rsiVerifiedAtDisplay = computed(() => {
  if (!rsiVerifiedAtLocal.value) return 'Not verified';
  return rsiVerifiedAtLocal.value.replace('T', ' ');
});

function ensureRsiVerifiedAtParts() {
  const parsed = parseLocalDatetime(rsiVerifiedAtLocal.value);
  if (parsed) return parsed;

  const now = new Date();
  return {
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    day: now.getDate(),
    hour: 0,
    minute: 0,
  };
}

const rsiVerifiedAtHour = computed({
  get() {
    return ensureRsiVerifiedAtParts().hour;
  },
  set(next) {
    const parts = ensureRsiVerifiedAtParts();
    parts.hour = Number(next);
    rsiVerifiedAtLocal.value = buildLocalDatetime(parts);
  },
});

const rsiVerifiedAtMinute = computed({
  get() {
    return ensureRsiVerifiedAtParts().minute;
  },
  set(next) {
    const parts = ensureRsiVerifiedAtParts();
    parts.minute = Number(next);
    rsiVerifiedAtLocal.value = buildLocalDatetime(parts);
  },
});

function openRsiVerifiedAtPicker() {
  rsiVerifiedAtPickerOpen.value = true;
  const parts = parseLocalDatetime(rsiVerifiedAtLocal.value);
  if (parts) {
    rsiVerifiedAtPickerYear.value = parts.year;
    rsiVerifiedAtPickerMonthIndex.value = parts.month - 1;
    return;
  }

  const now = new Date();
  rsiVerifiedAtPickerYear.value = now.getFullYear();
  rsiVerifiedAtPickerMonthIndex.value = now.getMonth();
}

function closeRsiVerifiedAtPicker() {
  rsiVerifiedAtHourMenuOpen.value = false;
  rsiVerifiedAtMinuteMenuOpen.value = false;
  rsiVerifiedAtPickerOpen.value = false;
}

function toggleRsiVerifiedAtPicker() {
  if (rsiVerifiedAtPickerOpen.value) {
    closeRsiVerifiedAtPicker();
    return;
  }

  openRsiVerifiedAtPicker();
}

function goRsiVerifiedAtPrevMonth() {
  if (rsiVerifiedAtPickerMonthIndex.value === 0) {
    rsiVerifiedAtPickerMonthIndex.value = 11;
    rsiVerifiedAtPickerYear.value -= 1;
    return;
  }

  rsiVerifiedAtPickerMonthIndex.value -= 1;
}

function goRsiVerifiedAtNextMonth() {
  if (rsiVerifiedAtPickerMonthIndex.value === 11) {
    rsiVerifiedAtPickerMonthIndex.value = 0;
    rsiVerifiedAtPickerYear.value += 1;
    return;
  }

  rsiVerifiedAtPickerMonthIndex.value += 1;
}

function selectRsiVerifiedAtDay(day) {
  const parts = ensureRsiVerifiedAtParts();
  parts.year = rsiVerifiedAtPickerYear.value;
  parts.month = rsiVerifiedAtPickerMonthIndex.value + 1;
  parts.day = Number(day);
  rsiVerifiedAtLocal.value = buildLocalDatetime(parts);
}

function setRsiVerifiedAtNow() {
  const now = new Date();
  rsiVerifiedAtLocal.value = buildLocalDatetime({
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    day: now.getDate(),
    hour: now.getHours(),
    minute: now.getMinutes(),
  });
  rsiVerifiedAtPickerYear.value = now.getFullYear();
  rsiVerifiedAtPickerMonthIndex.value = now.getMonth();
}

function clearRsiVerifiedAt() {
  rsiVerifiedAtLocal.value = '';
}

function computeFixedMenuStyle(triggerEl) {
  if (!triggerEl) return {};

  const rect = triggerEl.getBoundingClientRect();
  const menuHeight = 160;
  const margin = 10;

  const openUp = rect.bottom + menuHeight + margin > window.innerHeight;
  const top = openUp ? rect.top - menuHeight - 6 : rect.bottom + 6;

  const width = rect.width;
  let left = rect.left;

  if (left + width > window.innerWidth - margin) {
    left = window.innerWidth - margin - width;
  }

  if (left < margin) left = margin;

  return {
    top: `${Math.max(margin, top)}px`,
    left: `${left}px`,
    width: `${width}px`,
  };
}

function toggleRsiVerifiedAtHourMenu() {
  rsiVerifiedAtMinuteMenuOpen.value = false;

  if (rsiVerifiedAtHourMenuOpen.value) {
    rsiVerifiedAtHourMenuOpen.value = false;
    return;
  }

  rsiVerifiedAtHourMenuStyle.value = computeFixedMenuStyle(rsiVerifiedAtHourButton.value);
  rsiVerifiedAtHourMenuOpen.value = true;
}

function toggleRsiVerifiedAtMinuteMenu() {
  rsiVerifiedAtHourMenuOpen.value = false;

  if (rsiVerifiedAtMinuteMenuOpen.value) {
    rsiVerifiedAtMinuteMenuOpen.value = false;
    return;
  }

  rsiVerifiedAtMinuteMenuStyle.value = computeFixedMenuStyle(rsiVerifiedAtMinuteButton.value);
  rsiVerifiedAtMinuteMenuOpen.value = true;
}

function selectRsiVerifiedAtHour(value) {
  rsiVerifiedAtHour.value = Number(value);
  rsiVerifiedAtHourMenuOpen.value = false;
}

function selectRsiVerifiedAtMinute(value) {
  rsiVerifiedAtMinute.value = Number(value);
  rsiVerifiedAtMinuteMenuOpen.value = false;
}

const rsiVerifiedAtCalendarCells = computed(() => {
  const year = rsiVerifiedAtPickerYear.value;
  const monthIndex = rsiVerifiedAtPickerMonthIndex.value;

  const first = new Date(year, monthIndex, 1);
  const startWeekday = (first.getDay() + 6) % 7;
  const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

  const selected = parseLocalDatetime(rsiVerifiedAtLocal.value);

  const cells = [];
  const total = 42;

  for (let i = 0; i < total; i++) {
    const day = i - startWeekday + 1;
    const isBlank = day < 1 || day > daysInMonth;
    const isSelected =
      !isBlank &&
      !!selected &&
      selected.year === year &&
      selected.month === monthIndex + 1 &&
      selected.day === day;

    cells.push({
      key: `${year}-${monthIndex}-${i}`,
      day: isBlank ? '' : day,
      isBlank,
      isSelected,
    });
  }

  return cells;
});

function handleRsiVerifiedAtPickerClickOutside(event) {
  if (!rsiVerifiedAtPickerOpen.value) return;
  const el = rsiVerifiedAtPickerContainer.value;
  if (!el) return;
  if (el.contains(event.target)) return;

  closeRsiVerifiedAtPicker();
}

function handleRsiVerifiedAtViewportChanged() {
  if (!rsiVerifiedAtPickerOpen.value) return;

  rsiVerifiedAtHourMenuOpen.value = false;
  rsiVerifiedAtMinuteMenuOpen.value = false;
}

watch(
  () => rsiVerifiedAtLocal.value,
  (nextValue) => {
    form.value.rsi_verified_at = toBackendDatetimeFromLocalInput(nextValue);
  }
);

watch(
  () => form.value.rank,
  (nextRank) => {
    if (!nextRank) {
      form.value.rank_level = 1;
      return;
    }

    const mapped = rankLevelBySlug.get(nextRank);
    if (mapped) {
      form.value.rank_level = mapped;
    }
  }
);

function openUserEditor(user) {
  editingUser.value = { ...user };

  closeRsiVerifiedAtPicker();

  rsiVerifiedAtLocal.value = toDatetimeLocal(user.rsi_verified_at);

  form.value = {
    id: user.id,
    rsi_handle: user.rsi_handle || '',
    rank: user.rank || '',
    rank_level: user.rank_level || 1,
    global_status: user.global_status || '',
    rsi_verified_at: toBackendDatetimeFromLocalInput(rsiVerifiedAtLocal.value),
    timezone: user.timezone || '',
    availability_status: user.availability_status || '',
    loa_note: user.loa_note || '',
    bio: user.bio || '',
    role_ids: user.roles?.map(r => r.id) ?? [],
  };
}

function closeUserEditor() {
  editingUser.value = null;
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
  window.addEventListener('pointerdown', handleRsiVerifiedAtPickerClickOutside);
  window.addEventListener('resize', handleRsiVerifiedAtViewportChanged);
  window.addEventListener('scroll', handleRsiVerifiedAtViewportChanged, true);
});

onBeforeUnmount(() => {
  if (searchDebounceId) {
    clearTimeout(searchDebounceId);
    searchDebounceId = null;
  }

  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('pointerdown', handleRsiVerifiedAtPickerClickOutside);
  window.removeEventListener('resize', handleRsiVerifiedAtViewportChanged);
  window.removeEventListener('scroll', handleRsiVerifiedAtViewportChanged, true);
});

const unverifyConfirmDialog = ref(null);

/* ============================================================
   SAVE ACTIONS
============================================================ */
function unverifyUser() {
  if (!form.value.id) return;
  unverifyConfirmDialog.value?.show();
}

function confirmUnverifyUser({ close }) {
  const userId = form.value.id;
  if (!userId) {
    close();
    return;
  }

  router.post(
    route('admin.users.unverify'),
    { id: userId },
    {
      preserveScroll: true,
      onSuccess: () => {
        close();
        closeUserEditor();
        router.visit(window.location.href, { preserveScroll: true });
      },
    }
  );
}

function saveUser() {
  router.post(route('admin.users.update'), form.value, {
    preserveScroll: true,
    onSuccess: () => {
      closeUserEditor();
      router.visit(window.location.href, { preserveScroll: true });
    },
  });
}

function saveUserRoles() {
  router.post(
    route('admin.users.updateRoles'),
    {
      id: form.value.id,
      role_ids: form.value.role_ids,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        router.visit(window.location.href, { preserveScroll: true });
      },
    }
  );
}
</script>

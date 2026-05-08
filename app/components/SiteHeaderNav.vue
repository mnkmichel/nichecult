<script setup lang="ts">
const props = defineProps<{
  title: string
  active: 'home' | 'parfum' | 'duftkuration' | 'samples'
}>()

const everDone = ref(false)
const showSamples = computed(() => props.active === 'samples' || everDone.value)

const syncKurationState = () => {
  // Keep visibility even for users that only have one of the legacy/new flags.
  everDone.value = localStorage.getItem('nichecult_kuration_ever_done') === '1'
    || localStorage.getItem('nichecult_duftkuration_done') === '1'
}

const logout = async () => {
  localStorage.removeItem('nichecult_token')
  localStorage.removeItem('nichecult_duftkuration_done')
  await navigateTo('/login')
}

onMounted(() => {
  syncKurationState()
  window.addEventListener('storage', syncKurationState)
  window.addEventListener('nichecult:kuration-completed', syncKurationState)
})

onBeforeUnmount(() => {
  window.removeEventListener('storage', syncKurationState)
  window.removeEventListener('nichecult:kuration-completed', syncKurationState)
})
</script>

<template>
  <header class="mb-7 space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-xs uppercase tracking-[0.28em] text-amber-800">Nice Cult</p>
        <h1 class="mt-2 text-3xl font-bold md:text-4xl">{{ title }}</h1>
      </div>

      <button class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-stone-50" @click="logout">
        Logout
      </button>
    </div>

    <nav class="inline-flex rounded-2xl border border-stone-300 bg-white p-1 shadow-sm">
      <NuxtLink v-if="active !== 'home'" to="/" class="rounded-xl px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100">Startseite</NuxtLink>
      <span v-else class="rounded-xl bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Startseite</span>

      <NuxtLink v-if="active !== 'parfum'" to="/parfum" class="rounded-xl px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100">Parfum</NuxtLink>
      <span v-else class="rounded-xl bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Parfum</span>

      <NuxtLink v-if="active !== 'duftkuration'" to="/duftkuration" class="rounded-xl px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100">Parfum-Kuration</NuxtLink>
      <span v-else class="rounded-xl bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Parfum-Kuration</span>

      <template v-if="showSamples">
        <NuxtLink v-if="active !== 'samples'" to="/sets" class="rounded-xl px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100">Ihre Samples</NuxtLink>
        <span v-else class="rounded-xl bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Ihre Samples</span>
      </template>
    </nav>
  </header>
</template>
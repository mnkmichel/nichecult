<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const { me, listSampleSets } = useAuthApi()

const loading = ref(true)
const error = ref('')
const profile = ref<Record<string, any> | null>(null)
const sampleSets = ref<Array<Record<string, any>>>([])

const statusLabel = (value: string) => {
  if (value === 'completed') {
    return 'Abgeschlossen'
  }
  if (value === 'delivered') {
    return 'Geliefert'
  }
  return 'Offen'
}

const loadPageData = async () => {
  loading.value = true
  error.value = ''

  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/login')
      return
    }

    const [profileRes, setsRes] = await Promise.all([
      me(token),
      listSampleSets(token),
    ])

    if (!profileRes.ok || !profileRes.user) {
      localStorage.removeItem('nichecult_token')
      await navigateTo('/login')
      return
    }

    profile.value = profileRes.user
    sampleSets.value = setsRes.sampleSets || []
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Fehler beim Laden der Sample-Sets'
  } finally {
    loading.value = false
  }
}

const logout = async () => {
  localStorage.removeItem('nichecult_token')
  await navigateTo('/login')
}

onMounted(loadPageData)
</script>

<template>
  <main class="min-h-screen bg-stone-100 px-4 py-8">
    <div class="mx-auto max-w-6xl">
      <header class="mb-8 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-xs uppercase tracking-[0.2em] text-amber-700">Nichecult</p>
            <h1 class="text-3xl font-bold">Meine Sample-Sets</h1>
          </div>

          <button class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-stone-50" @click="logout">
            Logout
          </button>
        </div>

        <nav class="inline-flex rounded-2xl border border-stone-300 bg-white p-1 shadow-sm">
          <NuxtLink to="/parfum" class="rounded-xl px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100">Parfum</NuxtLink>
          <NuxtLink to="/duftkuration" class="rounded-xl px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100">Duft-Kuration</NuxtLink>
          <span class="rounded-xl bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Ihre Samples</span>
        </nav>
      </header>

      <div v-if="loading" class="rounded-xl border border-stone-200 bg-white p-5 text-stone-600">Lade Daten...</div>
      <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-5 text-red-700">{{ error }}</div>

      <section v-else class="space-y-6">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
          Eingeloggt als {{ profile?.email }}
        </div>

        <div v-if="sampleSets.length === 0" class="rounded-xl border border-stone-200 bg-white p-5 text-stone-600">
          Noch keine Sample-Sets zugewiesen.
        </div>

        <div v-else class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="item in sampleSets" :key="item.user_sample_set_id" class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
            <img v-if="item.image_url" :src="item.image_url" alt="Setbild" class="h-52 w-full object-cover" />
            <div class="p-5">
              <p class="text-xs uppercase tracking-[0.2em] text-stone-500">{{ item.perfume_count }} Parfums</p>
              <h2 class="mt-2 text-xl font-semibold">{{ item.title }}</h2>
              <p v-if="item.description" class="mt-2 text-sm text-stone-600">{{ item.description }}</p>
              <p class="mt-3 text-sm text-stone-500">Status: {{ statusLabel(item.set_status) }}</p>

              <NuxtLink :to="`/sets/${item.user_sample_set_id}`" class="mt-5 block rounded-xl bg-stone-900 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-stone-800">
                Set oeffnen
              </NuxtLink>
            </div>
          </article>
        </div>
      </section>
    </div>
  </main>
</template>

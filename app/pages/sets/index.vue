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

const openSet = async (userSampleSetId: unknown) => {
  const id = Number(userSampleSetId)
  if (!Number.isFinite(id) || id <= 0) {
    error.value = 'Dieses Sample-Set konnte nicht geoeffnet werden (ungueltige ID).'
    return
  }

  await navigateTo(`/sets/${id}`)
}

onMounted(loadPageData)
</script>

<template>
  <main class="nc-page">
    <div class="nc-page-frame">
      <SiteHeaderNav title="Ihre Samples" active="samples" />

      <div v-if="loading" class="nc-shell p-5 text-stone-600">Lade Daten...</div>
      <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-5 text-red-700">{{ error }}</div>

      <section v-else class="space-y-6">
        <div class="nc-shell border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
          Eingeloggt als {{ profile?.email }}
        </div>

        <div v-if="sampleSets.length === 0" class="nc-shell p-5 text-stone-600">
          Noch keine Sample-Sets zugewiesen.
        </div>

        <div v-else class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="item in sampleSets" :key="item.user_sample_set_id" class="nc-shell overflow-hidden bg-white/90">
            <img v-if="item.image_url" :src="item.image_url" alt="Setbild" class="h-52 w-full object-cover" />
            <div class="p-5">
              <p class="text-xs uppercase tracking-[0.2em] text-stone-500">{{ item.perfume_count }} Parfums</p>
              <h2 class="mt-2 text-xl font-semibold">{{ item.title }}</h2>
              <p v-if="item.description" class="mt-2 text-sm text-stone-600">{{ item.description }}</p>
              <p class="mt-3 text-sm text-stone-500">Status: {{ statusLabel(item.set_status) }}</p>

              <button
                type="button"
                class="mt-5 block w-full rounded-xl bg-stone-900 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-stone-800"
                @click="openSet(item.user_sample_set_id)"
              >
                Set oeffnen
              </button>
            </div>
          </article>
        </div>
      </section>
    </div>
  </main>
</template>
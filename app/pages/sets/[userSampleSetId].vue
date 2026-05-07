<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const route = useRoute()
const { getSampleSetDetail, savePerfumeRating } = useAuthApi()

const loading = ref(true)
const savingId = ref<number | null>(null)
const error = ref('')
const success = ref('')
const sampleSet = ref<Record<string, any> | null>(null)
const perfumes = ref<Array<Record<string, any>>>([])
const formState = reactive<Record<number, {
  overallScore: number
  longevityScore: number
  sillageScore: number
  mood: string
  occasion: string
}>>({})

const userSampleSetId = computed(() => Number(route.params.userSampleSetId || 0))

const ensureFormState = () => {
  for (const perfume of perfumes.value) {
    formState[perfume.perfume_id] = {
      overallScore: perfume.overall_score ?? 7,
      longevityScore: perfume.longevity_score ?? 7,
      sillageScore: perfume.sillage_score ?? 7,
      mood: '',
      occasion: '',
    }
  }
}

const loadData = async () => {
  loading.value = true
  error.value = ''

  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/login')
      return
    }

    const res = await getSampleSetDetail(token, userSampleSetId.value)
    sampleSet.value = res.sampleSet || null
    perfumes.value = res.perfumes || []
    ensureFormState()
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Fehler beim Laden des Sets'
  } finally {
    loading.value = false
  }
}

const savePerfume = async (perfumeId: number) => {
  error.value = ''
  success.value = ''
  savingId.value = perfumeId

  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/login')
      return
    }

    const state = formState[perfumeId]
    const res = await savePerfumeRating(token, {
      userSampleSetId: userSampleSetId.value,
      perfumeId,
      overallScore: state.overallScore,
      longevityScore: state.longevityScore,
      sillageScore: state.sillageScore,
      answers: {
        mood: state.mood,
        occasion: state.occasion,
      },
    })

    if (!res.ok) {
      error.value = res.error || 'Bewertung konnte nicht gespeichert werden'
      return
    }

    success.value = 'Bewertung gespeichert.'
    await loadData()
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Fehler beim Speichern'
  } finally {
    savingId.value = null
  }
}

onMounted(loadData)
</script>

<template>
  <main class="min-h-screen bg-stone-100 px-4 py-8">
    <div class="mx-auto max-w-6xl">
      <NuxtLink to="/sets" class="mb-4 inline-block text-sm text-stone-700 underline">Zurueck zu meinen Sets</NuxtLink>

      <div v-if="loading" class="rounded-xl border border-stone-200 bg-white p-5 text-stone-600">Lade Set...</div>
      <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 p-5 text-red-700">{{ error }}</div>

      <section v-else-if="sampleSet" class="space-y-6">
        <div class="overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm">
          <img v-if="sampleSet.image_url" :src="sampleSet.image_url" alt="Setbild" class="h-72 w-full object-cover" />
          <div class="p-6">
            <p class="text-xs uppercase tracking-[0.2em] text-amber-700">Sample-Set</p>
            <h1 class="mt-2 text-3xl font-bold">{{ sampleSet.title }}</h1>
            <p v-if="sampleSet.description" class="mt-3 max-w-3xl text-stone-600">{{ sampleSet.description }}</p>
          </div>
        </div>

        <p v-if="success" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700">{{ success }}</p>

        <div class="grid gap-6">
          <article v-for="perfume in perfumes" :key="perfume.perfume_id" class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-[320px_1fr]">
              <div>
                <img v-if="perfume.image_url" :src="perfume.image_url" alt="Parfumbild" class="h-80 w-full rounded-2xl object-cover" />
              </div>

              <div>
                <p class="text-xs uppercase tracking-[0.2em] text-stone-500">Parfum {{ perfume.sort_order }}</p>
                <h2 class="mt-2 text-2xl font-semibold">{{ perfume.name }}</h2>
                <p v-if="perfume.brand_name" class="mt-1 text-sm text-stone-500">{{ perfume.brand_name }}</p>
                <p v-if="perfume.description" class="mt-4 text-sm leading-6 text-stone-600">{{ perfume.description }}</p>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                  <label class="block">
                    <span class="text-sm font-semibold">Gesamteindruck: {{ formState[perfume.perfume_id]?.overallScore }}</span>
                    <input v-model.number="formState[perfume.perfume_id].overallScore" type="range" min="1" max="10" class="mt-2 w-full" />
                  </label>
                  <label class="block">
                    <span class="text-sm font-semibold">Haltbarkeit: {{ formState[perfume.perfume_id]?.longevityScore }}</span>
                    <input v-model.number="formState[perfume.perfume_id].longevityScore" type="range" min="1" max="10" class="mt-2 w-full" />
                  </label>
                  <label class="block">
                    <span class="text-sm font-semibold">Sillage: {{ formState[perfume.perfume_id]?.sillageScore }}</span>
                    <input v-model.number="formState[perfume.perfume_id].sillageScore" type="range" min="1" max="10" class="mt-2 w-full" />
                  </label>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                  <textarea v-model="formState[perfume.perfume_id].mood" class="rounded-xl border border-stone-300 px-4 py-3" rows="3" placeholder="Welche Stimmung beschreibt den Duft?"></textarea>
                  <textarea v-model="formState[perfume.perfume_id].occasion" class="rounded-xl border border-stone-300 px-4 py-3" rows="3" placeholder="Zu welchem Anlass passt der Duft?"></textarea>
                </div>

                <button class="mt-5 rounded-xl bg-stone-900 px-5 py-3 text-sm font-semibold text-white hover:bg-stone-800 disabled:opacity-60" :disabled="savingId === perfume.perfume_id" @click="savePerfume(perfume.perfume_id)">
                  {{ savingId === perfume.perfume_id ? 'Speichern...' : 'Parfum bewerten' }}
                </button>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </main>
</template>

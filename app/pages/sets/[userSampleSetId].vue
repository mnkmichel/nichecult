<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const route = useRoute()
const { getSampleSetDetail, savePerfumeRating } = useAuthApi()

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const sampleSet = ref<Record<string, any> | null>(null)
const perfumes = ref<Array<Record<string, any>>>([])
const favoritePerfumeId = ref<number | null>(null)

const currentPerfumeIdx = ref(0)
const currentQuestion = ref(0) // 0-4 = questions, 5 = thank-you per perfume
const showOverview = ref(true)

const userSampleSetId = computed(() => Number(route.params.userSampleSetId || 0))
const currentPerfume = computed(() => perfumes.value[currentPerfumeIdx.value])
const hasPerfumes = computed(() => perfumes.value.length > 0)

const priceLabel = (priceCents: unknown, sizeMl: unknown) => {
  const cents = Number(priceCents || 0)
  const ml = Number(sizeMl || 100)
  const eur = (Math.round(cents) / 100).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
  return `${ml} ml · ${eur} €`
}

const discountedCents = (priceCents: unknown) => Math.round(Number(priceCents || 0) * 0.85)

const isRated = (perfume: Record<string, any>) => perfume?.overall_score != null

const getFavoritePerfumeId = (items: Array<Record<string, any>>) => {
  let bestPerfumeId: number | null = null
  let bestScore = -1

  for (const perfume of items) {
    if (perfume?.overall_score == null) {
      continue
    }

    const score = (
      Number(perfume.overall_score || 0)
      + Number(perfume.longevity_score || 0)
      + Number(perfume.sillage_score || 0)
    ) / 3

    if (score > bestScore) {
      bestScore = score
      bestPerfumeId = Number(perfume.perfume_id)
    }
  }

  return bestPerfumeId
}

const isFavorite = (perfume: Record<string, any>) => Number(perfume?.perfume_id) === favoritePerfumeId.value

const statusHeading = (perfume: Record<string, any>) => {
  if (isFavorite(perfume)) {
    return 'Ihr aktueller Favorit'
  }
  return ''
}

const statusSubheading = (perfume: Record<string, any>) => {
  if (isRated(perfume)) {
    return 'Abgeschlossen'
  }
  return ''
}

const ratingButtonLabel = (perfume: Record<string, any>) => {
  return isRated(perfume) ? 'Erneut bewerten' : 'Bewerten'
}

const answerFor = (perfumeId: number) => {
  ensureAnswers(perfumeId)
  return answers[perfumeId]!
}

const startPerfumeRating = (perfumeIndex: number) => {
  showOverview.value = false
  currentPerfumeIdx.value = perfumeIndex
  currentQuestion.value = 0
  error.value = ''
}

const returnToOverview = async () => {
  showOverview.value = true
  currentQuestion.value = 0
  error.value = ''
  await loadData()
}

// Per-perfume answers
const answers = reactive<Record<number, {
  warmFrisch: number
  intensivDezent: number
  sweetness: number
  duftfamilien: string[]
  overallMatch: number
}>>({})

const ensureAnswers = (perfumeId: number) => {
  if (!answers[perfumeId]) {
    answers[perfumeId] = { warmFrisch: 2, intensivDezent: 2, sweetness: 2, duftfamilien: [], overallMatch: 2 }
  }
}

const duftfamilienOptions = [
  'Zitrus', 'Fruchtig', 'Blumig', 'Pudrig', 'Aquatisch',
  'Holzig', 'Grün', 'Balsamisch', 'Aromatisch', 'Erdig',
  'Rauchig', 'Würzig', 'Orientalisch', 'Ledrig', 'Gourmand',
]

const toggleDuftfamilie = (perfumeId: number, opt: string) => {
  const arr = answerFor(perfumeId).duftfamilien
  const idx = arr.indexOf(opt)
  if (idx >= 0) arr.splice(idx, 1)
  else arr.push(opt)
}

type SliderQ = { type: 'slider'; key: 'warmFrisch' | 'intensivDezent' | 'sweetness'; title: string; left: string; center: string; right: string }
type LabeledQ = { type: 'labeled'; key: 'overallMatch'; title: string; labels: string[] }
type MultiQ   = { type: 'multi'; key: 'duftfamilien'; title: string }
type Question = SliderQ | LabeledQ | MultiQ
type SliderKey = SliderQ['key']

const questions: Question[] = [
  { type: 'slider', key: 'warmFrisch',      title: 'Empfinden Sie den Duft als warm oder frisch?',      left: 'Warm',      center: 'Neutral', right: 'Frisch' },
  { type: 'slider', key: 'intensivDezent',  title: 'Empfinden Sie diesen Duft als intensiv oder dezent?', left: 'Intensiv',  center: 'Neutral', right: 'Dezent' },
  { type: 'slider', key: 'sweetness',       title: 'Empfinden Sie diesen Duft als süß?',                  left: 'Nicht süß', center: 'Neutral', right: 'Sehr süß' },
  { type: 'multi',  key: 'duftfamilien',    title: 'Welche Duftfamilien nehmen Sie wahr?' },
  { type: 'labeled',key: 'overallMatch',    title: 'Wie gut trifft dieser Duft Ihren Geschmack?', labels: ['Gar nicht', 'Wenig', 'Gut', 'Sehr gut', 'Perfekt'] },
]

const currentQ = computed<Question>(() => {
  const safeIndex = Math.max(0, Math.min(currentQuestion.value, questions.length - 1))
  return questions[safeIndex] as Question
})

const getScaleValue = (perfumeId: number, key: SliderKey) => answerFor(perfumeId)[key]
const setScaleValue = (perfumeId: number, key: SliderKey, value: number) => {
  answerFor(perfumeId)[key] = value
}

const canContinue = computed(() => {
  const p = currentPerfume.value
  if (!p) return false
  const q = currentQ.value
  if (!q) return false
  if (q.type === 'multi') return answerFor(p.perfume_id).duftfamilien.length > 0
  return true
})

const loadData = async () => {
  loading.value = true
  error.value = ''
  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) { await navigateTo('/login'); return }
    const res = await getSampleSetDetail(token, userSampleSetId.value)
    sampleSet.value = res.sampleSet || null
    perfumes.value = res.perfumes || []
    favoritePerfumeId.value = res.favoritePerfumeId ?? getFavoritePerfumeId(perfumes.value)
    for (const p of perfumes.value) ensureAnswers(p.perfume_id)
  } catch (e: any) {
    error.value = e?.message || 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}

const goNext = async () => {
  const perfume = currentPerfume.value
  if (!perfume) return

  if (currentQuestion.value < questions.length - 1) {
    currentQuestion.value++
    return
  }

  // Last question → save
  saving.value = true
  error.value = ''
  try {
    const token = localStorage.getItem('nichecult_token')!
    const a = answerFor(perfume.perfume_id)
    const overallScore = (a.overallMatch + 1) * 2 // 2,4,6,8,10
    await savePerfumeRating(token, {
      userSampleSetId: userSampleSetId.value,
      perfumeId: perfume.perfume_id,
      overallScore,
      longevityScore: overallScore,
      sillageScore: overallScore,
      answers: {
        warm_frisch: String(a.warmFrisch),
        intensiv_dezent: String(a.intensivDezent),
        sweetness: String(a.sweetness),
        duftfamilien: JSON.stringify(a.duftfamilien),
      },
    })

    perfume.overall_score = overallScore
    perfume.longevity_score = overallScore
    perfume.sillage_score = overallScore
    favoritePerfumeId.value = getFavoritePerfumeId(perfumes.value)
    currentQuestion.value = 5 // thank-you screen
  } catch (e: any) {
    error.value = e?.message || 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <div class="min-h-screen bg-[#f5f0e8] text-[#1a1612]">
    <!-- Loading -->
    <div v-if="loading" class="flex min-h-screen items-center justify-center text-stone-500">Lade Set...</div>
    <div v-else-if="error && !currentPerfume" class="flex min-h-screen items-center justify-center p-8 text-red-600">{{ error }}</div>

    <div v-else-if="showOverview && hasPerfumes" class="nc-page">
      <div class="nc-page-frame">
        <SiteHeaderNav title="Ihre Samples" active="samples" />

        <div class="mx-auto w-full max-w-6xl">
          <div class="mb-6">
            <h1 class="text-3xl font-semibold md:text-4xl">{{ sampleSet?.title || 'Ihre Samples' }}</h1>
          </div>

          <div class="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="(perfume, index) in perfumes"
              :key="perfume.perfume_id"
              class="mx-auto w-full max-w-[320px] overflow-hidden rounded-3xl border-2 border-[#bfa160] bg-[#f8f2e8] shadow-sm"
            >
              <div class="flex min-h-16 flex-col items-center justify-center px-4 pt-2 text-center text-[#b08a44]">
                <p class="min-h-6 text-base md:text-[18px]">{{ statusHeading(perfume) }}</p>
                <p class="min-h-6 text-lg md:text-[20px]">{{ statusSubheading(perfume) }}</p>
              </div>

              <img
                v-if="perfume.image_url"
                :src="perfume.image_url"
                alt="Parfumbild"
                class="mx-auto h-44 w-full max-w-[72%] object-contain md:h-52"
              >
              <div v-else class="mx-auto h-44 w-[72%] bg-[#ece4d3] md:h-52" />

              <div class="bg-[#d8c8ad] px-4 py-2.5 text-center">
                <p class="text-base leading-tight text-[#2a241f] md:text-[20px]">{{ perfume.brand_name }}</p>
                <p class="mt-1 text-[22px] leading-tight text-[#1e1a17] md:text-[30px]">{{ perfume.name }}</p>

                <div class="mt-1.5 text-sm text-[#1f1b18] md:text-[16px]">
                  <template v-if="isRated(perfume)">
                    {{ priceLabel(discountedCents(perfume.price_cents), perfume.size_ml) }}
                    <span class="block text-xs text-[#3e352d] md:text-sm">
                      (regulär {{ priceLabel(perfume.price_cents, perfume.size_ml) }})
                    </span>
                  </template>
                  <template v-else>
                    {{ priceLabel(perfume.price_cents, perfume.size_ml) }}
                  </template>
                </div>

                <button
                  type="button"
                  class="mt-2 inline-flex min-w-36 items-center justify-center rounded-xl bg-[#b99a57] px-4 py-1.5 text-sm font-medium text-[#1a1612] transition hover:brightness-95 md:text-[16px]"
                  @click="startPerfumeRating(index)"
                >
                  {{ ratingButtonLabel(perfume) }}
                </button>
              </div>
            </article>
          </div>
        </div>
      </div>
    </div>

    <!-- Per-perfume flow -->
    <div v-else-if="currentPerfume" class="nc-page flex flex-col">
      <div class="nc-page-frame flex flex-1 flex-col">
        <SiteHeaderNav title="Ihre Samples" active="samples" />

        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col">
          <div class="mb-6 rounded-2xl border border-[#d8ccb0] bg-[#faf7f2] p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#7a6040]">
              {{ sampleSet?.title || 'Sample-Set' }} · Parfum {{ currentPerfumeIdx + 1 }} von {{ perfumes.length }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
              <span
                v-for="(p, idx) in perfumes"
                :key="p.perfume_id"
                class="rounded-full border px-3 py-1 text-xs font-medium"
                :class="idx === currentPerfumeIdx
                  ? 'border-[#8e6c2a] bg-[#8e6c2a] text-white'
                  : 'border-[#c8b48a] bg-white text-[#1a1612]'"
              >
                {{ p.brand_name }} {{ p.name }}
              </span>
            </div>
          </div>

          <!-- Thank-you screen after saving a perfume -->
          <template v-if="currentQuestion === 5">
            <div class="flex flex-1 flex-col items-center justify-center text-center">
              <h1 class="text-3xl leading-tight md:text-5xl">
                Vielen Dank für Ihre Bewertung zu:
              </h1>
              <p class="mt-6 text-2xl font-semibold md:text-4xl">
                {{ currentPerfume.brand_name }} {{ currentPerfume.name }}
              </p>
              <p class="mt-6 max-w-lg text-lg text-[#4a3f2f] md:text-2xl">
                Ihre Bewertung geht in die Kuration des nächsten Sample-Sets ein.
              </p>
            </div>
            <div class="flex justify-end">
              <button
                type="button"
                class="rounded-xl bg-[#8e6c2a] px-8 py-4 text-base font-semibold text-white transition hover:brightness-110"
                @click="returnToOverview"
              >
                Zurück zur Übersicht
              </button>
            </div>
          </template>

          <!-- Question screen -->
          <template v-else>
            <!-- Perfume name subtitle -->
            <p class="mb-4 text-base font-medium text-[#7a6040] md:text-lg">
              {{ currentPerfume.brand_name }} {{ currentPerfume.name }}
            </p>

            <!-- Question title -->
            <h1 class="text-3xl leading-tight text-[#1a1612] md:text-5xl">
              {{ currentQ.title }}
            </h1>

            <!-- Error -->
            <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>

            <!-- Answer area -->
            <div class="mt-12 flex flex-1 flex-col justify-center md:mt-16">

            <!-- 5-point slider -->
            <template v-if="currentQ.type === 'slider'">
              <div class="mt-8">
                <div class="relative flex justify-between text-base font-medium md:text-lg">
                  <span>{{ (currentQ as any).left }}</span>
                  <span class="absolute left-1/2 -translate-x-1/2">{{ (currentQ as any).center }}</span>
                  <span>{{ (currentQ as any).right }}</span>
                </div>
                <div class="relative mt-5 flex items-center">
                  <div class="absolute left-0 right-0 h-px bg-[#b99a57]" />
                  <div class="relative flex w-full justify-between">
                    <button
                      v-for="i in 5"
                      :key="i"
                      type="button"
                      class="relative h-9 w-9 rounded-md border-2 transition"
                      :class="getScaleValue(currentPerfume.perfume_id, (currentQ as SliderQ).key) === (i - 1)
                        ? 'border-[#8e6c2a] bg-[#8e6c2a]'
                        : 'border-[#b99a57] bg-[#f5f0e8] hover:bg-[#e8ddc8]'"
                      @click="setScaleValue(currentPerfume.perfume_id, (currentQ as SliderQ).key, i - 1)"
                    />
                  </div>
                </div>
              </div>
            </template>

            <!-- Labeled 5-point slider (Wie gut trifft...) -->
            <template v-else-if="currentQ.type === 'labeled'">
              <div class="mt-8">
                <div class="relative flex justify-between text-base font-medium md:text-lg">
                  <span v-for="(lbl, li) in (currentQ as any).labels" :key="li">{{ lbl }}</span>
                </div>
                <div class="relative mt-5 flex items-center">
                  <div class="absolute left-0 right-0 h-px bg-[#b99a57]" />
                  <div class="relative flex w-full justify-between">
                    <button
                      v-for="i in 5"
                      :key="i"
                      type="button"
                      class="relative h-9 w-9 rounded-md border-2 transition"
                      :class="answerFor(currentPerfume.perfume_id).overallMatch === (i - 1)
                        ? 'border-[#8e6c2a] bg-[#8e6c2a]'
                        : 'border-[#b99a57] bg-[#f5f0e8] hover:bg-[#e8ddc8]'"
                      @click="answerFor(currentPerfume.perfume_id).overallMatch = i - 1"
                    />
                  </div>
                </div>
              </div>
            </template>

            <!-- Multi-select Duftfamilien -->
            <template v-else-if="currentQ.type === 'multi'">
              <div class="mt-6 rounded-2xl border border-[#d8ccb0] bg-[#faf7f2] p-5">
                <div class="grid grid-cols-5 gap-2">
                  <button
                    v-for="opt in duftfamilienOptions"
                    :key="opt"
                    type="button"
                    class="rounded-xl border px-2 py-2 text-center text-xs font-medium transition md:text-sm"
                    :class="answerFor(currentPerfume.perfume_id).duftfamilien.includes(opt)
                      ? 'border-[#8e6c2a] bg-[#8e6c2a] text-white'
                      : 'border-[#c8b48a] bg-white text-[#1a1612] hover:bg-[#f0e8d6]'"
                    @click="toggleDuftfamilie(currentPerfume.perfume_id, opt)"
                  >
                    {{ opt }}
                  </button>
                </div>
              </div>
            </template>
            </div>

            <!-- Navigation -->
            <div class="mt-10 flex justify-between">
              <button
                v-if="currentQuestion > 0"
                type="button"
                class="rounded-xl border border-[#c8b48a] px-6 py-3 text-sm font-medium text-[#5a4820] transition hover:bg-[#ede5d4]"
                @click="currentQuestion--"
              >
                Zurück
              </button>
              <div v-else />
              <button
                type="button"
                :disabled="!canContinue || saving"
                class="rounded-xl px-7 py-3 text-sm font-semibold transition disabled:opacity-40"
                :class="canContinue ? 'bg-[#b99a57] text-[#1a1612] hover:brightness-95' : 'bg-[#b99a57] text-[#1a1612]'"
                @click="goNext"
              >
                {{ saving ? 'Speichern...' : (currentQuestion === questions.length - 1 ? 'Abschliessen' : 'Weiter') }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>

    <div v-else-if="!hasPerfumes" class="flex min-h-screen items-center justify-center px-6">
      <div class="max-w-2xl rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center text-amber-900">
        <h2 class="text-2xl font-semibold">Dieses Set hat aktuell keine Parfums</h2>
        <p class="mt-3 text-sm md:text-base">
          Dem Set sind in der Datenbank noch keine Einträge in sample_set_items zugeordnet.
        </p>
        <NuxtLink to="/sets" class="mt-5 inline-flex rounded-xl bg-[#8e6c2a] px-5 py-3 text-sm font-semibold text-white">
          Zurück zu Ihren Sets
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

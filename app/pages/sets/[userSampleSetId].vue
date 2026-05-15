<script setup lang="ts">
import { getQuestions, getQuestionTitle, type Question as UnifiedQuestion } from '../../composables/useQuestions'

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
const nowTs = ref(Date.now())
let nowInterval: ReturnType<typeof setInterval> | null = null

const currentPerfumeIdx = ref(0)
const currentQuestion = ref(0) // 0..slideConfig-1 = questions, slideConfig.length = thank-you per perfume
const showOverview = ref(true)
const activeDuftInfo = ref<string | null>(null)

const currentQuestionStepLabel = computed(() => `${Math.min(currentQuestion.value + 1, slideConfig.length)} von ${slideConfig.length}`)
const currentQuestionPercentage = computed(() => Math.round(Math.min(((currentQuestion.value + 1) / slideConfig.length) * 100, 100)))
const currentQuestionProgress = computed(() => `${currentQuestionPercentage.value}%`)

const duftfamilieDescriptions: Record<string, string> = {
  Zitrus: 'Erinnert an Zitrone, Bergamotte, Grapefruit oder Orangenschale.',
  Fruchtig: 'Erinnert an Beeren, Apfel, Pfirsich, Birne oder tropische Früchte.',
  Blumig: 'Erinnert an Blüten wie Rose, Jasmin, Iris oder Orangenblüte.',
  Pudrig: 'Erinnert an Puder, Schminke, Iris oder feine Kosmetik.',
  Aquatisch: 'Erinnert an Meerwasser, Regen, Mineralien oder feuchte Luft.',
  Holzig: 'Erinnert an Zedernholz, Sandelholz, trockenes Holz oder Wald.',
  Grün: 'Erinnert an Gras, Blätter, Stängel, Kräuter oder grüne Pflanzen.',
  Balsamisch: 'Harzig und weich, z. B. Benzoe, Myrrhe, Amber oder Vanilleharz.',
  Aromatisch: 'Kräuterartige Eindrücke wie Lavendel, Salbei, Rosmarin oder Minze.',
  Erdig: 'Erinnert an Erde, Moos, Waldboden, Wurzeln oder feuchte Natur.',
  Rauchig: 'Erinnert an Rauch, Feuerholz, Weihrauch, Tabak oder Asche.',
  Würzig: 'Erinnert an Gewürze wie Pfeffer, Zimt, Kardamom oder Nelke.',
  Orientalisch: 'Opulent und süßlich, oft mit Vanille, Harzen, Amber oder Gewürzen.',
  Ledrig: 'Erinnert an Leder, Wildleder, Tabak oder eine Lederjacke.',
  Gourmand: 'Erinnert an Essbares wie Vanille, Karamell, Schokolade oder Kaffee.',
}

// Per-perfume answers - flexible key-value structure
const answers = reactive<Record<number, Record<string, string | number | string[]>>>({})

const userSampleSetId = computed(() => Number(route.params.userSampleSetId || 0))
const currentPerfume = computed(() => perfumes.value[currentPerfumeIdx.value])
const hasPerfumes = computed(() => perfumes.value.length > 0)
const tieFavoriteStorageKey = computed(() => `nichecult_tie_favorite_set_${userSampleSetId.value}`)
const ratedPerfumeCount = computed(() => perfumes.value.filter(perfume => isRated(perfume)).length)
const ratedPerfumeProgressLabel = computed(() => `${ratedPerfumeCount.value} von ${perfumes.value.length} wurden bewertet`)

const priceLabel = (priceCents: unknown, sizeMl: unknown) => {
  const cents = Number(priceCents || 0)
  const ml = Number(sizeMl || 100)
  const eur = (Math.round(cents) / 100).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
  return `${ml} ml · ${eur} €`
}

const priceOnlyLabel = (priceCents: unknown) => {
  const cents = Number(priceCents || 0)
  return (Math.round(cents) / 100).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + ' €'
}

const discountedCents = (priceCents: unknown) => Math.round(Number(priceCents || 0) * 0.85)

const isRated = (perfume: Record<string, any>) => perfume?.overall_score != null

const perfumeAvgScore = (perfume: Record<string, any>) => {
  if (perfume?.overall_score == null) {
    return null
  }

  return (
    Number(perfume.overall_score || 0)
    + Number(perfume.longevity_score || 0)
    + Number(perfume.sillage_score || 0)
  ) / 3
}

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

const topTiePerfumes = computed(() => {
  const rated = perfumes.value
    .map((perfume) => ({ perfume, score: perfumeAvgScore(perfume) }))
    .filter((entry): entry is { perfume: Record<string, any>; score: number } => entry.score != null)

  if (rated.length < 2) {
    return []
  }

  const bestScore = Math.max(...rated.map(entry => entry.score))
  const epsilon = 0.0001

  return rated
    .filter(entry => Math.abs(entry.score - bestScore) < epsilon)
    .map(entry => entry.perfume)
})

const showTieQuestion = computed(() => topTiePerfumes.value.length >= 2)

const parseApiDate = (value: unknown) => {
  const raw = String(value || '').trim()
  if (!raw) return null
  const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T')
  const date = new Date(normalized)
  return Number.isNaN(date.getTime()) ? null : date
}

const deadlineDate = computed(() => parseApiDate(sampleSet.value?.rating_deadline_at))
const isDeadlineExpired = computed(() => {
  if (!deadlineDate.value) return false
  return deadlineDate.value.getTime() <= nowTs.value
})

const deadlineLabel = computed(() => {
  if (!deadlineDate.value) {
    return 'Keine Bewertungsfrist gesetzt'
  }

  return deadlineDate.value.toLocaleString('de-DE', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
})

const deadlineCountdown = computed(() => {
  if (!deadlineDate.value) {
    return 'Offene Frist'
  }

  const diffMs = deadlineDate.value.getTime() - nowTs.value
  if (diffMs <= 0) {
    return 'Bewertungsfrist abgelaufen'
  }

  const totalSeconds = Math.floor(diffMs / 1000)
  const days = Math.floor(totalSeconds / 86400)
  const hours = Math.floor((totalSeconds % 86400) / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60

  if (days > 0) {
    return `Noch ${days}T ${hours}Std ${minutes}Min`
  }

  return `Noch ${hours}Std ${minutes}Min ${seconds}Sek`
})

const selectTieFavorite = (perfumeId: number) => {
  favoritePerfumeId.value = perfumeId
  localStorage.setItem(tieFavoriteStorageKey.value, String(perfumeId))
}

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

const ensureAnswers = (perfumeId: number) => {
  if (!answers[perfumeId]) {
    answers[perfumeId] = {}
  }
  // Extract all keys from slideConfig and ensure they all exist
  const defaults: Record<string, any> = {
    gender: 2,
    season: 2,
    occasion: [],
    warmFrisch: 2,
    naturalSynthetisch: 2,
    intensivDezent: 2,
    sweetness: 2,
    sexyClean: 2,
    duftfamilien: [],
    overallMatch: 2,
  }
  
  // Add any keys from slideConfig that might not be in defaults
  for (const slide of slideConfig) {
    for (const key of slide.keys) {
      if (!(key in defaults)) {
        defaults[key] = key === 'duftfamilien' ? [] : 2
      }
    }
  }
  
  // Ensure all expected keys exist with their defaults
  for (const [key, defaultValue] of Object.entries(defaults)) {
    if (!(key in answers[perfumeId])) {
      answers[perfumeId][key] = defaultValue
    }
  }
}

const ratingQuestions = getQuestions('rating', true)

// Convert unified questions to display format
type DisplayQuestion = {
  type: string
  key: string
  title: string
  layout?: 'pair' | 'stack' | 'grid5' | 'grid6'
  options?: string[]
  optionDescriptions?: string[]
  left?: string
  center?: string
  right?: string
  labels?: string[]
}

// Create a simple lookup map
const createQuestionMap = () => {
  const map = new Map<string, UnifiedQuestion>()
  for (const q of ratingQuestions) {
    map.set(q.key, q)
  }
  return map
}

// Simple question converter
const makeDisplayQuestion = (q: UnifiedQuestion | undefined): DisplayQuestion | null => {
  if (!q) return null
  
  const title = (q as any).rating?.title || (q as any).key || 'Unknown'
  const type = (q as any).type
  const key = (q as any).key || ''
  
  switch (type) {
    case 'choice':
      if (key === 'gender') {
        return {
          type: 'slider',
          key,
          title,
          left: 'Männlich',
          center: 'Unisex',
          right: 'Weiblich',
        }
      }
      return {
        type: 'choice',
        key,
        title,
        layout: (q as any).layout,
        options: ((q as any).rating?.options) || [],
        optionDescriptions: ((q as any).rating?.optionDescriptions) || [],
      }
    case 'slider':
      return {
        type: 'slider',
        key,
        title,
        left: ((q as any).rating?.left) || '',
        center: ((q as any).rating?.center) || '',
        right: ((q as any).rating?.right) || '',
      }
    case 'multi':
      return {
        type: 'multi',
        key,
        title,
        options: (q as any).options || [],
      }
    case 'labeled':
      return {
        type: 'labeled',
        key,
        title,
        labels: ((q as any).rating?.labels) || [],
      }
    default:
      return { type: 'unknown', key, title } as any
  }
}

// Build questions from slideConfig
const buildQuestionsFromSlideConfig = () => {
  const questionMap = createQuestionMap()
  const result: DisplayQuestion[] = []
  
  for (const slide of slideConfig) {
    for (const key of slide.keys) {
      const question = questionMap.get(key)
      const displayQuestion = makeDisplayQuestion(question)
      if (displayQuestion) {
        result.push(displayQuestion)
      }
    }
  }
  
  return result
}

const questions = computed(() => buildQuestionsFromSlideConfig())

// Slide configuration: which questions to show on each slide
const slideConfig = [
  { keys: ['gender'], twoColumn: false },
  { keys: ['season'], twoColumn: false },
  { keys: ['occasion'], twoColumn: false },
  { keys: ['warmFrisch', 'naturalSynthetisch'], twoColumn: true },
  { keys: ['sweetness', 'sexyClean'], twoColumn: true },
  { keys: ['intensivDezent'], twoColumn: false },
  { keys: ['duftfamilien'], twoColumn: false },
  { keys: ['overallMatch'], twoColumn: false },
]

const currentSlideQuestions = computed<DisplayQuestion[]>(() => {
  const slideIdx = Math.min(currentQuestion.value, slideConfig.length - 1)
  const config = slideConfig[slideIdx]
  if (!config) return []
  return questions.value.filter(q => config.keys.includes(q.key))
})

const currentSlideIsTwoColumn = computed(() => {
  const slideIdx = Math.min(currentQuestion.value, slideConfig.length - 1)
  return slideConfig[slideIdx]?.twoColumn ?? false
})

const currentSlideFirstQuestion = computed<DisplayQuestion | null>(() => currentSlideQuestions.value[0] || null)

const currentQ = computed<DisplayQuestion>(() => {
  const first = currentSlideFirstQuestion.value
  return (first || questions.value[0]) as DisplayQuestion
})

const isOccasionMultiSelect = computed(() => currentQ.value.key === 'occasion')
const isStepWithoutIntroPrompt = computed(() => {
  const keys = currentSlideQuestions.value.map(q => q.key)
  const isWarmNatural = keys.length === 2 && keys.includes('warmFrisch') && keys.includes('naturalSynthetisch')
  const isSweetSexy = keys.length === 2 && keys.includes('sweetness') && keys.includes('sexyClean')
  return isWarmNatural || isSweetSexy
})

const primaryQuestionText = computed(() => {
  if (currentSlideQuestions.value.length > 1) {
    return isStepWithoutIntroPrompt.value ? '' : 'Bitte beantworten Sie die folgenden Fragen'
  }
  return currentQ.value?.title || ''
})

const getScaleValue = (perfumeId: number, key: string) => getAnswerValue(perfumeId, key) as number
const setScaleValue = (perfumeId: number, key: string, value: number) => {
  setAnswerValue(perfumeId, key, value)
}

const getAnswerValue = (perfumeId: number, key: string): string | number | string[] => {
  return answerFor(perfumeId)[key] ?? 2
}

const setAnswerValue = (perfumeId: number, key: string, value: string | number | string[]) => {
  answerFor(perfumeId)[key] = value
}

const toggleMultiOption = (perfumeId: number, key: string, opt: string) => {
  const arr = getAnswerValue(perfumeId, key) as string[]
  const idx = arr.indexOf(opt)
  if (idx >= 0) arr.splice(idx, 1)
  else arr.push(opt)
}

const toggleChoiceMultiOption = (perfumeId: number, key: string, opt: string) => {
  const current = getAnswerValue(perfumeId, key)
  const arr = Array.isArray(current) ? current : []
  const idx = arr.indexOf(opt)
  if (idx >= 0) {
    arr.splice(idx, 1)
  } else {
    arr.push(opt)
  }
  setAnswerValue(perfumeId, key, arr)
}

const toggleDuftInfo = (opt: string) => {
  activeDuftInfo.value = activeDuftInfo.value === opt ? null : opt
}

const openDuftInfo = (opt: string) => {
  activeDuftInfo.value = opt
}

const closeDuftInfo = (opt: string) => {
  if (activeDuftInfo.value === opt) {
    activeDuftInfo.value = null
  }
}

const isDuftInfoOpen = (opt: string) => activeDuftInfo.value === opt

const startPerfumeRating = (perfumeIndex: number) => {
  if (isDeadlineExpired.value) {
    error.value = 'Die Bewertungsfrist für dieses Set ist abgelaufen.'
    return
  }

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

const canContinue = computed(() => {
  const p = currentPerfume.value
  if (!p) return false
  const qs = currentSlideQuestions.value
  if (qs.length === 0) return false
  
  // Check that all questions in this slide are answered
  for (const q of qs) {
    const val = getAnswerValue(p.perfume_id, q.key)
    if (q.type === 'multi') {
      if (!Array.isArray(val) || val.length === 0) return false
    } else if (q.type === 'choice' && q.key === 'occasion') {
      if (!Array.isArray(val) || val.length === 0) return false
    } else if (val === undefined || val === null) {
      return false
    }
  }
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

    const savedTieFavoriteId = Number(localStorage.getItem(tieFavoriteStorageKey.value) || 0)
    if (savedTieFavoriteId > 0 && perfumes.value.some(p => Number(p.perfume_id) === savedTieFavoriteId)) {
      favoritePerfumeId.value = savedTieFavoriteId
    }

    for (const p of perfumes.value) ensureAnswers(p.perfume_id)
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}

const goNext = async () => {
  const perfume = currentPerfume.value
  if (!perfume) return

  if (isDeadlineExpired.value) {
    error.value = 'Die Bewertungsfrist für dieses Set ist abgelaufen.'
    return
  }

  if (currentQuestion.value < slideConfig.length - 1) {
    currentQuestion.value++
    return
  }

  // Last question → save
  saving.value = true
  error.value = ''
  try {
    const token = localStorage.getItem('nichecult_token')!
    const a = answerFor(perfume.perfume_id)
    
    // Extract all expected keys from slideConfig to ensure we save everything
    const expectedKeysSet = new Set<string>()
    for (const slide of slideConfig) {
      for (const key of slide.keys) {
        expectedKeysSet.add(key)
      }
    }
    const expectedKeys = Array.from(expectedKeysSet)
    
    const answersToSave: Record<string, string> = {}
    
    for (const key of expectedKeys) {
      let value = a[key]
      
      // Provide defaults for missing keys
      if (value === undefined || value === null) {
        if (key === 'duftfamilien') {
          value = []
        } else {
          value = 2
        }
      }
      
      // Convert to saveable format
      if (Array.isArray(value)) {
        answersToSave[key] = JSON.stringify(value)
      } else {
        answersToSave[key] = String(value)
      }
    }
    
    // Also save any extra keys that might exist
    for (const [key, value] of Object.entries(a)) {
      if (!expectedKeysSet.has(key)) {
        if (Array.isArray(value)) {
          answersToSave[key] = JSON.stringify(value)
        } else {
          answersToSave[key] = String(value ?? '')
        }
      }
    }
    
    const overallScore = (Number(a.overallMatch) + 1) * 2 // 2,4,6,8,10
    await savePerfumeRating(token, {
      userSampleSetId: userSampleSetId.value,
      perfumeId: perfume.perfume_id,
      overallScore,
      longevityScore: overallScore,
      sillageScore: overallScore,
      answers: answersToSave,
    })

    perfume.overall_score = overallScore
    perfume.longevity_score = overallScore
    perfume.sillage_score = overallScore
    favoritePerfumeId.value = getFavoritePerfumeId(perfumes.value)
    currentQuestion.value = slideConfig.length // thank-you screen
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Fehler beim Speichern'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadData()
  nowInterval = setInterval(() => {
    nowTs.value = Date.now()
  }, 1000)
})

onUnmounted(() => {
  if (nowInterval) {
    clearInterval(nowInterval)
    nowInterval = null
  }
})
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-[radial-gradient(circle_at_top_left,rgba(184,150,78,0.18),transparent_30%),radial-gradient(circle_at_top_right,rgba(141,108,42,0.12),transparent_28%),linear-gradient(180deg,#f9f4eb_0%,#f4ecdf_46%,#efe5d5_100%)] text-[#1a1612]">
    <div class="pointer-events-none absolute -left-28 top-20 h-72 w-72 rounded-full bg-[#f0d8b0]/35 blur-3xl" />
    <div class="pointer-events-none absolute -right-16 top-1/3 h-80 w-80 rounded-full bg-[#d7be8a]/25 blur-3xl" />
    <div class="pointer-events-none absolute bottom-0 left-1/3 h-64 w-64 rounded-full bg-[#b99a57]/15 blur-3xl" />
    <!-- Loading -->
    <div v-if="loading" class="flex min-h-screen items-center justify-center text-stone-500">Lade Set...</div>
    <div v-else-if="error && !currentPerfume" class="flex min-h-screen items-center justify-center p-8 text-red-600">{{ error }}</div>

    <div v-else-if="showOverview && hasPerfumes" class="nc-page">
      <div class="nc-page-frame">
        <SiteHeaderNav title="Ihre Samples" active="samples" />

        <div class="mx-auto w-full max-w-6xl">
          <section class="mb-8 rounded-4xl border border-white/70 bg-white/72 p-6 shadow-[0_18px_50px_rgba(62,45,20,0.08)] backdrop-blur md:p-8">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
              <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#8b6c2d]">Sampleset Übersicht</p>
                <h1 class="mt-2 text-4xl leading-tight md:text-6xl">{{ sampleSet?.title || 'Ihre Samples' }}</h1>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-[#4c4338] md:text-base">
                  Bewerten Sie jeden Duft Schritt für Schritt. Ihre Antworten fließen direkt in die nächste Kuration ein.
                </p>
              </div>

              <div class="flex flex-col gap-2 text-sm md:items-end">
                <span class="inline-flex w-fit rounded-full border border-[#d8ccb0] bg-[#fbf7ef] px-3 py-1 font-medium text-[#5b4a32]">
                  {{ ratedPerfumeProgressLabel }}
                </span>
                <span class="inline-flex w-fit rounded-full px-3 py-1 font-semibold" :class="isDeadlineExpired ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-900'">
                  {{ deadlineCountdown }}
                </span>
                <span class="text-xs uppercase tracking-[0.18em] text-[#7f6a4c]">Frist: {{ deadlineLabel }}</span>
              </div>
            </div>
          </section>

          <div v-if="showTieQuestion" class="mb-6 rounded-2xl border border-[#c8b48a] bg-[#faf7f2] p-5">
            <p class="text-base font-semibold text-[#3e352d] md:text-lg">
              Sie haben diesen Düften die gleiche Bewertung gegeben. Welcher dieser Düfte trifft Ihren Geschmack am besten?
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
              <button
                v-for="p in topTiePerfumes"
                :key="`tie-${p.perfume_id}`"
                type="button"
                class="rounded-full border px-4 py-2 text-sm font-medium transition"
                :class="isFavorite(p)
                  ? 'border-[#8e6c2a] bg-[#8e6c2a] text-white'
                  : 'border-[#c8b48a] bg-white text-[#1a1612] hover:bg-[#f0e8d6]'"
                @click="selectTieFavorite(Number(p.perfume_id))"
              >
                {{ p.brand_name }} {{ p.name }}
              </button>
            </div>
          </div>

          <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="(perfume, index) in perfumes"
              :key="perfume.perfume_id"
              class="group mx-auto w-full max-w-85 overflow-hidden rounded-[30px] border border-white/80 bg-white/78 shadow-[0_18px_40px_rgba(62,45,20,0.08)] backdrop-blur transition duration-300 hover:-translate-y-1 hover:shadow-[0_28px_60px_rgba(62,45,20,0.14)]"
            >
              <div class="relative nc-perfume-card-media nc-perfume-card-media-tall -mb-1 overflow-hidden bg-[linear-gradient(180deg,#f8f4ec,#f0e5d3)] ring-1 ring-[#eadfc9]">
                  <img
                    v-if="perfume.image_url"
                    :src="perfume.image_url"
                    alt="Parfumbild"
                    class="nc-perfume-card-image"
                  >
                  <div v-else class="h-full w-full rounded-2xl bg-[#ece4d3]" />

                  <div class="absolute left-3 top-2 flex flex-col items-start gap-1">
                    <span
                      v-if="statusHeading(perfume)"
                      class="rounded-full bg-[#8e6c2a]/90 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-white shadow-sm"
                    >
                      {{ statusHeading(perfume) }}
                    </span>
                    <span
                      v-if="statusSubheading(perfume)"
                      class="rounded-full bg-white/88 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#5a4820] shadow-sm backdrop-blur"
                    >
                      {{ statusSubheading(perfume) }}
                    </span>
                  </div>
              </div>

              <div class="px-3.5 pb-2.5 pt-2 text-center md:px-4 md:pb-3">
                <p class="text-[10px] uppercase tracking-[0.18em] text-[#7f6a4c] md:text-[11px]">{{ perfume.brand_name }}</p>
                <p class="mt-0.5 text-[18px] leading-tight text-[#1c1712] md:text-[22px]">{{ perfume.name }}</p>

                <div class="mt-2 text-[12px] leading-tight text-[#1f1b18]">
                  <template v-if="isRated(perfume)">
                    <span class="font-semibold">{{ priceLabel(discountedCents(perfume.price_cents), perfume.size_ml) }}</span>
                    <span class="mt-0.5 block text-[11px] text-[#5e513d] md:text-xs">
                      (regulär {{ priceOnlyLabel(perfume.price_cents) }})
                    </span>
                  </template>
                  <template v-else>
                    <span class="font-semibold">{{ priceLabel(perfume.price_cents, perfume.size_ml) }}</span>
                  </template>
                </div>

                <button
                  type="button"
                  class="mt-2.5 inline-flex w-full items-center justify-center rounded-2xl bg-[#1c1712] px-4 py-2 text-[13px] font-semibold text-white transition hover:bg-[#2b2119] md:text-[14px]"
                  :disabled="isDeadlineExpired"
                  :class="isDeadlineExpired ? 'cursor-not-allowed opacity-50' : ''"
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

        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col">
          <div class="mb-6 rounded-[30px] border border-white/70 bg-white/72 p-5 shadow-[0_18px_40px_rgba(62,45,20,0.08)] backdrop-blur md:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b6c2d]">
                  {{ sampleSet?.title || 'Sample-Set' }}
                </p>
                <p class="mt-2 text-lg font-semibold text-[#1c1712] md:text-2xl">
                  Parfum {{ currentPerfumeIdx + 1 }} von {{ perfumes.length }}
                </p>
              </div>

            </div>
          </div>

          <!-- Thank-you screen after saving a perfume -->
          <template v-if="currentQuestion === slideConfig.length">
            <div class="flex flex-1 flex-col items-center justify-center rounded-[34px] border border-white/70 bg-white/72 px-6 py-12 text-center shadow-[0_18px_40px_rgba(62,45,20,0.08)] backdrop-blur md:px-10 md:py-16">
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b6c2d]">Bewertung abgeschlossen</p>
              <h1 class="mt-3 text-3xl leading-tight md:text-5xl">
                Vielen Dank für Ihre Bewertung zu:
              </h1>
              <p class="mt-6 text-2xl font-semibold text-[#1c1712] md:text-4xl">
                {{ currentPerfume.brand_name }} {{ currentPerfume.name }}
              </p>
              <p class="mt-6 max-w-lg text-base leading-relaxed text-[#4a3f2f] md:text-xl">
                Ihre Bewertung geht in die Kuration des nächsten Sample-Sets ein.
              </p>
            </div>
            <div class="mt-6 flex justify-end">
              <button
                type="button"
                class="rounded-2xl bg-[#1c1712] px-8 py-4 text-base font-semibold text-white transition hover:bg-[#2b2119]"
                @click="returnToOverview"
              >
                Zurück zur Übersicht
              </button>
            </div>
          </template>

          <!-- Question screen -->
          <template v-else>
            <section class="rounded-[34px] border border-white/70 bg-white/78 px-5 py-6 shadow-[0_18px_40px_rgba(62,45,20,0.08)] backdrop-blur md:px-8 md:py-8">
              <div class="flex flex-col gap-3 border-b border-[#ece1d0] pb-5 md:flex-row md:items-end md:justify-between">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b6c2d]">
                    {{ sampleSet?.title || 'Sample-Set' }}
                  </p>
                  <h1 class="mt-2 text-2xl font-semibold leading-tight text-[#1a1612] md:text-4xl">
                    {{ currentPerfume.brand_name }} {{ currentPerfume.name }}
                  </h1>
                </div>

                <div class="rounded-2xl bg-[#f7f1e6] px-4 py-3 text-sm text-[#5a4820]">
                  Schritt <span class="font-semibold">{{ currentQuestionStepLabel }}</span>
                </div>
              </div>

              <div class="mt-5 mb-6 space-y-3">
                <div class="flex items-center justify-between gap-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b6c2d]">
                    Schritt {{ currentQuestionStepLabel }}
                  </p>
                  <p class="text-xs font-semibold text-[#6b5431]">{{ currentQuestionPercentage }}%</p>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-[#eadfc9]">
                  <div class="h-full rounded-full bg-[#8e6c2a] transition-all duration-300" :style="{ width: currentQuestionProgress }" />
                </div>
              </div>

              <!-- Error -->
              <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>

              <!-- Answer area -->
              <div class="mt-8 flex flex-1 flex-col justify-center md:mt-10">
                <div class="space-y-1">
                  <p v-if="primaryQuestionText" class="text-[1.05rem] font-medium leading-snug text-[#1a1612] md:text-[1.2rem]" style="font-family: Georgia, serif;">
                    {{ primaryQuestionText }}
                  </p>
                </div>

              <!-- Choice pair (gender, etc) -->
              <template v-if="currentQ.type === 'choice' && currentQ.layout === 'pair'">
                <div class="grid gap-4 md:grid-cols-2">
                  <button
                    v-for="opt in (currentQ as any).options"
                    :key="opt"
                    type="button"
                    class="rounded-3xl border px-6 py-6 text-left text-lg font-medium transition md:px-7 md:py-7 md:text-xl"
                    :class="getAnswerValue(currentPerfume.perfume_id, currentQ.key) === opt
                      ? 'border-[#8e6c2a] bg-[#8e6c2a] text-[#f5f0e8] shadow-lg shadow-[#8e6c2a]/20'
                      : 'border-[#e4d7bf] bg-[#fcf9f3] text-[#1a1612] hover:border-[#cdb98f] hover:bg-[#f6efe2]'"
                    @click="setAnswerValue(currentPerfume.perfume_id, currentQ.key, opt)"
                  >
                    {{ opt }}
                  </button>
                </div>
              </template>

              <!-- Choice stack (season, etc) -->
              <template v-else-if="currentQ.type === 'choice' && currentQ.layout === 'stack'">
                <div class="mx-auto flex w-full max-w-sm flex-col gap-4">
                  <button
                    v-for="opt in (currentQ as any).options"
                    :key="opt"
                    type="button"
                    class="rounded-3xl border px-6 py-5 text-center text-lg font-medium transition md:px-7 md:py-6 md:text-xl"
                    :class="getAnswerValue(currentPerfume.perfume_id, currentQ.key) === opt
                      ? 'border-[#8e6c2a] bg-[#8e6c2a] text-[#f5f0e8] shadow-lg shadow-[#8e6c2a]/20'
                      : 'border-[#e4d7bf] bg-[#fcf9f3] text-[#1a1612] hover:border-[#cdb98f] hover:bg-[#f6efe2]'"
                    @click="setAnswerValue(currentPerfume.perfume_id, currentQ.key, opt)"
                  >
                    {{ opt }}
                  </button>
                </div>
              </template>

              <!-- Choice grid6 (occasion) -->
              <template v-else-if="currentQ.type === 'choice' && currentQ.layout === 'grid6'">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  <button
                    v-for="(opt, idx) in ((currentQ as any).options || []).slice(0, 6)"
                    :key="opt"
                    type="button"
                    class="rounded-3xl border px-5 py-5 text-left transition"
                    :class="(isOccasionMultiSelect
                      ? ((getAnswerValue(currentPerfume.perfume_id, currentQ.key) as string[] || []).includes(opt))
                      : (getAnswerValue(currentPerfume.perfume_id, currentQ.key) === opt))
                      ? 'border-[#8e6c2a] bg-[#8e6c2a] text-[#f5f0e8] shadow-lg shadow-[#8e6c2a]/20'
                      : 'border-[#e4d7bf] bg-[#fcf9f3] text-[#1a1612] hover:border-[#cdb98f] hover:bg-[#f6efe2]'"
                    @click="isOccasionMultiSelect
                      ? toggleChoiceMultiOption(currentPerfume.perfume_id, currentQ.key, String(opt))
                      : setAnswerValue(currentPerfume.perfume_id, currentQ.key, opt)"
                  >
                    <span class="block text-base font-semibold md:text-lg">{{ opt }}</span>
                    <span v-if="((currentQ as any).optionDescriptions || [])[idx]" class="mt-2 block text-sm font-normal leading-snug opacity-85">
                      {{ ((currentQ as any).optionDescriptions || [])[idx] }}
                    </span>
                  </button>
                </div>
              </template>

              <!-- Slider (warmFrisch, intensivDezent, sweetness) -->
              <template v-else-if="currentQ.type === 'slider'">
                <div class="flex flex-col gap-8">
                  <div v-for="q in currentSlideQuestions" :key="q.key" class="mt-8">
                    <p v-if="currentSlideQuestions.length > 1" class="mb-3 text-[1.05rem] font-medium leading-snug text-[#1a1612] md:text-[1.2rem]" style="font-family: Georgia, serif;">{{ q.title }}</p>
                    <div class="relative flex justify-between text-xs font-medium md:text-sm">
                      <span>{{ (q as any).left }}</span>
                      <span class="absolute left-1/2 -translate-x-1/2">{{ (q as any).center }}</span>
                      <span>{{ (q as any).right }}</span>
                    </div>
                    <div class="relative mt-4 flex items-center">
                      <div class="absolute left-0 right-0 h-px bg-[#cdb98f]" />
                      <div class="relative flex w-full justify-between">
                        <button
                          v-for="i in 5"
                          :key="`${q.key}-${i}`"
                          type="button"
                          class="relative h-9 w-9 rounded-md border-2 transition"
                          :class="getScaleValue(currentPerfume.perfume_id, q.key) === (i - 1)
                            ? 'border-[#8e6c2a] bg-[#8e6c2a]'
                            : 'border-[#b99a57] bg-[#f5f0e8] hover:bg-[#e8ddc8]'"
                          @click="setScaleValue(currentPerfume.perfume_id, q.key, i - 1)"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </template>

              <!-- Labeled 5-point slider (overallMatch) -->
              <template v-else-if="currentQ.type === 'labeled'">
                <div class="mt-8">
                  <div class="relative flex justify-between text-base font-medium md:text-lg">
                    <span v-for="(lbl, li) in ((currentSlideFirstQuestion as any)?.labels || [])" :key="li">{{ lbl }}</span>
                  </div>
                  <div class="relative mt-5 flex items-center">
                    <div class="absolute left-0 right-0 h-px bg-[#cdb98f]" />
                    <div class="relative flex w-full justify-between">
                      <button
                        v-for="i in 5"
                        :key="i"
                        type="button"
                        class="relative h-9 w-9 rounded-md border-2 transition"
                        :class="getScaleValue(currentPerfume.perfume_id, currentSlideFirstQuestion?.key || '') === (i - 1)
                          ? 'border-[#8e6c2a] bg-[#8e6c2a]'
                          : 'border-[#b99a57] bg-[#f5f0e8] hover:bg-[#e8ddc8]'"
                        @click="setScaleValue(currentPerfume.perfume_id, currentSlideFirstQuestion?.key || '', i - 1)"
                      />
                    </div>
                  </div>
                </div>
              </template>

              <!-- Multi-select (Duftfamilien) -->
              <template v-else-if="currentQ.type === 'multi'">
                <div class="mt-6 rounded-[28px] border border-[#eadfc9] bg-[#fbf8f2] p-5 md:p-6">
                  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                      v-for="opt in ((currentSlideFirstQuestion as any)?.options || [])"
                      :key="opt"
                      class="relative rounded-2xl border p-3 transition"
                      :class="(getAnswerValue(currentPerfume.perfume_id, currentSlideFirstQuestion?.key || '') as string[]).includes(opt)
                        ? 'border-[#8e6c2a] bg-[#f4ebd8]'
                        : 'border-[#e3d6bd] bg-white hover:bg-[#fcf9f3]'"
                    >
                      <div class="flex items-start gap-2">
                        <button
                          type="button"
                          class="flex-1 text-left text-sm font-semibold transition"
                          :class="(getAnswerValue(currentPerfume.perfume_id, currentSlideFirstQuestion?.key || '') as string[]).includes(opt)
                            ? 'text-[#5a4415]'
                            : 'text-[#1a1612] hover:text-[#5a4415]'"
                          @click="toggleMultiOption(currentPerfume.perfume_id, currentSlideFirstQuestion?.key || '', opt)"
                        >
                          {{ opt }}
                        </button>
                        <div class="relative shrink-0">
                          <button
                            type="button"
                            class="h-6 w-6 rounded-full border border-[#b99a57] bg-[#f5f0e8] text-xs font-bold text-[#7a6040] transition hover:bg-[#e8ddc8]"
                            :aria-label="`Info zu ${opt}`"
                            @mouseenter="openDuftInfo(String(opt))"
                            @mouseleave="closeDuftInfo(String(opt))"
                            @focus="openDuftInfo(String(opt))"
                            @blur="closeDuftInfo(String(opt))"
                            @click.stop="toggleDuftInfo(String(opt))"
                          >
                            i
                          </button>
                          <p
                            v-if="isDuftInfoOpen(String(opt))"
                            class="absolute bottom-full right-0 z-20 mb-2 w-64 rounded-lg border border-[#d8ccb0] bg-[#fffdf8] p-2 text-xs leading-relaxed text-[#4a3f2f] shadow-md"
                          >
                            {{ duftfamilieDescriptions[String(opt)] || 'Keine Beschreibung verfügbar.' }}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
              </div>

              <!-- Navigation -->
              <div class="mt-8 flex items-center justify-between gap-4 border-t border-[#ece1d0] pt-5">
                <button
                  v-if="currentQuestion > 0"
                  type="button"
                  class="rounded-2xl border border-[#e3d6bd] bg-white px-6 py-3 text-sm font-medium text-[#5a4820] transition hover:bg-[#f5efe4]"
                  @click="currentQuestion--"
                >
                  Zurück
                </button>
                <div v-else />
                <button
                  type="button"
                  :disabled="!canContinue || saving || isDeadlineExpired"
                  class="rounded-2xl px-7 py-3 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
                  :class="canContinue ? 'bg-[#1c1712] text-white hover:bg-[#2b2119]' : 'bg-[#b99a57] text-[#1a1612]'"
                  @click="goNext"
                >
                  {{ saving ? 'Speichern...' : (currentQuestion === slideConfig.length - 1 ? 'Abschliessen' : 'Weiter') }}
                </button>
              </div>
            </section>
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

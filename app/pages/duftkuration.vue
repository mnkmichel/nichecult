<script setup lang="ts">
import { getQuestions, getQuestionTitle, getQuestionOptions, getQuestionOptionDescriptions, type Question, type QuestionContext } from '~/composables/useQuestions'
import parfumKurationImage from '~/assets/images/Parfum Kuration hochkant.png'

definePageMeta({
  middleware: 'auth',
})

const context: QuestionContext = 'curation'
const curationQuestionKeys = ['gender', 'season', 'occasion', 'warmFrisch', 'naturalSynthetisch', 'intensivDezent', 'sweetness', 'sexyClean', 'duftfamilien']
const questions = getQuestions(context, true).filter(q => curationQuestionKeys.includes(q.key))

// Map questions to display format
type DisplayQuestion = {
  type: string
  key: string
  title: string
  layout?: 'pair' | 'stack' | 'grid5' | 'grid6'
  options?: string[]
  optionDescriptions?: string[]
  leftLabel?: string
  centerLabel?: string
  rightLabel?: string
  labels?: string[]
}

const steps = computed<DisplayQuestion[]>(() => {
  const mapped = questions.map(q => {
    const title = getQuestionTitle(q, context)
    const options = getQuestionOptions(q, context)
    
    if (q.type === 'choice') {
      return {
        type: 'choice',
        key: q.key,
        title,
        layout: q.layout,
        options: options || [],
        optionDescriptions: getQuestionOptionDescriptions(q, context),
      }
    } else if (q.type === 'slider') {
      const config = q[context]
      if (!('left' in config)) throw new Error('Missing slider config')
      return {
        type: 'slider',
        key: q.key,
        title,
        leftLabel: config.left,
        centerLabel: config.center,
        rightLabel: config.right,
      }
    } else if (q.type === 'multi') {
      return {
        type: 'multi',
        key: q.key,
        title,
        options: (q as any).options || [],
      }
    }
    return { type: 'unknown', key: q.key, title }
  })

  return [
    {
      type: 'intro',
      key: '__intro__',
      title: 'Parfum-Kuration',
    },
    ...mapped,
  ]
})

const slideConfig = [
  { keys: ['__intro__'] },
  { keys: ['gender'] },
  { keys: ['season'] },
  { keys: ['occasion'] },
  { keys: ['warmFrisch', 'naturalSynthetisch'] },
  { keys: ['sweetness', 'sexyClean'] },
  { keys: ['intensivDezent'] },
  { keys: ['duftfamilien'] },
]

const currentStep = ref(0)
const answers = reactive<Record<string, string | number | string[]>>({})
const finished = ref(false)
const alreadyCompleted = ref(false)
const activeDuftInfo = ref<string | null>(null)

const totalSlides = computed(() => slideConfig.length)
const progressPercentage = computed(() => Math.round(((currentStep.value + 1) / totalSlides.value) * 100))

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

const resolveStep = (idx: number): DisplayQuestion => {
  const stepsArray = steps.value
  const safeIndex = Math.max(0, Math.min(idx, stepsArray.length - 1))
  const resolved = stepsArray[safeIndex]
  return resolved || stepsArray[0]!
}

const step = computed<DisplayQuestion>(() => resolveStep(currentStep.value))

const currentSlideQuestions = computed<DisplayQuestion[]>(() => {
  const slideIdx = Math.min(currentStep.value, slideConfig.length - 1)
  const config = slideConfig[slideIdx]
  if (!config) return []
  return steps.value.filter(q => config.keys.includes(q.key))
})

const currentQ = computed<DisplayQuestion>(() => {
  const first = currentSlideQuestions.value[0]
  return (first || step.value) as DisplayQuestion
})

const isStepWithoutIntroPrompt = computed(() => {
  const keys = currentSlideQuestions.value.map(q => q.key)
  const isWarmNatural = keys.length === 2 && keys.includes('warmFrisch') && keys.includes('naturalSynthetisch')
  const isSweetSexy = keys.length === 2 && keys.includes('sweetness') && keys.includes('sexyClean')
  return isWarmNatural || isSweetSexy
})

const primaryQuestionText = computed(() => {
  if (isIntroStep.value) {
    return 'Starten Sie mit Ihrer persönlichen Parfum-Kuration'
  }
  if (currentSlideQuestions.value.length > 1) {
    return isStepWithoutIntroPrompt.value ? '' : 'Bitte beantworten Sie die folgenden Fragen'
  }
  return currentQ.value.title
})

const isChoicePair = computed(() => currentQ.value.type === 'choice' && currentQ.value.layout === 'pair')
const isChoiceStack = computed(() => currentQ.value.type === 'choice' && currentQ.value.layout === 'stack')
const isChoiceGrid6 = computed(() => currentQ.value.type === 'choice' && currentQ.value.layout === 'grid6')
const isOccasionMultiSelect = computed(() => currentQ.value.key === 'occasion')
const isSliderStep = computed(() => currentQ.value.type === 'slider')
const isMultiStep = computed(() => currentQ.value.type === 'multi')
const isIntroStep = computed(() => currentQ.value.type === 'intro')

const canContinue = computed(() => {
  const slide = currentSlideQuestions.value
  if (slide.length === 0) return false
  return slide.every((s) => {
    if (s.type === 'intro') return true
    if (s.type === 'slider') return answers[s.key] !== undefined
    if (s.type === 'multi') return Array.isArray(answers[s.key]) && (answers[s.key] as string[]).length > 0
    if (s.type === 'choice' && s.key === 'occasion') return Array.isArray(answers[s.key]) && (answers[s.key] as string[]).length > 0
    return Boolean(answers[s.key])
  })
})

const selectOption = (value: string) => {
  answers[currentQ.value.key] = value
}

const toggleChoiceOption = (key: string, value: string) => {
  const current = answers[key]
  const arr = Array.isArray(current) ? current : []
  const idx = arr.indexOf(value)
  if (idx >= 0) {
    arr.splice(idx, 1)
  } else {
    arr.push(value)
  }
  answers[key] = arr
}

const setSlider = (idx: number) => {
  answers[currentQ.value.key] = idx
}

const setSliderValue = (key: string, idx: number) => {
  answers[key] = idx
}

const toggleMultiOption = (key: string, value: string) => {
  const current = answers[key]
  const arr = Array.isArray(current) ? current : []
  const idx = arr.indexOf(value)
  if (idx >= 0) {
    arr.splice(idx, 1)
  } else {
    arr.push(value)
  }
  answers[key] = arr
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

// Initialize slider defaults to center (index 2 = middle of 5)
watch(currentStep, (idx) => {
  const slide = currentSlideQuestions.value
  for (const s of slide) {
    if (s.type === 'slider' && answers[s.key] === undefined) {
      answers[s.key] = 2
    }
    if (s.type === 'multi' && answers[s.key] === undefined) {
      answers[s.key] = []
    }
  }
})

const goNext = () => {
  if (!canContinue.value) return
  if (currentStep.value < slideConfig.length - 1) {
    currentStep.value += 1
    return
  }
  localStorage.setItem('nichecult_duftkuration_done', '1')
  localStorage.setItem('nichecult_kuration_ever_done', '1')
  window.dispatchEvent(new Event('nichecult:kuration-completed'))
  finished.value = true
  alreadyCompleted.value = false
}

const goBack = () => {
  if (currentStep.value > 0) currentStep.value -= 1
}

const resetKuration = () => {
  localStorage.removeItem('nichecult_duftkuration_done')
  for (const key of Object.keys(answers)) {
    delete answers[key]
  }
  currentStep.value = 0
  finished.value = false
  alreadyCompleted.value = false
}

onMounted(() => {
  const done = localStorage.getItem('nichecult_duftkuration_done') === '1'
  if (done) {
    finished.value = true
    alreadyCompleted.value = true
  }
})
</script>

<template>
  <div v-if="!finished" class="nc-page flex flex-col bg-[#f5f0e8] text-[#1a1612]">
    <div class="nc-page-frame flex flex-1 flex-col">
      <SiteHeaderNav title="Parfum-Kuration" active="duftkuration" />

      <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col px-4 pb-8 sm:px-6">
        <div class="mt-4 rounded-[30px] border border-white/70 bg-white/75 p-5 shadow-[0_22px_55px_rgba(79,61,31,0.10)] backdrop-blur sm:p-8">
          <div class="mb-6 space-y-3">
            <div class="flex items-center justify-between gap-4">
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#8b6c2d]">
                Schritt {{ currentStep + 1 }} von {{ totalSlides }}
              </p>
              <p class="text-xs font-semibold text-[#6b5431]">{{ progressPercentage }}%</p>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-[#eadfc9]">
              <div class="h-full rounded-full bg-[#8e6c2a] transition-all duration-300" :style="{ width: `${progressPercentage}%` }"></div>
            </div>
          </div>

          <div class="space-y-1">
            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#8b6c2d]">Duftprofil</p>
            <p v-if="primaryQuestionText" class="text-[1.05rem] font-medium leading-snug text-[#1a1612] md:text-[1.2rem]" style="font-family: Georgia, serif;">
              {{ primaryQuestionText }}
            </p>
          </div>

          <div class="mt-8 flex flex-1 flex-col justify-center md:mt-10">
            <div v-if="isIntroStep" class="mx-auto w-full max-w-3xl">
              <div class="overflow-hidden rounded-3xl border border-[#e4d7bf] bg-[#fcf8ef] shadow-[0_25px_50px_-30px_rgba(68,49,23,0.7)]">
                <img
                  :src="parfumKurationImage"
                  alt="Parfum Kuration"
                  class="block h-auto w-full"
                >
              </div>
              <div class="h-2" aria-hidden="true"></div>
            </div>

            <div v-else-if="isChoicePair" class="flex justify-between gap-6">
              <button
                v-for="opt in (currentQ as any).options"
                :key="opt"
                type="button"
                class="flex-1 rounded-2xl px-8 py-7 text-lg font-medium transition md:text-2xl"
                :class="answers[currentQ.key] === opt
                  ? 'bg-[#8e6c2a] text-[#f5f0e8]'
                  : 'bg-[#b99a57] text-[#1a1612] hover:brightness-95'"
                @click="selectOption(opt)"
              >
                {{ opt }}
              </button>
            </div>

            <div v-else-if="isChoiceStack" class="mx-auto flex w-full max-w-sm flex-col gap-4">
              <button
                v-for="opt in (currentQ as any).options"
                :key="opt"
                type="button"
                class="rounded-2xl px-8 py-6 text-center text-lg font-medium transition md:text-xl"
                :class="answers[currentQ.key] === opt
                  ? 'bg-[#8e6c2a] text-[#f5f0e8]'
                  : 'bg-[#b99a57] text-[#1a1612] hover:brightness-95'"
                @click="selectOption(opt)"
              >
                {{ opt }}
              </button>
            </div>

            <div v-else-if="isChoiceGrid6" class="flex flex-col gap-4">
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <button
                  v-for="(opt, idx) in ((currentQ as any).options || []).slice(0, 6)"
                  :key="opt"
                  type="button"
                  class="rounded-3xl border px-6 py-5 text-left text-base font-medium leading-snug transition md:px-7 md:py-6 md:text-lg"
                  :class="(isOccasionMultiSelect
                    ? ((answers[currentQ.key] as string[] || []).includes(opt))
                    : (answers[currentQ.key] === opt))
                    ? 'border-[#8e6c2a] bg-[#8e6c2a] text-[#f5f0e8] shadow-lg shadow-[#8e6c2a]/20'
                    : 'border-[#e4d7bf] bg-[#fcf9f3] text-[#1a1612] hover:border-[#cdb98f] hover:bg-[#f6efe2]'"
                  @click="isOccasionMultiSelect ? toggleChoiceOption(currentQ.key, String(opt)) : selectOption(opt)"
                >
                  <span class="block text-base font-semibold md:text-lg">{{ opt }}</span>
                  <span v-if="((currentQ as any).optionDescriptions || [])[idx]" class="mt-2 block text-sm font-normal leading-snug opacity-85">
                    {{ ((currentQ as any).optionDescriptions || [])[idx] }}
                  </span>
                </button>
              </div>
            </div>

            <div v-else-if="isMultiStep" class="mt-6 rounded-2xl border border-[#d8ccb0] bg-[#faf7f2] p-5">
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                  v-for="opt in ((currentQ as any).options || [])"
                  :key="opt"
                  class="relative rounded-xl border p-3"
                  :class="(answers[currentQ.key] as string[] || []).includes(opt)
                    ? 'border-[#8e6c2a] bg-[#f0e8d6]'
                    : 'border-[#c8b48a] bg-white'"
                >
                  <div class="flex items-start gap-2">
                    <button
                      type="button"
                      class="flex-1 text-left text-sm font-semibold transition"
                      :class="(answers[currentQ.key] as string[] || []).includes(opt)
                        ? 'text-[#5a4415]'
                        : 'text-[#1a1612] hover:text-[#5a4415]'"
                      @click="toggleMultiOption(currentQ.key, String(opt))"
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

            <div v-else-if="isSliderStep" class="mt-8 flex flex-col gap-8">
              <div v-for="q in currentSlideQuestions" :key="q.key">
                <p v-if="currentSlideQuestions.length > 1" class="mb-2 text-[1.05rem] font-medium leading-snug text-[#1a1612] md:text-[1.2rem]" style="font-family: Georgia, serif;">
                  {{ q.title }}
                </p>
                <div class="relative flex justify-between text-[11px] font-medium leading-tight sm:text-xs md:text-lg">
                  <span class="max-w-[34%] text-left">{{ (q as any).leftLabel }}</span>
                  <span class="absolute left-1/2 max-w-[40%] -translate-x-1/2 text-center">{{ (q as any).centerLabel }}</span>
                  <span class="max-w-[34%] text-right">{{ (q as any).rightLabel }}</span>
                </div>
                <div class="relative mt-5 flex items-center">
                  <div class="absolute left-0 right-0 h-px bg-[#b99a57]"></div>
                  <div class="relative flex w-full justify-between">
                    <button
                      v-for="i in 5"
                      :key="`${q.key}-${i}`"
                      type="button"
                      class="relative h-9 w-9 rounded-md border-2 transition"
                      :class="answers[q.key] === (i - 1)
                        ? 'border-[#8e6c2a] bg-[#8e6c2a]'
                        : 'border-[#b99a57] bg-[#f5f0e8] hover:bg-[#e8ddc8]'"
                      @click="setSliderValue(q.key, i - 1)"
                    ></button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-10 flex justify-between">
            <button
              v-if="currentStep > 0"
              type="button"
              class="rounded-xl border border-[#c8b48a] px-6 py-3 text-sm font-medium text-[#5a4820] transition hover:bg-[#ede5d4]"
              @click="goBack"
            >
              Zurück
            </button>
            <div v-else></div>
            <button
              type="button"
              :disabled="!canContinue"
              class="rounded-xl px-7 py-3 text-sm font-semibold transition disabled:opacity-40"
              :class="canContinue ? 'bg-[#b99a57] text-[#1a1612] hover:brightness-95' : 'bg-[#b99a57] text-[#1a1612]'"
              @click="goNext"
            >
              {{ currentStep === slideConfig.length - 1 ? 'Abschliessen' : 'Weiter' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div v-if="finished" class="nc-page relative flex overflow-hidden bg-white text-[#1a1612]">
    <div class="pointer-events-none absolute inset-0">
      <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-full w-full">
        <polygon points="50,0 100,0 100,100" fill="#f5f0e8" />
      </svg>
    </div>

    <div class="nc-page-frame relative z-10 flex w-full flex-col">
      <SiteHeaderNav title="Parfum-Kuration" active="duftkuration" />

      <div class="flex flex-1 flex-col">
        <h1 v-if="alreadyCompleted" class="max-w-2xl text-3xl leading-tight md:text-5xl">
          Ihre Parfum-Kuration ist bereits abgeschlossen.<br>
          Ihre Samples stehen für Sie bereit.
        </h1>
        <h1 v-else class="max-w-2xl text-3xl leading-tight md:text-5xl">
          Ihr Duftprofil wurde erstellt.<br>
          Wir kuratieren nun Ihre erste Persönliche Duftselektion.
        </h1>

        <div class="flex flex-1 items-end justify-end gap-3 pt-20">
          <button
            type="button"
            class="rounded-xl border border-[#c8b48a] bg-white px-8 py-4 text-base font-semibold text-[#5a4820] transition hover:bg-[#ede5d4]"
            @click="resetKuration"
          >
            Parfum-Kuration neu starten
          </button>
          <NuxtLink
            to="/sets"
            class="rounded-xl bg-[#8e6c2a] px-8 py-4 text-base font-semibold text-white transition hover:brightness-110"
          >
            {{ alreadyCompleted ? 'Zu Ihren Samples' : 'Weiter' }}
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

// Step types
type ChoiceStep = { type: 'choice'; key: string; title: string; layout: 'pair' | 'stack' | 'grid5'; options: string[] }
type SliderStep = { type: 'slider'; key: string; title: string; leftLabel: string; rightLabel: string; centerLabel: string }
type Step = ChoiceStep | SliderStep

const steps: Step[] = [
  {
    type: 'choice',
    key: 'gender',
    title: 'Für wen suchen Sie ein Parfum?',
    layout: 'pair',
    options: ['Mann', 'Frau'],
  },
  {
    type: 'choice',
    key: 'season',
    title: 'Zu welcher Jahreszeit soll das Parfum passen?',
    layout: 'stack',
    options: ['Frühling und Sommer', 'Herbst und Winter', 'Offen lassen'],
  },
  {
    type: 'choice',
    key: 'occasion',
    title: 'Zu welchem Anlass soll das Parfüm passen?',
    layout: 'grid5',
    options: ['Freizeit und Alltag', 'Geschäftliches Umfeld', 'Besondere Abendanlässe', 'Sport', 'Offen lassen'],
  },
  {
    type: 'slider',
    key: 'character',
    title: 'Sollte Ihr Parfum eher einen frischen oder warmen Character haben?',
    leftLabel: 'Warm',
    rightLabel: 'Frisch',
    centerLabel: 'Keine Präferenz',
  },
  {
    type: 'slider',
    key: 'intensity',
    title: 'Sollte Ihr Parfum eher intensiv oder dezent sein?',
    leftLabel: 'Intensiv',
    rightLabel: 'Dezent',
    centerLabel: 'Neutral',
  },
]

const currentStep = ref(0)
const answers = reactive<Record<string, string | number>>({})
const finished = ref(false)
const alreadyCompleted = ref(false)

const resolveStep = (idx: number): Step => {
  const safeIndex = Math.max(0, Math.min(idx, steps.length - 1))
  const resolved = steps[safeIndex]
  return resolved || steps[0]!
}

const step = computed<Step>(() => resolveStep(currentStep.value))

const isChoicePair = computed(() => step.value.type === 'choice' && step.value.layout === 'pair')
const isChoiceStack = computed(() => step.value.type === 'choice' && step.value.layout === 'stack')
const isChoiceGrid5 = computed(() => step.value.type === 'choice' && step.value.layout === 'grid5')
const isSliderStep = computed(() => step.value.type === 'slider')

const grid5LastOption = computed(() => {
  if (step.value.type !== 'choice') return ''
  return step.value.options[4] || ''
})

const canContinue = computed(() => {
  const s = step.value
  if (s.type === 'slider') return answers[s.key] !== undefined
  return Boolean(answers[s.key])
})

// Initialize slider defaults to center (index 2 = middle of 5)
watch(currentStep, (idx) => {
  const s = resolveStep(idx)
  if (s.type === 'slider' && answers[s.key] === undefined) {
    answers[s.key] = 2
  }
})

const selectOption = (value: string) => {
  answers[step.value.key] = value
}

const setSlider = (idx: number) => {
  answers[step.value.key] = idx
}

const goNext = () => {
  if (!canContinue.value) return
  if (currentStep.value < steps.length - 1) {
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
  <!-- Step screens -->
  <div v-if="!finished" class="nc-page flex flex-col bg-[#f5f0e8] text-[#1a1612]">
    <div class="nc-page-frame flex flex-1 flex-col">
      <SiteHeaderNav title="Parfum-Kuration" active="duftkuration" />

      <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col">

        <!-- Title -->
        <h1 class="text-3xl leading-tight text-[#1a1612] md:text-5xl lg:text-[54px]">
          {{ step.title }}
        </h1>

        <!-- Options area -->
        <div class="mt-12 flex flex-1 flex-col justify-center md:mt-16">

        <!-- Choice: pair (Mann/Frau) -->
        <div v-if="isChoicePair" class="flex justify-between gap-6">
          <button
            v-for="opt in (step as ChoiceStep).options"
            :key="opt"
            type="button"
            class="flex-1 rounded-2xl px-8 py-7 text-lg font-medium transition md:text-2xl"
            :class="answers[step.key] === opt
              ? 'bg-[#8e6c2a] text-[#f5f0e8]'
              : 'bg-[#b99a57] text-[#1a1612] hover:brightness-95'"
            @click="selectOption(opt)"
          >
            {{ opt }}
          </button>
        </div>

        <!-- Choice: stack (Jahreszeit) -->
        <div v-else-if="isChoiceStack" class="mx-auto flex w-full max-w-sm flex-col gap-4">
          <button
            v-for="opt in (step as ChoiceStep).options"
            :key="opt"
            type="button"
            class="rounded-2xl px-8 py-6 text-center text-lg font-medium transition md:text-xl"
            :class="answers[step.key] === opt
              ? 'bg-[#8e6c2a] text-[#f5f0e8]'
              : 'bg-[#b99a57] text-[#1a1612] hover:brightness-95'"
            @click="selectOption(opt)"
          >
            {{ opt }}
          </button>
        </div>

        <!-- Choice: grid5 (Anlass) -->
        <div v-else-if="isChoiceGrid5" class="flex flex-col gap-4">
          <div class="grid grid-cols-2 gap-4">
            <button
              v-for="opt in (step as ChoiceStep).options.slice(0, 4)"
              :key="opt"
              type="button"
              class="rounded-2xl px-6 py-6 text-center text-base font-medium leading-snug transition md:text-lg"
              :class="answers[step.key] === opt
                ? 'bg-[#8e6c2a] text-[#f5f0e8]'
                : 'bg-[#b99a57] text-[#1a1612] hover:brightness-95'"
              @click="selectOption(opt)"
            >
              {{ opt }}
            </button>
          </div>
          <div class="flex justify-center">
            <button
              type="button"
              class="rounded-2xl px-10 py-6 text-center text-base font-medium transition md:text-lg"
              :class="answers[step.key] === grid5LastOption
                ? 'bg-[#8e6c2a] text-[#f5f0e8]'
                : 'bg-[#b99a57] text-[#1a1612] hover:brightness-95'"
              @click="selectOption(grid5LastOption)"
            >
              {{ grid5LastOption }}
            </button>
          </div>
        </div>

        <!-- Slider -->
        <div v-else-if="isSliderStep" class="mt-8">
          <!-- Labels row -->
          <div class="relative flex justify-between text-base font-medium md:text-lg">
            <span>{{ (step as SliderStep).leftLabel }}</span>
            <span class="absolute left-1/2 -translate-x-1/2">{{ (step as SliderStep).centerLabel }}</span>
            <span>{{ (step as SliderStep).rightLabel }}</span>
          </div>
          <!-- Track -->
          <div class="relative mt-5 flex items-center">
            <div class="absolute left-0 right-0 h-px bg-[#b99a57]" />
            <div class="relative flex w-full justify-between">
              <button
                v-for="i in 5"
                :key="i"
                type="button"
                class="relative h-9 w-9 rounded-md border-2 transition"
                :class="answers[step.key] === (i - 1)
                  ? 'border-[#8e6c2a] bg-[#8e6c2a]'
                  : 'border-[#b99a57] bg-[#f5f0e8] hover:bg-[#e8ddc8]'"
                @click="setSlider(i - 1)"
              />
            </div>
          </div>
        </div>
        </div>

        <!-- Navigation -->
        <div class="mt-10 flex justify-between">
          <button
            v-if="currentStep > 0"
            type="button"
            class="rounded-xl border border-[#c8b48a] px-6 py-3 text-sm font-medium text-[#5a4820] transition hover:bg-[#ede5d4]"
            @click="goBack"
          >
            Zurück
          </button>
          <div v-else />
          <button
            type="button"
            :disabled="!canContinue"
            class="rounded-xl px-7 py-3 text-sm font-semibold transition disabled:opacity-40"
            :class="canContinue ? 'bg-[#b99a57] text-[#1a1612] hover:brightness-95' : 'bg-[#b99a57] text-[#1a1612]'"
            @click="goNext"
          >
            {{ currentStep === steps.length - 1 ? 'Abschliessen' : 'Weiter' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Completion screen -->
  <div v-else class="nc-page relative flex overflow-hidden bg-white text-[#1a1612]">
    <!-- Diagonal warm triangle -->
    <div class="pointer-events-none absolute inset-0">
      <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-full w-full">
        <polygon points="50,0 100,0 100,100" fill="#f5f0e8" />
      </svg>
    </div>
    <!-- Content -->
    <div class="nc-page-frame relative z-10 flex w-full flex-col">
      <SiteHeaderNav title="Parfum-Kuration" active="duftkuration" />

      <div class="flex flex-1 flex-col">
        <h1 class="max-w-2xl text-3xl leading-tight md:text-5xl">
          <template v-if="alreadyCompleted">
            Ihre Parfum-Kuration ist bereits abgeschlossen.<br>
            Ihre Samples stehen für Sie bereit.
          </template>
          <template v-else>
            Ihr Duftprofil wurde erstellt.<br>
            Wir kuratieren nun Ihre erste Persönliche Duftselektion.
          </template>
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

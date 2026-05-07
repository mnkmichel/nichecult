<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

type StepQuestion = {
  key: string
  title: string
  helper: string
  options: string[]
}

const steps: StepQuestion[] = [
  { key: 'mood', title: 'Wie soll der Duft wirken?', helper: 'Page 3', options: ['Elegant', 'Clean', 'Warm', 'Kraftvoll'] },
  { key: 'intensity', title: 'Welche Intensitaet bevorzugst du?', helper: 'Page 4', options: ['Leicht', 'Mittel', 'Intensiv', 'Sehr intensiv'] },
  { key: 'season', title: 'Fokus-Jahreszeit', helper: 'Page 5', options: ['Fruehling', 'Sommer', 'Herbst', 'Winter'] },
  { key: 'occasion', title: 'Wofuer suchst du den Duft?', helper: 'Page 6', options: ['Alltag', 'Office', 'Date Night', 'Event'] },
  { key: 'notes', title: 'Welche Noten magst du?', helper: 'Page 7', options: ['Zitrisch', 'Holzig', 'Orientalisch', 'Floral'] },
  { key: 'projection', title: 'Sillage-Profil', helper: 'Page 8', options: ['Diskret', 'Ausgewogen', 'Praesent', 'Raumfuellend'] },
  { key: 'longevity', title: 'Haltbarkeit', helper: 'Page 9', options: ['4-6h', '6-8h', '8-10h', '10h+'] },
  { key: 'budget', title: 'Budget pro 100 ml', helper: 'Page 10', options: ['<120 EUR', '120-180 EUR', '180-250 EUR', '250+ EUR'] },
]

const currentStep = ref(0)
const answers = reactive<Record<string, string>>({})
const finished = ref(false)

const canContinue = computed(() => {
  const current = steps[currentStep.value]
  return Boolean(answers[current.key])
})

const progress = computed(() => Math.round(((currentStep.value + 1) / steps.length) * 100))

const selectOption = (value: string) => {
  answers[steps[currentStep.value].key] = value
}

const goBack = () => {
  if (currentStep.value > 0) {
    currentStep.value -= 1
  }
}

const goNext = () => {
  if (!canContinue.value) {
    return
  }

  if (currentStep.value < steps.length - 1) {
    currentStep.value += 1
    return
  }

  localStorage.setItem('nichecult_duftkuration_done', '1')
  finished.value = true
}

const openSamples = async () => {
  await navigateTo('/sets')
}
</script>

<template>
  <main class="min-h-screen bg-[linear-gradient(165deg,#1d1a17_0%,#2b251e_45%,#3c3126_100%)] px-4 py-8 text-stone-100">
    <div class="mx-auto max-w-4xl">
      <header class="mb-8 space-y-5">
        <div>
          <p class="text-xs uppercase tracking-[0.28em] text-amber-300">Nichecult</p>
          <h1 class="mt-2 text-3xl font-bold md:text-4xl">Duft-Kuration</h1>
        </div>

        <nav class="inline-flex rounded-2xl border border-stone-600 bg-stone-900/70 p-1">
          <NuxtLink to="/parfum" class="rounded-xl px-4 py-2 text-sm font-semibold text-stone-200 hover:bg-stone-800">Parfum</NuxtLink>
          <span class="rounded-xl bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950">Duft-Kuration</span>
          <NuxtLink to="/sets" class="rounded-xl px-4 py-2 text-sm font-semibold text-stone-200 hover:bg-stone-800">Ihre Samples</NuxtLink>
        </nav>
      </header>

      <section class="rounded-3xl border border-stone-700 bg-stone-950/55 p-6 shadow-2xl md:p-8">
        <template v-if="!finished">
          <div class="mb-6 flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-stone-300">{{ steps[currentStep].helper }} von Page 3 bis 10</p>
            <p class="text-sm font-semibold text-amber-300">{{ progress }}%</p>
          </div>

          <div class="h-2 overflow-hidden rounded-full bg-stone-800">
            <div class="h-full rounded-full bg-amber-400 transition-all" :style="{ width: `${progress}%` }"></div>
          </div>

          <h2 class="mt-8 text-2xl font-semibold">{{ steps[currentStep].title }}</h2>
          <p class="mt-2 text-sm text-stone-300">Waehle eine Option, dann gehe weiter.</p>

          <div class="mt-6 grid gap-3 md:grid-cols-2">
            <button
              v-for="option in steps[currentStep].options"
              :key="option"
              type="button"
              class="rounded-2xl border px-4 py-3 text-left text-sm font-semibold transition"
              :class="answers[steps[currentStep].key] === option
                ? 'border-amber-300 bg-amber-300 text-stone-950'
                : 'border-stone-700 bg-stone-900/60 text-stone-100 hover:bg-stone-800'"
              @click="selectOption(option)"
            >
              {{ option }}
            </button>
          </div>

          <div class="mt-8 flex flex-wrap justify-between gap-3">
            <button type="button" class="rounded-xl border border-stone-600 px-4 py-2 text-sm font-semibold hover:bg-stone-800" @click="goBack">
              Zurueck
            </button>
            <button type="button" :disabled="!canContinue" class="rounded-xl bg-amber-400 px-5 py-2 text-sm font-semibold text-stone-950 disabled:opacity-50" @click="goNext">
              {{ currentStep === steps.length - 1 ? 'Abschliessen' : 'Weiter' }}
            </button>
          </div>
        </template>

        <template v-else>
          <p class="text-xs uppercase tracking-[0.28em] text-emerald-300">Abgeschlossen</p>
          <h2 class="mt-3 text-3xl font-bold">Ihre Duft-Kuration ist fertig</h2>
          <p class="mt-3 max-w-2xl text-stone-300">
            Der Unter-Reiter Ihre Samples ist jetzt freigeschaltet. Dort kannst du Samples oeffnen, bewerten und den Status auf Abgeschlossen bringen.
          </p>
          <button type="button" class="mt-6 rounded-xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-stone-950 hover:bg-emerald-400" @click="openSamples">
            Zu Ihren Samples
          </button>
        </template>
      </section>
    </div>
  </main>
</template>

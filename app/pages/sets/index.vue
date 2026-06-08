<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const { me, listSampleSets } = useAuthApi()

const loading = ref(true)
const error = ref('')
const profile = ref<Record<string, any> | null>(null)
const sampleSets = ref<Array<Record<string, any>>>([])
const nowTs = ref(Date.now())
let nowInterval: ReturnType<typeof setInterval> | null = null

const setStats = computed(() => {
  const total = sampleSets.value.length
  const open = sampleSets.value.filter((item) => String(item.set_status || '') === 'open').length
  const completed = sampleSets.value.filter((item) => String(item.set_status || '') === 'completed').length

  return { total, open, completed }
})

const statusLabel = (value: string) => {
  if (value === 'completed') {
    return 'Abgeschlossen'
  }
  if (value === 'delivered') {
    return 'Geliefert'
  }
  return 'Offen'
}

const parseApiDate = (value: unknown) => {
  const raw = String(value || '').trim()
  if (!raw) return null
  const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T')
  const date = new Date(normalized)
  return Number.isNaN(date.getTime()) ? null : date
}

const formatDeadline = (value: unknown) => {
  const date = parseApiDate(value)
  if (!date) return 'Keine Frist gesetzt'
  return date.toLocaleString('de-DE', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

const deadlineCountdown = (value: unknown, status: unknown) => {
  if (String(status || '') === 'completed') {
    return 'Bewertung abgeschlossen'
  }

  const date = parseApiDate(value)
  if (!date) {
    return 'Keine Frist gesetzt'
  }

  const diffMs = date.getTime() - nowTs.value
  if (diffMs <= 0) {
    return 'Frist abgelaufen'
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
}

const deadlineUrgency = (value: unknown, status: unknown): 'none' | 'normal' | 'soon' | 'urgent' | 'expired' => {
  if (String(status || '') === 'completed') {
    return 'none'
  }

  const date = parseApiDate(value)
  if (!date) {
    return 'none'
  }

  const diffSeconds = Math.floor((date.getTime() - nowTs.value) / 1000)
  if (diffSeconds <= 0) {
    return 'expired'
  }

  const diffHours = diffSeconds / 3600
  if (diffHours <= 6) {
    return 'urgent'
  }
  if (diffHours <= 24) {
    return 'soon'
  }
  return 'normal'
}

const deadlineTimerClass = (value: unknown, status: unknown) => {
  const urgency = deadlineUrgency(value, status)
  if (urgency === 'expired') {
    return 'bg-red-50 text-red-700 border border-red-200'
  }
  if (urgency === 'urgent') {
    return 'bg-amber-100 text-amber-900 border border-amber-200'
  }
  if (urgency === 'soon') {
    return 'bg-amber-50 text-amber-800 border border-amber-200'
  }
  return 'bg-[#f6efe2] text-[#5a4820] border border-[#e7dbc7]'
}

const statusBadgeClass = (value: unknown) => {
  const status = statusLabel(String(value || ''))
  if (status === 'Abgeschlossen') return 'bg-emerald-50 text-emerald-800 border border-emerald-200'
  if (status === 'Geliefert') return 'bg-sky-50 text-sky-800 border border-sky-200'
  return 'bg-amber-50 text-amber-900 border border-amber-200'
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

onMounted(() => {
  loadPageData()
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
  <main class="nc-page relative overflow-hidden bg-[radial-gradient(circle_at_top_left,rgba(255,245,230,0.95),rgba(245,240,232,1)_42%,rgba(236,228,214,1)_100%)]">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-[linear-gradient(180deg,rgba(255,255,255,0.58),rgba(255,255,255,0))]" />
    <div class="nc-page-frame relative">
      <div class="mx-auto w-full max-w-6xl">
        <SiteHeaderNav title="Ihre Samples" active="samples" />

      <section class="w-full pb-6 pt-2">
        <div class="grid gap-6 lg:grid-cols-[1.4fr_0.6fr] lg:items-end">
          <div class="space-y-4">
            <span class="inline-flex rounded-full border border-[#d8ccb0] bg-white/70 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-[#8b6c2d] shadow-sm backdrop-blur">
              Überblick
            </span>
            <div class="space-y-3">
              <h1 class="text-3xl font-semibold tracking-tight text-[#1a1612] sm:text-4xl lg:text-5xl">
                Ihre Sample-Sets
              </h1>
              <p class="max-w-2xl text-sm leading-6 text-[#5a4820] sm:text-base">
                Hier sehen Sie alle zugewiesenen Sets, den aktuellen Status und die jeweilige Bewertungsfrist auf einen Blick.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="w-full pb-16">
        <div v-if="loading" class="rounded-3xl border border-white/70 bg-white/75 p-6 text-stone-600 shadow-[0_20px_50px_rgba(79,61,31,0.08)] backdrop-blur">
          Lade Daten...
        </div>
        <div v-else-if="error" class="rounded-3xl border border-red-200 bg-red-50 p-6 text-red-700 shadow-[0_20px_50px_rgba(127,29,29,0.08)]">
          {{ error }}
        </div>

        <div v-else class="space-y-6">

          <div v-if="sampleSets.length === 0" class="rounded-3xl border border-dashed border-[#d8ccb0] bg-white/70 p-8 text-stone-600 shadow-[0_20px_50px_rgba(79,61,31,0.06)] backdrop-blur">
            Noch keine Sample-Sets zugewiesen.
          </div>

          <div v-else class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="item in sampleSets"
              :key="item.user_sample_set_id"
              class="flex h-full flex-col rounded-2xl border border-[#e4d8c4] bg-white/90 p-5 shadow-[0_10px_26px_rgba(79,61,31,0.07)] transition"
            >
              <div class="flex h-full flex-col">
                <div class="flex items-start justify-between gap-3">
                  <div class="space-y-1">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8b6c2d]">Sample-Set</p>
                    <h2 class="text-lg font-semibold tracking-tight text-stone-900">{{ item.title }}</h2>
                  </div>
                  <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em]" :class="statusBadgeClass(item.set_status)">
                    {{ statusLabel(item.set_status) }}
                  </span>
                </div>

                <p v-if="item.description" class="mt-2 text-sm leading-6 text-[#5a4820]">{{ item.description }}</p>

                <div class="mt-3 rounded-lg px-3 py-2" :class="deadlineTimerClass(item.rating_deadline_at, item.set_status)">
                  <p class="text-sm font-semibold leading-none">{{ deadlineCountdown(item.rating_deadline_at, item.set_status) }}</p>
                </div>

                <div class="mt-4" />
                <button
                  type="button"
                  class="mt-auto block w-full rounded-xl bg-[#1a1612] px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-[#2a241d]"
                  @click="openSet(item.user_sample_set_id)"
                >
                  Set öffnen
                </button>
              </div>
            </article>
          </div>
        </div>
      </section>
      </div>
    </div>
  </main>
</template>
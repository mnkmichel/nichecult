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
      <SiteHeaderNav title="Ihre Samples" active="samples" />

      <section class="mx-auto max-w-6xl px-4 pb-6 pt-2 sm:px-6 lg:px-8">
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

          <div class="grid grid-cols-3 gap-3 rounded-3xl border border-white/70 bg-white/70 p-4 shadow-[0_20px_50px_rgba(79,61,31,0.08)] backdrop-blur">
            <div class="rounded-2xl bg-[#f7f1e6] px-3 py-4 text-center">
              <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8b6c2d]">Gesamt</p>
              <p class="mt-2 text-2xl font-semibold text-[#1a1612]">{{ setStats.total }}</p>
            </div>
            <div class="rounded-2xl bg-[#f7f1e6] px-3 py-4 text-center">
              <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8b6c2d]">Offen</p>
              <p class="mt-2 text-2xl font-semibold text-[#1a1612]">{{ setStats.open }}</p>
            </div>
            <div class="rounded-2xl bg-[#f7f1e6] px-3 py-4 text-center">
              <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8b6c2d]">Fertig</p>
              <p class="mt-2 text-2xl font-semibold text-[#1a1612]">{{ setStats.completed }}</p>
            </div>
          </div>
        </div>
      </section>

      <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6 lg:px-8">
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
              class="group overflow-hidden rounded-[28px] border border-white/70 bg-white/80 shadow-[0_18px_45px_rgba(79,61,31,0.09)] backdrop-blur transition duration-300 hover:-translate-y-1 hover:shadow-[0_26px_60px_rgba(79,61,31,0.14)]"
            >
              <div class="relative nc-perfume-card-media">
                <img
                  v-if="item.image_url"
                  :src="item.image_url"
                  alt="Setbild"
                  class="nc-perfume-card-image"
                />
                <div v-else class="h-full w-full rounded-2xl bg-[#e7dbc7]"></div>
                <div class="absolute inset-x-4 top-4 flex items-center justify-between gap-3">
                  <span class="rounded-full bg-white/85 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-[#5a4820] shadow-sm backdrop-blur">
                    {{ item.perfume_count }} Parfums
                  </span>
                  <span class="rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-white shadow-sm" :class="statusLabel(item.set_status) === 'Abgeschlossen' ? 'bg-emerald-600/90' : statusLabel(item.set_status) === 'Geliefert' ? 'bg-sky-600/90' : 'bg-amber-600/90'">
                    {{ statusLabel(item.set_status) }}
                  </span>
                </div>
              </div>

              <div class="space-y-4 p-5 sm:p-6">
                <div class="space-y-2">
                  <h2 class="text-xl font-semibold tracking-tight text-[#1a1612]">{{ item.title }}</h2>
                  <p v-if="item.description" class="text-sm leading-6 text-[#5a4820]">{{ item.description }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full bg-[#f6efe2] px-3 py-1 text-xs font-medium text-[#5a4820]">{{ formatDeadline(item.rating_deadline_at) }}</span>
                  <span class="rounded-full bg-[#f6efe2] px-3 py-1 text-xs font-medium text-[#5a4820]">{{ deadlineCountdown(item.rating_deadline_at, item.set_status) }}</span>
                </div>

                <button
                  type="button"
                  class="block w-full rounded-2xl bg-[#1a1612] px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-[#2a241d]"
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
  </main>
</template>
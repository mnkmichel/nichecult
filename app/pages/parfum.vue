<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const { listPerfumes, listSampleSets, getSampleSetDetail } = useAuthApi()

const loading = ref(true)
const error = ref('')
const sortBy = ref<'relevance' | 'price-desc' | 'price-asc' | 'abc'>('relevance')
const perfumes = ref<Array<Record<string, any>>>([])
const ratingByPerfumeId = ref<Record<number, number>>({})

const priceLabel = (cents: number) => {
  const value = Math.round(cents) / 100
  return `100 ml · ${value.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`
}

const discountedCents = (baseCents: number) => Math.round(baseCents * 0.85)

const perfumeIdOf = (item: Record<string, any>) => Number(item?.id ?? item?.perfume_id ?? NaN)

const isTested = (perfumeId: number) => Number.isFinite(ratingByPerfumeId.value[Number(perfumeId)])

const relevanceScore = (perfumeId: number) => ratingByPerfumeId.value[Number(perfumeId)] ?? -1

const scoreOutOfFiveLabel = (perfumeId: number) => {
  const raw = relevanceScore(perfumeId)
  if (!Number.isFinite(raw) || raw < 0) {
    return '1/5'
  }
  const normalized = Math.max(1, Math.min(5, raw / 2))
  return `${normalized.toFixed(1).replace('.0', '')}/5`
}

const sortedPerfumes = computed(() => {
  const items = [...perfumes.value]

  if (sortBy.value === 'abc') {
    return items.sort((a, b) => String(a.name).localeCompare(String(b.name), 'de'))
  }

  if (sortBy.value === 'price-asc') {
    return items.sort((a, b) => {
      const aId = perfumeIdOf(a)
      const bId = perfumeIdOf(b)
      const aPrice = isTested(aId) ? discountedCents(Number(a.price_cents || 0)) : Number(a.price_cents || 0)
      const bPrice = isTested(bId) ? discountedCents(Number(b.price_cents || 0)) : Number(b.price_cents || 0)
      return aPrice - bPrice
    })
  }

  if (sortBy.value === 'price-desc') {
    return items.sort((a, b) => {
      const aId = perfumeIdOf(a)
      const bId = perfumeIdOf(b)
      const aPrice = isTested(aId) ? discountedCents(Number(a.price_cents || 0)) : Number(a.price_cents || 0)
      const bPrice = isTested(bId) ? discountedCents(Number(b.price_cents || 0)) : Number(b.price_cents || 0)
      return bPrice - aPrice
    })
  }

  return items.sort((a, b) => {
    const aId = perfumeIdOf(a)
    const bId = perfumeIdOf(b)
    const aTested = isTested(aId)
    const bTested = isTested(bId)

    if (aTested && !bTested) return -1
    if (!aTested && bTested) return 1

    const scoreDiff = relevanceScore(bId) - relevanceScore(aId)
    if (scoreDiff !== 0) {
      return scoreDiff
    }

    return String(a.name).localeCompare(String(b.name), 'de')
  })
})

const loadData = async () => {
  loading.value = true
  error.value = ''

  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/login')
      return
    }

    const [perfumeRes, sampleSetRes] = await Promise.all([
      listPerfumes(),
      listSampleSets(token),
    ])

    perfumes.value = perfumeRes.perfumes || []

    const allSets = sampleSetRes.sampleSets || []
    const detailResponses = await Promise.all(allSets.map(set => getSampleSetDetail(token, Number(set.user_sample_set_id))))

    const scoreBucket: Record<number, number[]> = {}
    for (const detail of detailResponses) {
      for (const perfume of detail.perfumes || []) {
        if (perfume.overall_score == null) {
          continue
        }

        const perfumeId = Number(perfume.perfume_id ?? (perfume as any).id ?? 0)
        if (!perfumeId) {
          continue
        }

        const values = [
          Number(perfume.overall_score || 0),
          Number(perfume.longevity_score || 0),
          Number(perfume.sillage_score || 0),
        ]
        const score = values.reduce((sum, current) => sum + current, 0) / 3

        if (!scoreBucket[perfumeId]) {
          scoreBucket[perfumeId] = []
        }

        const bucket = scoreBucket[perfumeId]
        if (bucket) {
          bucket.push(score)
        }
      }
    }

    const normalized: Record<number, number> = {}
    for (const [id, scores] of Object.entries(scoreBucket)) {
      normalized[Number(id)] = scores.reduce((sum, score) => sum + score, 0) / scores.length
    }

    ratingByPerfumeId.value = normalized
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Parfums konnten nicht geladen werden'
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
</script>

<template>
  <main class="nc-page relative overflow-hidden bg-[radial-gradient(circle_at_top_left,rgba(255,246,231,0.95),rgba(246,238,225,1)_42%,rgba(237,226,207,1)_100%)] text-stone-900">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-[linear-gradient(180deg,rgba(255,255,255,0.58),rgba(255,255,255,0))]"></div>
    <div class="nc-page-frame relative">
      <SiteHeaderNav title="Parfum" active="parfum" />

      <section class="mx-auto max-w-6xl px-4 pb-6 pt-2 sm:px-6 lg:px-8">
        <div class="space-y-4">
          <span class="inline-flex rounded-full border border-[#d8ccb0] bg-white/70 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-[#8b6c2d] shadow-sm backdrop-blur">
            Duftkatalog
          </span>
          <div class="space-y-3">
            <h1 class="text-3xl font-semibold tracking-tight text-[#1a1612] sm:text-4xl lg:text-5xl">
              Parfums fuer Sie
            </h1>
            <p class="max-w-2xl text-sm leading-6 text-[#5a4820] sm:text-base">
              Relevanz zeigt getestete Duefte zuerst, sortiert nach Ihrer bisherigen Bewertung.
            </p>
          </div>
        </div>
      </section>

      <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-stone-200/70 bg-white/85 p-5 shadow-[0_20px_50px_rgba(79,61,31,0.08)] backdrop-blur sm:p-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold tracking-tight text-[#1a1612]">Sortierung</h2>
              <p class="text-sm text-[#5a4820]">Getestete Duefte koennen direkt mit Vorteilspreis gefunden werden.</p>
            </div>

            <select v-model="sortBy" class="rounded-xl border border-[#d0c1a4] bg-[#fffaf1] px-4 py-2 text-sm font-semibold text-[#3d3122] shadow-sm">
              <option value="relevance">Relevanz</option>
              <option value="price-desc">Preis hoch nach niedrig</option>
              <option value="price-asc">Preis niedrig nach hoch</option>
              <option value="abc">ABC</option>
            </select>
          </div>

          <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
            Kunden der Parfum-Kuration erhalten 15% Vorteil auf getestete Duefte.
          </p>
        </div>

        <div v-if="loading" class="mt-6 rounded-3xl border border-white/70 bg-white/75 p-6 text-stone-600 shadow-[0_20px_50px_rgba(79,61,31,0.08)] backdrop-blur">
          Lade Parfums...
        </div>
        <div v-else-if="error" class="mt-6 rounded-3xl border border-red-200 bg-red-50 p-6 text-red-700 shadow-[0_20px_50px_rgba(127,29,29,0.08)]">
          {{ error }}
        </div>

        <section v-else class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="item in sortedPerfumes"
            :key="`${perfumeIdOf(item)}-${item.name}`"
            class="group overflow-hidden rounded-[28px] border border-white/70 bg-white/80 shadow-[0_18px_45px_rgba(79,61,31,0.09)] backdrop-blur transition duration-300 hover:-translate-y-1 hover:shadow-[0_26px_60px_rgba(79,61,31,0.14)]"
          >
            <div class="relative nc-perfume-card-media nc-perfume-card-media-tall -mb-1 overflow-hidden bg-[linear-gradient(180deg,#f8f4ec,#f0e5d3)] ring-1 ring-[#eadfc9]">
              <img
                v-if="item.image_url"
                :src="item.image_url"
                alt="Parfumbild"
                class="nc-perfume-card-image"
              >
              <div v-else class="h-full w-full rounded-2xl bg-[#e7dbc7]"></div>
              <div class="absolute left-3 top-2 flex flex-col items-start gap-1">
                <span
                  v-if="isTested(perfumeIdOf(item))"
                  class="rounded-full bg-emerald-600/90 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-white shadow-sm"
                >
                  Getestet
                </span>
                <span
                  v-else
                  class="rounded-full bg-amber-600/90 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-white shadow-sm"
                >
                  Neu
                </span>
              </div>
            </div>

            <div class="space-y-2 p-3 pb-2.5 sm:p-3.5 sm:pb-3">
              <div class="space-y-0.5">
                <p class="text-[10px] uppercase tracking-[0.18em] text-[#7f6a4c] sm:text-[11px]">{{ item.brand_name || 'Niche Cult' }}</p>
                <h3 class="text-[0.98rem] font-semibold tracking-tight text-[#1a1612] sm:text-[1.06rem]">{{ item.name }}</h3>
              </div>

              <div class="space-y-0.5 text-[12px]">
                <template v-if="isTested(perfumeIdOf(item))">
                  <p class="text-stone-500 line-through">Normal: {{ priceLabel(Number(item.price_cents || 0)) }}</p>
                  <p class="font-semibold text-emerald-700">Rabattiert: {{ priceLabel(discountedCents(Number(item.price_cents || 0))) }}</p>
                </template>
                <template v-else>
                  <p class="font-semibold text-stone-800">Normal: {{ priceLabel(Number(item.price_cents || 0)) }}</p>
                </template>
              </div>

              <div class="flex flex-wrap gap-1">
                <span v-if="isTested(perfumeIdOf(item))" class="rounded-full bg-[#e5f7ee] px-3 py-1 text-xs font-semibold text-emerald-800">
                  Score {{ scoreOutOfFiveLabel(perfumeIdOf(item)) }}
                </span>
                <span v-else class="rounded-full bg-[#f8ecd4] px-3 py-1 text-xs font-semibold text-amber-900">Noch nicht getestet</span>
              </div>
            </div>
          </article>
        </section>
      </section>
    </div>
  </main>
</template>

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
  <main class="nc-page bg-[linear-gradient(180deg,#f6f2ea_0%,#fffaf1_45%,#f7efe0_100%)] text-stone-900">
    <div class="nc-page-frame">
      <SiteHeaderNav title="Parfum" active="parfum" />

      <section class="rounded-3xl border border-stone-200 bg-white/90 p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold">Sortierung</h2>
            <p class="text-sm text-stone-600">Relevanz zeigt getestete Samples zuerst, von bester zu schlechtester Bewertung.</p>
          </div>

          <select v-model="sortBy" class="rounded-xl border border-stone-300 bg-white px-4 py-2 text-sm font-semibold">
            <option value="relevance">Relevanz</option>
            <option value="price-desc">Preis hoch nach niedrig</option>
            <option value="price-asc">Preis niedrig nach hoch</option>
            <option value="abc">ABC</option>
          </select>
        </div>

        <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
          Kunden der Parfum-Kuration erhalten einen 15% Vorteil auf getestete Duefte.
        </p>
      </section>

      <div v-if="loading" class="mt-6 rounded-2xl border border-stone-200 bg-white p-5 text-stone-600">Lade Parfums...</div>
      <div v-else-if="error" class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5 text-red-700">{{ error }}</div>

      <section v-else class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <article v-for="item in sortedPerfumes" :key="`${perfumeIdOf(item)}-${item.name}`" class="overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm">
          <img v-if="item.image_url" :src="item.image_url" alt="Parfumbild" class="h-56 w-full object-cover" />
          <div class="p-5">
            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">{{ item.brand_name || 'Nice Cult Selection' }}</p>
            <h3 class="mt-2 text-xl font-semibold">{{ item.name }}</h3>
            <p v-if="item.description" class="mt-2 text-sm text-stone-600">{{ item.description }}</p>

            <div class="mt-4 space-y-1 text-sm">
              <template v-if="isTested(perfumeIdOf(item))">
                <p class="text-stone-500 line-through">Normal: {{ priceLabel(Number(item.price_cents || 0)) }}</p>
                <p class="font-semibold text-emerald-700">
                  Rabattiert: {{ priceLabel(discountedCents(Number(item.price_cents || 0))) }}
                  <span class="font-normal text-stone-500">(regulaer {{ priceLabel(Number(item.price_cents || 0)) }})</span>
                </p>
              </template>
              <template v-else>
                <p class="font-semibold text-stone-800">Normal: {{ priceLabel(Number(item.price_cents || 0)) }}</p>
              </template>
            </div>

            <div class="mt-4">
              <span v-if="isTested(perfumeIdOf(item))" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                Getestet · Score {{ relevanceScore(perfumeIdOf(item)).toFixed(1) }}
              </span>
              <span v-else class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">Noch nicht getestet</span>
            </div>
          </div>
        </article>
      </section>
    </div>
  </main>
</template>

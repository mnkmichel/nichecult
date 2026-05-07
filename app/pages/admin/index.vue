<script setup lang="ts">
definePageMeta({
  middleware: 'admin',
})

const { listAdminUsers, listAdminPerfumes, listAdminSampleSets } = useAuthApi()
const config = useRuntimeConfig()
const apiBase = (config.public.apiBase as string).replace(/\/$/, '')

const users = ref<Array<Record<string, any>>>([])
const perfumes = ref<Array<Record<string, any>>>([])
const sampleSets = ref<Array<Record<string, any>>>([])
const createdPerfume = ref<Record<string, any> | null>(null)
const createdSet = ref<Record<string, any> | null>(null)
const loading = ref(true)
const error = ref('')
const success = ref('')
const perfumeImagePreview = ref<string | null>(null)
const setImagePreview = ref<string | null>(null)
const perfumeImageFile = ref<File | null>(null)
const setImageFile = ref<File | null>(null)
const savingPerfume = ref(false)
const savingSet = ref(false)

const perfumeForm = reactive({
  name: '',
  brand_name: '',
  description: '',
  size_ml: '100',
  price_cents: '0',
  discount_percent: '0',
  is_active: '1',
})

const setForm = reactive({
  title: '',
  description: '',
  status: 'active',
  assign_user_id: '',
  perfume_ids: [] as number[],
})

const perfumeDrafts = reactive<Record<number, {
  name: string
  brand_name: string
  description: string
  size_ml: string
  price_cents: string
  discount_percent: string
  is_active: string
}>>({})

const setDrafts = reactive<Record<number, {
  title: string
  description: string
  status: string
  assign_user_id: string
  perfume_ids: number[]
}>>({})

const getPerfumeDraft = (perfumeId: number) => {
  if (!perfumeDrafts[perfumeId]) {
    perfumeDrafts[perfumeId] = {
      name: '',
      brand_name: '',
      description: '',
      size_ml: '100',
      price_cents: '0',
      discount_percent: '0',
      is_active: '1',
    }
  }
  return perfumeDrafts[perfumeId]!
}

const getSetDraft = (setId: number) => {
  if (!setDrafts[setId]) {
    setDrafts[setId] = {
      title: '',
      description: '',
      status: 'active',
      assign_user_id: '',
      perfume_ids: [],
    }
  }
  return setDrafts[setId]!
}

const perfumeEditFile = reactive<Record<number, File | null>>({})
const setEditFile = reactive<Record<number, File | null>>({})

const savingPerfumeId = ref<number | null>(null)
const savingSetId = ref<number | null>(null)
const assigningSetId = ref<number | null>(null)
const deletingPerfumeId = ref<number | null>(null)
const deletingSetId = ref<number | null>(null)
const showCreatePerfume = ref(false)
const showCreateSet = ref(false)
const openPerfumeEditorId = ref<number | null>(null)
const openSetEditorId = ref<number | null>(null)
const openAssignUserId = ref<number | null>(null)

const priceEuro = (priceCents: unknown) => {
  return (Number(priceCents || 0) / 100).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const togglePerfumeEditor = (perfumeId: number) => {
  openPerfumeEditorId.value = openPerfumeEditorId.value === perfumeId ? null : perfumeId
}

const toggleSetEditor = (setId: number) => {
  openSetEditorId.value = openSetEditorId.value === setId ? null : setId
}

const toggleAssignUser = (setId: number) => {
  openAssignUserId.value = openAssignUserId.value === setId ? null : setId
}

const hydrateDrafts = () => {
  for (const perfume of perfumes.value) {
    perfumeDrafts[perfume.id] = {
      name: String(perfume.name || ''),
      brand_name: String(perfume.brand_name || ''),
      description: String(perfume.description || ''),
      size_ml: String(perfume.size_ml ?? 100),
      price_cents: String(perfume.price_cents ?? 0),
      discount_percent: String(perfume.discount_percent ?? 0),
      is_active: String(Number(perfume.is_active ?? 1)),
    }
  }

  for (const set of sampleSets.value) {
    setDrafts[set.id] = {
      title: String(set.title || ''),
      description: String(set.description || ''),
      status: String(set.status || 'active'),
      assign_user_id: '',
      perfume_ids: Array.isArray(set.perfume_ids) ? set.perfume_ids.map((id: any) => Number(id)) : [],
    }
  }
}

const loadAdminData = async () => {
  loading.value = true
  error.value = ''

  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/admin/login')
      return
    }

    const [usersRes, perfumesRes, setsRes] = await Promise.all([
      listAdminUsers(token),
      listAdminPerfumes(token),
      listAdminSampleSets(token),
    ])

    users.value = usersRes.users || []
    perfumes.value = perfumesRes.perfumes || []
    sampleSets.value = setsRes.sampleSets || []
    hydrateDrafts()
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Admin-Daten konnten nicht geladen werden'
  } finally {
    loading.value = false
  }
}

const onPerfumeImageChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] || null
  perfumeImageFile.value = file
  perfumeImagePreview.value = file ? URL.createObjectURL(file) : null
}

const onSetImageChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] || null
  setImageFile.value = file
  setImagePreview.value = file ? URL.createObjectURL(file) : null
}

const createPerfume = async () => {
  error.value = ''
  success.value = ''
  savingPerfume.value = true

  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/admin/login')
      return
    }

    const body = new FormData()
    body.append('name', perfumeForm.name)
    body.append('brand_name', perfumeForm.brand_name)
    body.append('description', perfumeForm.description)
    body.append('size_ml', perfumeForm.size_ml)
    body.append('price_cents', perfumeForm.price_cents)
    body.append('discount_percent', perfumeForm.discount_percent)
    body.append('is_active', perfumeForm.is_active)
    if (perfumeImageFile.value) {
      body.append('image', perfumeImageFile.value)
    }

    const res = await $fetch<{ ok: boolean; perfume?: Record<string, any>; error?: string }>(`${apiBase}/admin-create-perfume.php`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body,
    })

    if (!res.ok) {
      error.value = res.error || 'Parfum konnte nicht angelegt werden'
      return
    }

    createdPerfume.value = res.perfume || null
    success.value = 'Parfum erfolgreich angelegt.'
    perfumeForm.name = ''
    perfumeForm.brand_name = ''
    perfumeForm.description = ''
    perfumeForm.size_ml = '100'
    perfumeForm.price_cents = '0'
    perfumeForm.discount_percent = '0'
    perfumeForm.is_active = '1'
    perfumeImageFile.value = null
    perfumeImagePreview.value = null
    await loadAdminData()
  } catch (e: any) {
    error.value = e?.data?.details || e?.data?.error || e?.message || 'Parfum konnte nicht angelegt werden'
  } finally {
    savingPerfume.value = false
  }
}

const togglePerfumeSelection = (perfumeId: number) => {
  if (setForm.perfume_ids.includes(perfumeId)) {
    setForm.perfume_ids = setForm.perfume_ids.filter(id => id !== perfumeId)
  } else if (setForm.perfume_ids.length < 5) {
    setForm.perfume_ids = [...setForm.perfume_ids, perfumeId]
  }
}

const createSampleSet = async () => {
  error.value = ''
  success.value = ''
  savingSet.value = true

  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/admin/login')
      return
    }

    const body = new FormData()
    body.append('title', setForm.title)
    body.append('description', setForm.description)
    body.append('status', setForm.status)
    body.append('perfume_ids', JSON.stringify(setForm.perfume_ids))
    if (setForm.assign_user_id) {
      body.append('assign_user_id', setForm.assign_user_id)
    }
    if (setImageFile.value) {
      body.append('image', setImageFile.value)
    }

    const res = await $fetch<{ ok: boolean; sampleSet?: Record<string, any>; error?: string }>(`${apiBase}/admin-create-sample-set.php`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body,
    })

    if (!res.ok) {
      error.value = res.error || 'Sample-Set konnte nicht angelegt werden'
      return
    }

    createdSet.value = res.sampleSet || null
    success.value = 'Sample-Set erfolgreich angelegt.'
    setForm.title = ''
    setForm.description = ''
    setForm.status = 'active'
    setForm.assign_user_id = ''
    setForm.perfume_ids = []
    setImageFile.value = null
    setImagePreview.value = null
    await loadAdminData()
  } catch (e: any) {
    error.value = e?.data?.details || e?.data?.error || e?.message || 'Sample-Set konnte nicht angelegt werden'
  } finally {
    savingSet.value = false
  }
}

const onPerfumeEditImageChange = (perfumeId: number, event: Event) => {
  const target = event.target as HTMLInputElement
  perfumeEditFile[perfumeId] = target.files?.[0] || null
}

const onSetEditImageChange = (setId: number, event: Event) => {
  const target = event.target as HTMLInputElement
  setEditFile[setId] = target.files?.[0] || null
}

const toggleSetDraftPerfume = (setId: number, perfumeId: number) => {
  const draft = getSetDraft(setId)
  if (!draft) return

  if (draft.perfume_ids.includes(perfumeId)) {
    draft.perfume_ids = draft.perfume_ids.filter(id => id !== perfumeId)
  } else if (draft.perfume_ids.length < 5) {
    draft.perfume_ids = [...draft.perfume_ids, perfumeId]
  }
}

const updatePerfume = async (perfumeId: number) => {
  const draft = getPerfumeDraft(perfumeId)
  if (!draft) return

  savingPerfumeId.value = perfumeId
  error.value = ''
  success.value = ''
  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/admin/login')
      return
    }

    const body = new FormData()
    body.append('id', String(perfumeId))
    body.append('name', draft.name)
    body.append('brand_name', draft.brand_name)
    body.append('description', draft.description)
    body.append('size_ml', draft.size_ml)
    body.append('price_cents', draft.price_cents)
    body.append('discount_percent', draft.discount_percent)
    body.append('is_active', draft.is_active)
    if (perfumeEditFile[perfumeId]) {
      body.append('image', perfumeEditFile[perfumeId] as File)
    }

    const res = await $fetch<{ ok: boolean; error?: string }>(`${apiBase}/admin-update-perfume.php`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      body,
    })

    if (!res.ok) {
      error.value = res.error || 'Parfum konnte nicht aktualisiert werden'
      return
    }

    success.value = 'Parfum aktualisiert.'
    perfumeEditFile[perfumeId] = null
    await loadAdminData()
  } catch (e: any) {
    error.value = e?.data?.details || e?.data?.error || e?.message || 'Parfum konnte nicht aktualisiert werden'
  } finally {
    savingPerfumeId.value = null
  }
}

const deletePerfume = async (perfumeId: number) => {
  if (process.client && !window.confirm('Parfum wirklich loeschen?')) {
    return
  }

  deletingPerfumeId.value = perfumeId
  error.value = ''
  success.value = ''
  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/admin/login')
      return
    }

    const body = new FormData()
    body.append('id', String(perfumeId))
    const res = await $fetch<{ ok: boolean; error?: string }>(`${apiBase}/admin-delete-perfume.php`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      body,
    })

    if (!res.ok) {
      error.value = res.error || 'Parfum konnte nicht geloescht werden'
      return
    }

    success.value = 'Parfum geloescht.'
    await loadAdminData()
  } catch (e: any) {
    error.value = e?.data?.details || e?.data?.error || e?.message || 'Parfum konnte nicht geloescht werden'
  } finally {
    deletingPerfumeId.value = null
  }
}

const updateSampleSet = async (setId: number) => {
  const draft = getSetDraft(setId)
  if (!draft) return

  savingSetId.value = setId
  error.value = ''
  success.value = ''
  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/admin/login')
      return
    }

    const body = new FormData()
    body.append('id', String(setId))
    body.append('title', draft.title)
    body.append('description', draft.description)
    body.append('status', draft.status)
    body.append('perfume_ids', JSON.stringify(draft.perfume_ids))
    if (draft.assign_user_id) {
      body.append('assign_user_id', draft.assign_user_id)
    }
    if (setEditFile[setId]) {
      body.append('image', setEditFile[setId] as File)
    }

    const res = await $fetch<{ ok: boolean; error?: string }>(`${apiBase}/admin-update-sample-set.php`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      body,
    })

    if (!res.ok) {
      error.value = res.error || 'Sample-Set konnte nicht aktualisiert werden'
      return
    }

    success.value = 'Sample-Set aktualisiert.'
    setEditFile[setId] = null
    await loadAdminData()
  } catch (e: any) {
    error.value = e?.data?.details || e?.data?.error || e?.message || 'Sample-Set konnte nicht aktualisiert werden'
  } finally {
    savingSetId.value = null
  }
}

const deleteSampleSet = async (setId: number) => {
  if (process.client && !window.confirm('Sample-Set wirklich loeschen?')) {
    return
  }

  deletingSetId.value = setId
  error.value = ''
  success.value = ''
  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/admin/login')
      return
    }

    const body = new FormData()
    body.append('id', String(setId))
    const res = await $fetch<{ ok: boolean; error?: string }>(`${apiBase}/admin-delete-sample-set.php`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      body,
    })

    if (!res.ok) {
      error.value = res.error || 'Sample-Set konnte nicht geloescht werden'
      return
    }

    success.value = 'Sample-Set geloescht.'
    await loadAdminData()
  } catch (e: any) {
    error.value = e?.data?.details || e?.data?.error || e?.message || 'Sample-Set konnte nicht geloescht werden'
  } finally {
    deletingSetId.value = null
  }
}

const assignSampleSetToUser = async (setId: number) => {
  const draft = getSetDraft(setId)
  if (!draft || !draft.assign_user_id) {
    error.value = 'Bitte zuerst einen Nutzer auswaehlen.'
    return
  }

  assigningSetId.value = setId
  error.value = ''
  success.value = ''
  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/admin/login')
      return
    }

    const body = new FormData()
    body.append('sample_set_id', String(setId))
    body.append('user_id', draft.assign_user_id)
    body.append('set_status', 'delivered')

    const res = await $fetch<{ ok: boolean; error?: string }>(`${apiBase}/admin-assign-sample-set.php`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      body,
    })

    if (!res.ok) {
      error.value = res.error || 'Set konnte nicht zugewiesen werden'
      return
    }

    success.value = 'Set wurde dem Nutzer zugewiesen.'
    await loadAdminData()
  } catch (e: any) {
    error.value = e?.data?.details || e?.data?.error || e?.message || 'Set konnte nicht zugewiesen werden'
  } finally {
    assigningSetId.value = null
  }
}

const logout = async () => {
  localStorage.removeItem('nichecult_token')
  await navigateTo('/admin/login')
}

onMounted(loadAdminData)
</script>

<template>
  <main class="min-h-screen bg-[radial-gradient(circle_at_top,#2e261d,#111111_60%)] px-4 py-8 text-stone-100">
    <div class="mx-auto max-w-7xl space-y-8">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-[0.3em] text-amber-400">Nichecult Admin</p>
          <h1 class="mt-2 text-3xl font-bold">Admin Verwaltung</h1>
          <p class="mt-2 text-sm text-stone-400">Parfüm-Liste, Set-Liste und User-Zuweisung an einem Ort.</p>
        </div>

        <button class="rounded-xl border border-stone-700 bg-stone-900/70 px-4 py-2 text-sm font-semibold hover:bg-stone-800" @click="logout">
          Logout
        </button>
      </header>

      <div v-if="loading" class="rounded-2xl border border-stone-800 bg-stone-900/70 p-5 text-stone-300">Lade Admin-Daten...</div>
      <div v-else class="space-y-8">
        <div v-if="success" class="rounded-2xl border border-emerald-800 bg-emerald-950/40 p-4 text-emerald-300">{{ success }}</div>
        <div v-if="error" class="rounded-2xl border border-red-900 bg-red-950/60 p-4 text-red-300">{{ error }}</div>

        <section class="rounded-3xl border border-stone-800 bg-stone-900/75 p-6 shadow-2xl">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-2xl font-semibold">Parfüm Liste</h2>
              <p class="mt-1 text-sm text-stone-400">{{ perfumes.length }} Einträge</p>
            </div>
            <button class="rounded-xl bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950" @click="showCreatePerfume = !showCreatePerfume">
              + Parfüm erstellen
            </button>
          </div>

          <form v-if="showCreatePerfume" class="mt-5 space-y-4 rounded-2xl border border-stone-800 bg-stone-950/60 p-5" @submit.prevent="createPerfume">
            <div class="grid gap-4 md:grid-cols-2">
              <input v-model="perfumeForm.name" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="text" placeholder="Parfumname" required />
              <input v-model="perfumeForm.brand_name" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="text" placeholder="Marke" />
            </div>
            <textarea v-model="perfumeForm.description" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" rows="3" placeholder="Beschreibung"></textarea>
            <div class="grid gap-4 md:grid-cols-4">
              <input v-model="perfumeForm.size_ml" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="number" min="1" placeholder="ml" />
              <input v-model="perfumeForm.price_cents" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="number" min="0" placeholder="Preis in Cent" />
              <input v-model="perfumeForm.discount_percent" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="number" min="0" max="100" placeholder="Rabatt %" />
              <select v-model="perfumeForm.is_active" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3">
                <option value="1">Aktiv</option>
                <option value="0">Inaktiv</option>
              </select>
            </div>
            <div class="space-y-2">
              <p class="text-sm font-semibold text-stone-200">Parfümbild hochladen</p>
              <label class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-stone-600 bg-stone-900 px-4 py-3 text-sm font-semibold text-stone-100 transition hover:bg-stone-800">
                Bild auswählen
                <input class="hidden" type="file" accept="image/png,image/jpeg,image/webp" @change="onPerfumeImageChange" />
              </label>
              <p class="text-xs text-stone-400">{{ perfumeImageFile ? perfumeImageFile.name : 'Kein Bild ausgewählt' }}</p>
            </div>
            <img v-if="perfumeImagePreview" :src="perfumeImagePreview" alt="Parfumvorschau" class="h-48 w-full rounded-2xl object-cover" />
            <button :disabled="savingPerfume" class="rounded-xl bg-amber-400 px-4 py-3 font-semibold text-stone-950 disabled:opacity-60">
              {{ savingPerfume ? 'Speichert...' : 'Parfum speichern' }}
            </button>
          </form>

          <div class="mt-5 space-y-3">
            <article v-for="perfume in perfumes" :key="perfume.id" class="rounded-2xl border border-stone-800 bg-stone-950/70 p-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p class="text-lg font-semibold text-stone-100">{{ perfume.name }}</p>
                  <p class="mt-1 text-sm text-stone-400">{{ perfume.brand_name || 'Ohne Marke' }} · {{ perfume.size_ml || 100 }} ml · {{ priceEuro(perfume.price_cents) }} €</p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="Number(perfume.is_active || 0) === 1 ? 'bg-emerald-900/50 text-emerald-200' : 'bg-stone-800 text-stone-300'">
                    {{ Number(perfume.is_active || 0) === 1 ? 'Aktiv' : 'Inaktiv' }}
                  </span>
                  <button class="rounded-lg border border-stone-700 px-3 py-2 text-sm font-semibold text-stone-200 hover:bg-stone-800" @click="togglePerfumeEditor(perfume.id)">
                    {{ openPerfumeEditorId === perfume.id ? 'Schließen' : 'Bearbeiten' }}
                  </button>
                  <button class="rounded-lg border border-red-700 bg-red-950/50 px-3 py-2 text-sm font-semibold text-red-200" :disabled="deletingPerfumeId === perfume.id" @click="deletePerfume(perfume.id)">
                    {{ deletingPerfumeId === perfume.id ? 'Löscht...' : 'Löschen' }}
                  </button>
                </div>
              </div>

              <div v-if="openPerfumeEditorId === perfume.id" class="mt-4 grid gap-4 border-t border-stone-800 pt-4 md:grid-cols-[140px_1fr]">
                <img v-if="perfume.image_url" :src="perfume.image_url" alt="Parfumbild" class="h-36 w-full rounded-xl object-cover" />
                <div v-else class="h-36 w-full rounded-xl bg-stone-800" />
                <div class="space-y-3">
                  <div class="grid gap-3 md:grid-cols-2">
                    <input v-model="getPerfumeDraft(perfume.id).name" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" type="text" placeholder="Name" />
                    <input v-model="getPerfumeDraft(perfume.id).brand_name" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" type="text" placeholder="Marke" />
                  </div>
                  <textarea v-model="getPerfumeDraft(perfume.id).description" class="w-full rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" rows="2" placeholder="Beschreibung" />
                  <div class="grid gap-3 md:grid-cols-4">
                    <input v-model="getPerfumeDraft(perfume.id).size_ml" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" type="number" min="1" placeholder="ml" />
                    <input v-model="getPerfumeDraft(perfume.id).price_cents" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" type="number" min="0" placeholder="Cent" />
                    <input v-model="getPerfumeDraft(perfume.id).discount_percent" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" type="number" min="0" max="100" placeholder="%" />
                    <select v-model="getPerfumeDraft(perfume.id).is_active" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2">
                      <option value="1">Aktiv</option>
                      <option value="0">Inaktiv</option>
                    </select>
                  </div>
                  <div class="space-y-2">
                    <p class="text-sm font-semibold text-stone-200">Parfümbild ändern</p>
                    <label class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-stone-600 bg-stone-900 px-4 py-2 text-sm font-semibold text-stone-100 transition hover:bg-stone-800">
                      Neues Bild auswählen
                      <input class="hidden" type="file" accept="image/png,image/jpeg,image/webp" @change="onPerfumeEditImageChange(perfume.id, $event)" />
                    </label>
                    <p class="text-xs text-stone-400">{{ perfumeEditFile[perfume.id] ? perfumeEditFile[perfume.id]?.name : 'Kein neues Bild ausgewählt' }}</p>
                  </div>
                  <button class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 disabled:opacity-60" :disabled="savingPerfumeId === perfume.id" @click="updatePerfume(perfume.id)">
                    {{ savingPerfumeId === perfume.id ? 'Speichert...' : 'Speichern' }}
                  </button>
                </div>
              </div>
            </article>
          </div>
        </section>

        <section class="rounded-3xl border border-stone-800 bg-stone-900/75 p-6 shadow-2xl">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-2xl font-semibold">Set Liste</h2>
              <p class="mt-1 text-sm text-stone-400">{{ sampleSets.length }} Sets</p>
            </div>
            <button class="rounded-xl bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950" @click="showCreateSet = !showCreateSet">
              + Set erstellen
            </button>
          </div>

          <form v-if="showCreateSet" class="mt-5 space-y-4 rounded-2xl border border-stone-800 bg-stone-950/60 p-5" @submit.prevent="createSampleSet">
            <div class="grid gap-4 md:grid-cols-2">
              <input v-model="setForm.title" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="text" placeholder="Set-Titel" required />
              <select v-model="setForm.status" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3">
                <option value="active">Aktiv</option>
                <option value="inactive">Inaktiv</option>
              </select>
            </div>
            <textarea v-model="setForm.description" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" rows="3" placeholder="Beschreibung des Sets"></textarea>
            <select v-model="setForm.assign_user_id" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3">
              <option value="">Keinem Nutzer direkt zuweisen</option>
              <option v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.email }}</option>
            </select>
            <input class="block w-full text-sm" type="file" accept="image/png,image/jpeg,image/webp" @change="onSetImageChange" />
            <img v-if="setImagePreview" :src="setImagePreview" alt="Setvorschau" class="h-48 w-full rounded-2xl object-cover" />
            <div class="rounded-2xl border border-stone-800 bg-stone-950/60 p-4">
              <p class="text-sm font-semibold">Wähle genau 5 Parfums aus</p>
              <div class="mt-3 grid max-h-72 gap-3 overflow-auto pr-1 md:grid-cols-2">
                <button
                  v-for="perfume in perfumes"
                  :key="perfume.id"
                  type="button"
                  class="rounded-xl border px-4 py-3 text-left transition"
                  :class="setForm.perfume_ids.includes(perfume.id)
                    ? 'border-amber-400 bg-amber-400/10 text-amber-200'
                    : 'border-stone-700 bg-stone-900/60 text-stone-200 hover:bg-stone-800'"
                  @click="togglePerfumeSelection(perfume.id)"
                >
                  <div class="font-medium">{{ perfume.name }}</div>
                  <div class="text-sm text-stone-400">{{ perfume.brand_name || 'Ohne Marke' }}</div>
                </button>
              </div>
              <p class="mt-3 text-sm text-stone-400">Ausgewählt: {{ setForm.perfume_ids.length }} / 5</p>
            </div>
            <button :disabled="savingSet || setForm.perfume_ids.length !== 5" class="rounded-xl bg-amber-400 px-4 py-3 font-semibold text-stone-950 disabled:opacity-60">
              {{ savingSet ? 'Speichert...' : 'Set speichern' }}
            </button>
          </form>

          <div class="mt-5 space-y-3">
            <article v-for="set in sampleSets" :key="set.id" class="rounded-2xl border border-stone-800 bg-stone-950/70 p-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p class="text-lg font-semibold text-stone-100">{{ set.title }}</p>
                  <p class="mt-1 text-sm text-stone-400">{{ set.perfume_count }} Parfums · {{ set.assigned_count }} Zuweisungen</p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="set.status === 'active' ? 'bg-emerald-900/50 text-emerald-200' : 'bg-stone-800 text-stone-300'">
                    {{ set.status === 'active' ? 'Aktiv' : 'Inaktiv' }}
                  </span>
                  <button class="rounded-lg border border-stone-700 px-3 py-2 text-sm font-semibold text-stone-200 hover:bg-stone-800" @click="toggleSetEditor(set.id)">
                    {{ openSetEditorId === set.id ? 'Schließen' : 'Bearbeiten' }}
                  </button>
                  <button class="rounded-lg border border-emerald-700 bg-emerald-900/40 px-3 py-2 text-sm font-semibold text-emerald-200" @click="toggleAssignUser(set.id)">
                    {{ openAssignUserId === set.id ? 'Zuweisung schließen' : '+ User hinzufügen' }}
                  </button>
                  <button class="rounded-lg border border-red-700 bg-red-950/50 px-3 py-2 text-sm font-semibold text-red-200" :disabled="deletingSetId === set.id" @click="deleteSampleSet(set.id)">
                    {{ deletingSetId === set.id ? 'Löscht...' : 'Löschen' }}
                  </button>
                </div>
              </div>

              <div v-if="openAssignUserId === set.id" class="mt-4 rounded-2xl border border-stone-800 bg-stone-900/60 p-4">
                <p class="text-sm font-semibold text-stone-200">User zu diesem Set hinzufügen</p>
                <div class="mt-3 grid gap-3 md:grid-cols-[1fr_auto]">
                  <select v-model="getSetDraft(set.id).assign_user_id" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2">
                    <option value="">Nutzer auswählen</option>
                    <option v-for="user in users" :key="`${set.id}-user-${user.id}`" :value="String(user.id)">{{ user.email }}</option>
                  </select>
                  <button class="rounded-lg border border-emerald-700 bg-emerald-900/40 px-4 py-2 text-sm font-semibold text-emerald-200 disabled:opacity-60" :disabled="assigningSetId === set.id || !getSetDraft(set.id).assign_user_id" @click="assignSampleSetToUser(set.id)">
                    {{ assigningSetId === set.id ? 'Weist zu...' : 'User hinzufügen' }}
                  </button>
                </div>
              </div>

              <div v-if="openSetEditorId === set.id" class="mt-4 space-y-4 rounded-2xl border border-stone-800 bg-stone-900/60 p-4">
                <div class="grid gap-3 md:grid-cols-2">
                  <input v-model="getSetDraft(set.id).title" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" type="text" placeholder="Titel" />
                  <select v-model="getSetDraft(set.id).status" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2">
                    <option value="active">Aktiv</option>
                    <option value="inactive">Inaktiv</option>
                  </select>
                </div>
                <textarea v-model="getSetDraft(set.id).description" class="w-full rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" rows="2" placeholder="Beschreibung" />
                <input class="block w-full text-sm" type="file" accept="image/png,image/jpeg,image/webp" @change="onSetEditImageChange(set.id, $event)" />
                <div class="rounded-xl border border-stone-800 bg-stone-950/60 p-3">
                  <p class="text-sm font-semibold">Parfums im Set (genau 5)</p>
                  <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    <button
                      v-for="perfume in perfumes"
                      :key="`${set.id}-${perfume.id}`"
                      type="button"
                      class="rounded-lg border px-3 py-2 text-left text-sm transition"
                      :class="getSetDraft(set.id).perfume_ids.includes(perfume.id)
                        ? 'border-amber-400 bg-amber-400/10 text-amber-200'
                        : 'border-stone-700 bg-stone-900 text-stone-200 hover:bg-stone-800'"
                      @click="toggleSetDraftPerfume(set.id, perfume.id)"
                    >
                      {{ perfume.name }}
                    </button>
                  </div>
                  <p class="mt-2 text-xs text-stone-400">Ausgewählt: {{ getSetDraft(set.id).perfume_ids.length }} / 5</p>
                </div>
                <button class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 disabled:opacity-60" :disabled="savingSetId === set.id || getSetDraft(set.id).perfume_ids.length !== 5" @click="updateSampleSet(set.id)">
                  {{ savingSetId === set.id ? 'Speichert...' : 'Set speichern' }}
                </button>
              </div>
            </article>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>

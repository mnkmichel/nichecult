<script setup lang="ts">
definePageMeta({
  middleware: 'admin',
})

const { listAdminUsers, listAdminPerfumes } = useAuthApi()
const config = useRuntimeConfig()
const apiBase = (config.public.apiBase as string).replace(/\/$/, '')

const users = ref<Array<Record<string, any>>>([])
const perfumes = ref<Array<Record<string, any>>>([])
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

const loadAdminData = async () => {
  loading.value = true
  error.value = ''

  try {
    const token = localStorage.getItem('nichecult_token')
    if (!token) {
      await navigateTo('/admin/login')
      return
    }

    const [usersRes, perfumesRes] = await Promise.all([
      listAdminUsers(token),
      listAdminPerfumes(token),
    ])

    users.value = usersRes.users || []
    perfumes.value = perfumesRes.perfumes || []
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
  } catch (e: any) {
    error.value = e?.data?.details || e?.data?.error || e?.message || 'Sample-Set konnte nicht angelegt werden'
  } finally {
    savingSet.value = false
  }
}

const logout = async () => {
  localStorage.removeItem('nichecult_token')
  await navigateTo('/admin/login')
}

onMounted(loadAdminData)
</script>

<template>
  <main class="min-h-screen bg-[radial-gradient(circle_at_top,_#2e261d,_#111111_60%)] px-4 py-8 text-stone-100">
    <div class="mx-auto max-w-7xl space-y-8">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-[0.3em] text-amber-400">Nichecult Admin</p>
          <h1 class="mt-2 text-3xl font-bold">Parfums und Sample-Sets verwalten</h1>
        </div>

        <button class="rounded-xl border border-stone-700 bg-stone-900/70 px-4 py-2 text-sm font-semibold hover:bg-stone-800" @click="logout">
          Logout
        </button>
      </header>

      <div v-if="loading" class="rounded-2xl border border-stone-800 bg-stone-900/70 p-5 text-stone-300">Lade Admin-Daten...</div>
      <div v-else-if="error && !savingPerfume && !savingSet" class="rounded-2xl border border-red-900 bg-red-950/60 p-5 text-red-300">{{ error }}</div>

      <section class="grid gap-6 xl:grid-cols-[1fr_1fr_0.9fr]">
        <div class="rounded-3xl border border-stone-800 bg-stone-900/75 p-6 shadow-2xl">
          <h2 class="text-xl font-semibold">1. Parfum anlegen</h2>

          <form class="mt-5 space-y-4" @submit.prevent="createPerfume">
            <input v-model="perfumeForm.name" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="text" placeholder="Parfumname" required />
            <input v-model="perfumeForm.brand_name" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="text" placeholder="Marke" />
            <textarea v-model="perfumeForm.description" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" rows="4" placeholder="Beschreibung"></textarea>
            <div class="grid gap-4 md:grid-cols-3">
              <input v-model="perfumeForm.price_cents" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="number" min="0" placeholder="Preis in Cent" />
              <input v-model="perfumeForm.discount_percent" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="number" min="0" max="100" placeholder="Rabatt %" />
              <select v-model="perfumeForm.is_active" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-3">
                <option value="1">Aktiv</option>
                <option value="0">Inaktiv</option>
              </select>
            </div>
            <input class="block w-full text-sm" type="file" accept="image/png,image/jpeg,image/webp" @change="onPerfumeImageChange" />
            <img v-if="perfumeImagePreview" :src="perfumeImagePreview" alt="Parfumvorschau" class="h-56 w-full rounded-2xl object-cover" />
            <button :disabled="savingPerfume" class="w-full rounded-xl bg-amber-400 px-4 py-3 font-semibold text-stone-950 disabled:opacity-60">
              {{ savingPerfume ? 'Speichert...' : 'Parfum speichern' }}
            </button>
          </form>
        </div>

        <div class="rounded-3xl border border-stone-800 bg-stone-900/75 p-6 shadow-2xl">
          <h2 class="text-xl font-semibold">2. Sample-Set mit 5 Parfums erstellen</h2>

          <form class="mt-5 space-y-4" @submit.prevent="createSampleSet">
            <input v-model="setForm.title" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="text" placeholder="Set-Titel" required />
            <textarea v-model="setForm.description" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" rows="4" placeholder="Beschreibung des Sets"></textarea>
            <select v-model="setForm.status" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3">
              <option value="active">Aktiv</option>
              <option value="inactive">Inaktiv</option>
            </select>
            <select v-model="setForm.assign_user_id" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3">
              <option value="">Keinem Nutzer direkt zuweisen</option>
              <option v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.email }}</option>
            </select>
            <input class="block w-full text-sm" type="file" accept="image/png,image/jpeg,image/webp" @change="onSetImageChange" />
            <img v-if="setImagePreview" :src="setImagePreview" alt="Setvorschau" class="h-56 w-full rounded-2xl object-cover" />

            <div class="rounded-2xl border border-stone-800 bg-stone-950/60 p-4">
              <p class="text-sm font-semibold">Waehle genau 5 Parfums aus</p>
              <div class="mt-3 grid max-h-72 gap-3 overflow-auto pr-1">
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
              <p class="mt-3 text-sm text-stone-400">Ausgewaehlt: {{ setForm.perfume_ids.length }} / 5</p>
            </div>

            <button :disabled="savingSet || setForm.perfume_ids.length !== 5" class="w-full rounded-xl bg-amber-400 px-4 py-3 font-semibold text-stone-950 disabled:opacity-60">
              {{ savingSet ? 'Speichert...' : 'Sample-Set speichern' }}
            </button>
          </form>
        </div>

        <div class="space-y-6">
          <div class="rounded-3xl border border-stone-800 bg-stone-900/75 p-6">
            <h2 class="text-xl font-semibold">Zuletzt erstellt</h2>
            <div v-if="createdPerfume" class="mt-4 space-y-3 rounded-2xl border border-stone-800 bg-stone-950/60 p-4">
              <p class="text-xs uppercase tracking-[0.2em] text-amber-400">Parfum</p>
              <img v-if="createdPerfume.image_url" :src="createdPerfume.image_url" alt="Parfumbild" class="h-44 w-full rounded-2xl object-cover" />
              <p class="font-semibold">{{ createdPerfume.name }}</p>
            </div>
            <div v-if="createdSet" class="mt-4 space-y-3 rounded-2xl border border-stone-800 bg-stone-950/60 p-4">
              <p class="text-xs uppercase tracking-[0.2em] text-amber-400">Sample-Set</p>
              <img v-if="createdSet.image_url" :src="createdSet.image_url" alt="Setbild" class="h-44 w-full rounded-2xl object-cover" />
              <p class="font-semibold">{{ createdSet.title }}</p>
            </div>
            <p v-if="!createdPerfume && !createdSet" class="mt-4 text-sm text-stone-400">Noch nichts in dieser Session erstellt.</p>
            <p v-if="success" class="mt-4 text-sm text-emerald-400">{{ success }}</p>
          </div>

          <div class="rounded-3xl border border-stone-800 bg-stone-900/75 p-6">
            <h2 class="text-xl font-semibold">Nutzer</h2>
            <div class="mt-4 space-y-3 max-h-[28rem] overflow-auto pr-1">
              <div v-for="user in users" :key="user.id" class="rounded-2xl border border-stone-800 bg-stone-950/70 p-4">
                <p class="font-medium">{{ user.email }}</p>
                <p class="mt-1 text-sm text-stone-400">{{ user.first_name || '' }} {{ user.last_name || '' }}</p>
                <p class="mt-1 text-xs uppercase tracking-[0.15em] text-stone-500">{{ user.is_admin ? 'Admin' : 'User' }} · {{ user.sample_count }} Eintraege</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>
</template>

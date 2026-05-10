<script setup lang="ts">
definePageMeta({
  middleware: 'admin',
})

const { listAdminUsers, listAdminPerfumes, listAdminSampleSets, listAdminRatingAnalytics } = useAuthApi()
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
const perfumeImageFile = ref<File | null>(null)
const savingPerfume = ref(false)
const savingSet = ref(false)
const nowTs = ref(Date.now())
let nowInterval: ReturnType<typeof setInterval> | null = null

type AdminRatingAnalyticsRow = {
  rating_id: number
  user_id: number
  user_email: string
  user_name: string
  user_sample_set_id: number
  sample_set_id: number
  sample_set_title: string
  perfume_id: number
  perfume_name: string
  brand_name: string | null
  sort_order: number | null
  overall_score: number | null
  longevity_score: number | null
  sillage_score: number | null
  created_at: string | null
  updated_at: string | null
  set_status: string | null
  answers: Record<string, string>
}

const analyticsRows = ref<AdminRatingAnalyticsRow[]>([])
const analyticsLoading = ref(false)
const analyticsSetId = ref('all')
const analyticsUserId = ref('all')
const analyticsPerfumeId = ref('all')

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
  rating_deadline_at: '',
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
  rating_deadline_at: string
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
      rating_deadline_at: '',
      perfume_ids: [],
    }
  }
  return setDrafts[setId]!
}

const perfumeEditFile = reactive<Record<number, File | null>>({})
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
const perfumeSearch = ref('')
const setSearch = ref('')
const userSearch = ref('')
const perfumeStatusFilter = ref<'all' | 'active' | 'inactive'>('all')
const setStatusFilter = ref<'all' | 'active' | 'inactive'>('all')

const parseApiDate = (value: unknown) => {
  const raw = String(value || '').trim()
  if (!raw) return null
  const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T')
  const date = new Date(normalized)
  return Number.isNaN(date.getTime()) ? null : date
}

const toDateTimeLocal = (value: unknown) => {
  const raw = String(value || '').trim()
  if (!raw) return ''
  return raw.replace(' ', 'T').slice(0, 16)
}

const getDatePart = (value: string) => {
  return String(value || '').split('T')[0] || ''
}

const getTimePart = (value: string) => {
  return String(value || '').split('T')[1] || ''
}

const setDatePart = (currentValue: string, nextDate: string) => {
  const timePart = getTimePart(currentValue) || '12:00'
  return nextDate ? `${nextDate}T${timePart}` : ''
}

const setTimePart = (currentValue: string, nextTime: string) => {
  const datePart = getDatePart(currentValue)
  if (!datePart) return ''
  return `${datePart}T${nextTime || '12:00'}`
}

const formatDeadline = (value: unknown) => {
  const date = parseApiDate(value)
  if (!date) return 'Keine Frist gesetzt'
  return date.toLocaleString('de-DE', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}

const deadlineCountdown = (value: unknown) => {
  const date = parseApiDate(value)
  if (!date) return 'Ohne Timer'

  const diffMs = date.getTime() - nowTs.value
  if (diffMs <= 0) return 'Frist abgelaufen'

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

const getDisplayedDeadline = (set: Record<string, any>) => {
  return set.next_rating_deadline_at || set.rating_deadline_at || ''
}

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
  const nextValue = openSetEditorId.value === setId ? null : setId
  openSetEditorId.value = nextValue

  if (nextValue !== null) {
    openAssignUserId.value = null
  }
}

const toggleAssignUser = (setId: number) => {
  const nextValue = openAssignUserId.value === setId ? null : setId
  openAssignUserId.value = nextValue

  if (nextValue !== null) {
    openSetEditorId.value = null
  }
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
      rating_deadline_at: toDateTimeLocal(getDisplayedDeadline(set)),
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

    analyticsLoading.value = true

    const [usersRes, perfumesRes, setsRes, analyticsRes] = await Promise.all([
      listAdminUsers(token),
      listAdminPerfumes(token),
      listAdminSampleSets(token),
      listAdminRatingAnalytics(token),
    ])

    users.value = usersRes.users || []
    perfumes.value = perfumesRes.perfumes || []
    sampleSets.value = setsRes.sampleSets || []
    analyticsRows.value = analyticsRes.rows || []
    hydrateDrafts()
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Admin-Daten konnten nicht geladen werden'
  } finally {
    analyticsLoading.value = false
    loading.value = false
  }
}

const onPerfumeImageChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] || null
  perfumeImageFile.value = file
  perfumeImagePreview.value = file ? URL.createObjectURL(file) : null
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
    body.append('rating_deadline_at', setForm.rating_deadline_at || '')

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
    setForm.rating_deadline_at = ''
    setForm.perfume_ids = []
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

const toggleSetDraftPerfume = (setId: number, perfumeId: number) => {
  const draft = getSetDraft(setId)
  if (!draft) return

  if (draft.perfume_ids.includes(perfumeId)) {
    draft.perfume_ids = draft.perfume_ids.filter(id => id !== perfumeId)
  } else if (draft.perfume_ids.length < 5) {
    draft.perfume_ids = [...draft.perfume_ids, perfumeId]
  }
}

const getSetById = (setId: number) => sampleSets.value.find(set => Number(set.id) === Number(setId))

const resetSetDraft = (setId: number) => {
  const source = getSetById(setId)
  if (!source) return

  const draft = getSetDraft(setId)
  draft.title = String(source.title || '')
  draft.description = String(source.description || '')
  draft.status = String(source.status || 'active')
  draft.assign_user_id = ''
  draft.rating_deadline_at = toDateTimeLocal(getDisplayedDeadline(source))
  draft.perfume_ids = Array.isArray(source.perfume_ids) ? source.perfume_ids.map((id: any) => Number(id)) : []
}

const setDraftSelectionHint = (setId: number) => {
  const count = getSetDraft(setId).perfume_ids.length
  if (count === 5) return 'Perfekt: 5 von 5 ausgewählt.'
  if (count < 5) return `Noch ${5 - count} Parfum(s) auswählen.`
  return `${count - 5} zu viel ausgewählt.`
}

const isSetDraftPerfumeSelected = (setId: number, perfumeId: number) => {
  return getSetDraft(setId).perfume_ids.includes(perfumeId)
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
    body.append('rating_deadline_at', draft.rating_deadline_at || '')

    const res = await $fetch<{
      ok: boolean
      error?: string
      sampleSet?: {
        rating_deadline_at?: string | null
        next_rating_deadline_at?: string | null
      }
      debug?: {
        submitted_rating_deadline_at?: string | null
        normalized_rating_deadline_at?: string | null
      }
    }>(`${apiBase}/admin-update-sample-set.php`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      body,
    })

    if (!res.ok) {
      error.value = res.error || 'Sample-Set konnte nicht aktualisiert werden'
      return
    }

    const storedDeadline = String(res.sampleSet?.next_rating_deadline_at || res.sampleSet?.rating_deadline_at || '')
    if (draft.rating_deadline_at && !storedDeadline) {
      error.value = `Frist wurde vom Server nicht gespeichert. Gesendet: ${res.debug?.submitted_rating_deadline_at || draft.rating_deadline_at}`
      return
    }

    success.value = 'Sample-Set aktualisiert.'
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
    body.append('rating_deadline_at', draft.rating_deadline_at || '')

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

const activeTab = ref<'perfumes' | 'sets' | 'users' | 'ratings'>('perfumes')

const matchesQuery = (value: unknown, query: string) => String(value || '').toLowerCase().includes(query.toLowerCase().trim())

const filteredPerfumes = computed(() => {
  return perfumes.value.filter((perfume) => {
    const isActive = Number(perfume.is_active || 0) === 1
    const statusMatch = perfumeStatusFilter.value === 'all'
      || (perfumeStatusFilter.value === 'active' && isActive)
      || (perfumeStatusFilter.value === 'inactive' && !isActive)

    const searchMatch = !perfumeSearch.value
      || matchesQuery(perfume.name, perfumeSearch.value)
      || matchesQuery(perfume.brand_name, perfumeSearch.value)

    return statusMatch && searchMatch
  })
})

const filteredSampleSets = computed(() => {
  return sampleSets.value.filter((set) => {
    const isActive = String(set.status || '').toLowerCase() === 'active'
    const statusMatch = setStatusFilter.value === 'all'
      || (setStatusFilter.value === 'active' && isActive)
      || (setStatusFilter.value === 'inactive' && !isActive)

    const searchMatch = !setSearch.value
      || matchesQuery(set.title, setSearch.value)
      || matchesQuery(set.description, setSearch.value)

    return statusMatch && searchMatch
  })
})

const filteredUsers = computed(() => {
  if (!userSearch.value.trim()) return users.value
  return users.value.filter((user) => {
    return matchesQuery(user.email, userSearch.value)
      || matchesQuery(user.first_name, userSearch.value)
      || matchesQuery(user.last_name, userSearch.value)
      || matchesQuery(user.id, userSearch.value)
  })
})

const parseOverallMatch = (value: unknown): number | null => {
  const n = Number(value)
  if (!Number.isFinite(n)) return null
  if (n >= 0 && n <= 4) return n + 1
  if (n >= 1 && n <= 5) return n
  return null
}

const parseDuftfamilien = (value: unknown): string[] => {
  if (Array.isArray(value)) return value.map((v) => String(v))
  if (typeof value !== 'string') return []
  try {
    const parsed = JSON.parse(value)
    if (Array.isArray(parsed)) {
      return parsed.map((v) => String(v))
    }
  } catch {
    // ignore invalid json
  }
  return []
}

const answerLabelByKey: Record<string, string> = {
  gender: 'Geschlecht',
  season: 'Jahreszeit',
  occasion: 'Anlass',
  warmFrisch: 'Warm-Frisch',
  naturalSynthetisch: 'Natürlich-Synthetisch',
  sweetness: 'Süße',
  sexyClean: 'Sexy-Clean',
  intensivDezent: 'Intensiv-Dezent',
  duftfamilien: 'Duftfamilien',
  overallMatch: 'Passgenauigkeit',
}

const expectedRatingAnswerKeys = [
  'gender',
  'season',
  'occasion',
  'warmFrisch',
  'naturalSynthetisch',
  'sweetness',
  'sexyClean',
  'intensivDezent',
  'duftfamilien',
  'overallMatch',
]

const formatAnswerValue = (key: string, rawValue: unknown): string => {
  if (rawValue === null || rawValue === undefined) return '-'

  if (key === 'duftfamilien') {
    const list = parseDuftfamilien(rawValue)
    return list.length ? list.join(', ') : '-'
  }

  if (key === 'overallMatch') {
    const parsed = parseOverallMatch(rawValue)
    return parsed ? `${parsed}/5` : '-'
  }

  const text = String(rawValue)
  if ((text.startsWith('[') && text.endsWith(']')) || (text.startsWith('{') && text.endsWith('}'))) {
    try {
      const parsed = JSON.parse(text)
      if (Array.isArray(parsed)) {
        return parsed.map((v) => String(v)).join(', ')
      }
      if (typeof parsed === 'object' && parsed !== null) {
        return Object.entries(parsed as Record<string, unknown>)
          .map(([k, v]) => `${k}: ${String(v)}`)
          .join(' | ')
      }
    } catch {
      // ignore parse errors and return raw text
    }
  }

  return text
}

const analyticsSetOptions = computed(() => {
  const map = new Map<number, string>()
  for (const row of analyticsRows.value) {
    map.set(row.sample_set_id, row.sample_set_title || `Set ${row.sample_set_id}`)
  }
  return Array.from(map.entries())
    .map(([id, title]) => ({ id, title }))
    .sort((a, b) => b.id - a.id)
})

const analyticsUserOptions = computed(() => {
  const map = new Map<number, string>()
  for (const row of analyticsRows.value) {
    const label = row.user_name ? `${row.user_name} (${row.user_email})` : row.user_email
    map.set(row.user_id, label)
  }
  return Array.from(map.entries())
    .map(([id, label]) => ({ id, label }))
    .sort((a, b) => a.label.localeCompare(b.label, 'de'))
})

const analyticsPerfumeOptions = computed(() => {
  const map = new Map<number, string>()
  for (const row of analyticsRows.value) {
    const label = row.brand_name ? `${row.brand_name} ${row.perfume_name}` : row.perfume_name
    map.set(row.perfume_id, label)
  }
  return Array.from(map.entries())
    .map(([id, label]) => ({ id, label }))
    .sort((a, b) => a.label.localeCompare(b.label, 'de'))
})

const filteredAnalyticsRows = computed(() => {
  return analyticsRows.value.filter((row) => {
    const setMatch = analyticsSetId.value === 'all' || Number(analyticsSetId.value) === row.sample_set_id
    const userMatch = analyticsUserId.value === 'all' || Number(analyticsUserId.value) === row.user_id
    const perfumeMatch = analyticsPerfumeId.value === 'all' || Number(analyticsPerfumeId.value) === row.perfume_id
    return setMatch && userMatch && perfumeMatch
  })
})

const userSetSummary = computed(() => {
  const map = new Map<string, {
    userId: number
    userLabel: string
    setId: number
    setTitle: string
    entries: Array<{ perfumeLabel: string; passgenauigkeit: number | null; overallScore: number | null }>
  }>()

  for (const row of filteredAnalyticsRows.value) {
    const key = `${row.user_id}-${row.user_sample_set_id}`
    if (!map.has(key)) {
      map.set(key, {
        userId: row.user_id,
        userLabel: row.user_name ? `${row.user_name} (${row.user_email})` : row.user_email,
        setId: row.sample_set_id,
        setTitle: row.sample_set_title,
        entries: [],
      })
    }

    const item = map.get(key)!
    const perfumeLabel = row.brand_name ? `${row.brand_name} ${row.perfume_name}` : row.perfume_name
    item.entries.push({
      perfumeLabel,
      passgenauigkeit: parseOverallMatch(row.answers?.overallMatch),
      overallScore: row.overall_score,
    })
  }

  return Array.from(map.values()).map((item) => {
    item.entries.sort((a, b) => a.perfumeLabel.localeCompare(b.perfumeLabel, 'de'))
    return item
  })
})

const selectedPerfumeRows = computed(() => {
  if (analyticsPerfumeId.value === 'all') return []
  const perfumeId = Number(analyticsPerfumeId.value)
  return filteredAnalyticsRows.value.filter((row) => row.perfume_id === perfumeId)
})

const ratingsByPerfume = computed(() => {
  const map = new Map<number, {
    perfumeId: number
    perfumeLabel: string
    rows: Array<{
      ratingId: number
      userLabel: string
      setTitle: string
      overallScore: number | null
      passgenauigkeit: number | null
      duftfamilien: string[]
      allAnswers: Array<{ key: string; label: string; value: string }>
      ratedAt: string
    }>
  }>()

  for (const row of filteredAnalyticsRows.value) {
    if (!map.has(row.perfume_id)) {
      map.set(row.perfume_id, {
        perfumeId: row.perfume_id,
        perfumeLabel: row.brand_name ? `${row.brand_name} ${row.perfume_name}` : row.perfume_name,
        rows: [],
      })
    }

    const entry = map.get(row.perfume_id)!
    const rawAnswers = row.answers || {}

    const expectedEntries = expectedRatingAnswerKeys.map((key) => ({
      key,
      label: answerLabelByKey[key] || key,
      value: formatAnswerValue(key, rawAnswers[key] ?? null),
    }))

    const extraEntries = Object.entries(rawAnswers)
      .filter(([key]) => !expectedRatingAnswerKeys.includes(key))
      .map(([key, value]) => ({
        key,
        label: answerLabelByKey[key] || key,
        value: formatAnswerValue(key, value),
      }))
      .sort((a, b) => a.label.localeCompare(b.label, 'de'))

    const answerEntries = [...expectedEntries, ...extraEntries]

    entry.rows.push({
      ratingId: row.rating_id,
      userLabel: row.user_name ? `${row.user_name} (${row.user_email})` : row.user_email,
      setTitle: row.sample_set_title,
      overallScore: row.overall_score,
      passgenauigkeit: parseOverallMatch(row.answers?.overallMatch),
      duftfamilien: parseDuftfamilien(row.answers?.duftfamilien),
      allAnswers: answerEntries,
      ratedAt: row.updated_at || row.created_at || '-',
    })
  }

  return Array.from(map.values())
    .map((group) => {
      group.rows.sort((a, b) => a.userLabel.localeCompare(b.userLabel, 'de'))
      return group
    })
    .sort((a, b) => a.perfumeLabel.localeCompare(b.perfumeLabel, 'de'))
})

const passgenauigkeitChart = computed(() => {
  const counts = [0, 0, 0, 0, 0]
  for (const row of selectedPerfumeRows.value) {
    const value = parseOverallMatch(row.answers?.overallMatch)
    if (value && value >= 1 && value <= 5) {
      const idx = value - 1
      counts[idx] = (counts[idx] ?? 0) + 1
    }
  }
  const maxCount = Math.max(1, ...counts)
  return counts.map((count, idx) => ({
    label: `${idx + 1}/5`,
    count,
    width: `${Math.round((count / maxCount) * 100)}%`,
  }))
})

const duftfamilienChart = computed(() => {
  const counts = new Map<string, number>()
  for (const row of selectedPerfumeRows.value) {
    for (const family of parseDuftfamilien(row.answers?.duftfamilien)) {
      counts.set(family, (counts.get(family) || 0) + 1)
    }
  }
  const items = Array.from(counts.entries())
    .map(([label, count]) => ({ label, count }))
    .sort((a, b) => b.count - a.count)
  const maxCount = Math.max(1, ...items.map((i) => i.count), 1)
  return items.map((item) => ({
    ...item,
    width: `${Math.round((item.count / maxCount) * 100)}%`,
  }))
})

const totalDeliveredAssignments = computed(() => {
  return sampleSets.value.reduce((sum, set) => sum + Number(set.assigned_count || 0), 0)
})

watch(success, (val) => {
  if (val) setTimeout(() => { success.value = '' }, 4000)
})

const logout = async () => {
  localStorage.removeItem('nichecult_token')
  await navigateTo('/admin/login')
}

onMounted(() => {
  loadAdminData()
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
  <main class="min-h-screen bg-[radial-gradient(circle_at_top,#2e261d,#111111_60%)] px-4 py-8 text-stone-100">
    <div class="mx-auto max-w-7xl space-y-8">
      <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-[0.3em] text-amber-400">Nice Cult Admin</p>
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

        <section class="rounded-3xl border border-stone-800 bg-stone-900/75 p-4 shadow-2xl md:p-6">
          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-stone-700 bg-stone-950/60 p-4">
              <p class="text-xs uppercase tracking-[0.2em] text-stone-400">Parfüms</p>
              <p class="mt-2 text-2xl font-bold text-stone-100">{{ perfumes.length }}</p>
            </article>
            <article class="rounded-2xl border border-stone-700 bg-stone-950/60 p-4">
              <p class="text-xs uppercase tracking-[0.2em] text-stone-400">Sample-Sets</p>
              <p class="mt-2 text-2xl font-bold text-stone-100">{{ sampleSets.length }}</p>
            </article>
            <article class="rounded-2xl border border-stone-700 bg-stone-950/60 p-4">
              <p class="text-xs uppercase tracking-[0.2em] text-stone-400">Nutzer</p>
              <p class="mt-2 text-2xl font-bold text-stone-100">{{ users.length }}</p>
            </article>
            <article class="rounded-2xl border border-stone-700 bg-stone-950/60 p-4">
              <p class="text-xs uppercase tracking-[0.2em] text-stone-400">Zuweisungen</p>
              <p class="mt-2 text-2xl font-bold text-stone-100">{{ totalDeliveredAssignments }}</p>
            </article>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <button
              class="rounded-xl border px-4 py-2 text-sm font-semibold transition"
              :class="activeTab === 'perfumes' ? 'border-amber-400 bg-amber-400 text-stone-950' : 'border-stone-700 bg-stone-900 text-stone-200 hover:bg-stone-800'"
              @click="activeTab = 'perfumes'"
            >
              Parfüms verwalten
            </button>
            <button
              class="rounded-xl border px-4 py-2 text-sm font-semibold transition"
              :class="activeTab === 'sets' ? 'border-amber-400 bg-amber-400 text-stone-950' : 'border-stone-700 bg-stone-900 text-stone-200 hover:bg-stone-800'"
              @click="activeTab = 'sets'"
            >
              Sets verwalten
            </button>
            <button
              class="rounded-xl border px-4 py-2 text-sm font-semibold transition"
              :class="activeTab === 'users' ? 'border-amber-400 bg-amber-400 text-stone-950' : 'border-stone-700 bg-stone-900 text-stone-200 hover:bg-stone-800'"
              @click="activeTab = 'users'"
            >
              Nutzerübersicht
            </button>
            <button
              class="rounded-xl border px-4 py-2 text-sm font-semibold transition"
              :class="activeTab === 'ratings' ? 'border-amber-400 bg-amber-400 text-stone-950' : 'border-stone-700 bg-stone-900 text-stone-200 hover:bg-stone-800'"
              @click="activeTab = 'ratings'"
            >
              Bewertungsdaten
            </button>
          </div>
        </section>

        <section v-if="activeTab === 'perfumes'" class="rounded-3xl border border-stone-800 bg-stone-900/75 p-6 shadow-2xl">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-2xl font-semibold">Parfüm Liste</h2>
              <p class="mt-1 text-sm text-stone-400">{{ filteredPerfumes.length }} von {{ perfumes.length }} Einträgen</p>
            </div>
            <button class="rounded-xl bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950" @click="showCreatePerfume = !showCreatePerfume">
              + Parfüm erstellen
            </button>
          </div>

          <div class="mt-4 grid gap-3 md:grid-cols-[1fr_220px]">
            <input v-model="perfumeSearch" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5" type="text" placeholder="Suche nach Name oder Marke" />
            <select v-model="perfumeStatusFilter" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5">
              <option value="all">Alle Status</option>
              <option value="active">Nur aktiv</option>
              <option value="inactive">Nur inaktiv</option>
            </select>
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

          <div v-if="!filteredPerfumes.length" class="mt-5 rounded-2xl border border-stone-800 bg-stone-950/60 p-5 text-sm text-stone-300">
            Keine Parfüms für die aktuelle Suche gefunden.
          </div>

          <div v-else class="mt-5 space-y-3">
            <article v-for="perfume in filteredPerfumes" :key="perfume.id" class="rounded-2xl border border-stone-800 bg-stone-950/70 p-4">
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

        <section v-if="activeTab === 'sets'" class="rounded-3xl border border-stone-800 bg-stone-900/75 p-6 shadow-2xl">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-2xl font-semibold">Set Liste</h2>
              <p class="mt-1 text-sm text-stone-400">{{ filteredSampleSets.length }} von {{ sampleSets.length }} Sets</p>
            </div>
            <button class="rounded-xl bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950" @click="showCreateSet = !showCreateSet">
              + Set erstellen
            </button>
          </div>

          <div class="mt-4 grid gap-3 md:grid-cols-[1fr_220px]">
            <input v-model="setSearch" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5" type="text" placeholder="Suche nach Set-Titel" />
            <select v-model="setStatusFilter" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5">
              <option value="all">Alle Status</option>
              <option value="active">Nur aktiv</option>
              <option value="inactive">Nur inaktiv</option>
            </select>
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
            <div class="grid gap-3 md:grid-cols-2">
              <select v-model="setForm.assign_user_id" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3">
                <option value="">Keinem Nutzer direkt zuweisen</option>
                <option v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.email }}</option>
              </select>
              <div class="grid grid-cols-2 gap-3">
                <input :value="getDatePart(setForm.rating_deadline_at)" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="date" @input="setForm.rating_deadline_at = setDatePart(setForm.rating_deadline_at, ($event.target as HTMLInputElement)?.value || '')" />
                <input :value="getTimePart(setForm.rating_deadline_at)" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3" type="time" step="60" @input="setForm.rating_deadline_at = setTimePart(setForm.rating_deadline_at, ($event.target as HTMLInputElement)?.value || '')" />
              </div>
            </div>
            <p class="-mt-2 text-xs text-stone-400">Optional: Frist bis wann die Bewertung abgegeben werden muss.</p>
            <div class="rounded-2xl border border-stone-800 bg-stone-950/60 p-4">
              <p class="text-sm font-semibold">Wähle genau 5 Parfums aus</p>
              <div class="mt-3 space-y-2 max-h-72 overflow-auto pr-1">
                <label
                  v-for="perfume in perfumes"
                  :key="perfume.id"
                  class="flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 transition"
                  :class="setForm.perfume_ids.includes(perfume.id)
                    ? 'border-amber-400 bg-amber-400/10 text-amber-200'
                    : 'border-stone-700 bg-stone-900/60 text-stone-200 hover:bg-stone-800'"
                >
                  <input
                    class="mt-1 h-4 w-4 accent-amber-400"
                    type="checkbox"
                    :checked="setForm.perfume_ids.includes(perfume.id)"
                    @change="togglePerfumeSelection(perfume.id)"
                  />
                  <div>
                    <div class="font-medium">{{ perfume.name }}</div>
                    <div class="text-sm text-stone-400">{{ perfume.brand_name || 'Ohne Marke' }}</div>
                  </div>
                </label>
              </div>
              <p class="mt-3 text-sm" :class="setForm.perfume_ids.length === 5 ? 'text-emerald-300' : 'text-stone-400'">
                Ausgewählt: {{ setForm.perfume_ids.length }} / 5
              </p>
            </div>
            <button :disabled="savingSet || setForm.perfume_ids.length !== 5" class="rounded-xl bg-amber-400 px-4 py-3 font-semibold text-stone-950 disabled:opacity-60">
              {{ savingSet ? 'Speichert...' : 'Set speichern' }}
            </button>
          </form>

          <div v-if="!filteredSampleSets.length" class="mt-5 rounded-2xl border border-stone-800 bg-stone-950/60 p-5 text-sm text-stone-300">
            Keine Sets für die aktuelle Suche gefunden.
          </div>

          <div v-else class="mt-5 space-y-3">
            <article v-for="set in filteredSampleSets" :key="set.id" class="rounded-2xl border border-stone-800 bg-stone-950/70 p-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p class="text-lg font-semibold text-stone-100">{{ set.title }}</p>
                  <p class="mt-1 text-sm text-stone-400">{{ set.perfume_count }} Parfums · {{ set.assigned_count }} Zuweisungen</p>
                  <p class="mt-1 text-sm text-stone-400">Set-Frist: {{ formatDeadline(getDisplayedDeadline(set)) }}</p>
                  <p class="mt-1 text-xs font-semibold" :class="deadlineCountdown(getDisplayedDeadline(set)) === 'Frist abgelaufen' ? 'text-red-300' : 'text-amber-200'">
                    {{ deadlineCountdown(getDisplayedDeadline(set)) }}
                    <span v-if="Number(set.overdue_assignments || 0) > 0" class="ml-2 text-red-300">
                      · {{ Number(set.overdue_assignments || 0) }} überfällig
                    </span>
                  </p>
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
                <div class="mt-3 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                  <select v-model="getSetDraft(set.id).assign_user_id" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2">
                    <option value="">Nutzer auswählen</option>
                    <option v-for="user in users" :key="`${set.id}-user-${user.id}`" :value="String(user.id)">{{ user.email }}</option>
                  </select>
                  <div class="grid grid-cols-2 gap-3">
                    <input :value="getDatePart(getSetDraft(set.id).rating_deadline_at)" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" type="date" @input="getSetDraft(set.id).rating_deadline_at = setDatePart(getSetDraft(set.id).rating_deadline_at, ($event.target as HTMLInputElement)?.value || '')" />
                    <input :value="getTimePart(getSetDraft(set.id).rating_deadline_at)" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" type="time" step="60" @input="getSetDraft(set.id).rating_deadline_at = setTimePart(getSetDraft(set.id).rating_deadline_at, ($event.target as HTMLInputElement)?.value || '')" />
                  </div>
                  <button class="rounded-lg border border-emerald-700 bg-emerald-900/40 px-4 py-2 text-sm font-semibold text-emerald-200 disabled:opacity-60" :disabled="assigningSetId === set.id || !getSetDraft(set.id).assign_user_id" @click="assignSampleSetToUser(set.id)">
                    {{ assigningSetId === set.id ? 'Weist zu...' : 'User hinzufügen' }}
                  </button>
                </div>
                <p class="mt-2 text-xs text-stone-400">Wenn gesetzt, zählt ab jetzt der Timer bis zur Bewertungsfrist.</p>
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
                <div class="grid grid-cols-2 gap-3">
                  <input :value="getDatePart(getSetDraft(set.id).rating_deadline_at)" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" type="date" @input="getSetDraft(set.id).rating_deadline_at = setDatePart(getSetDraft(set.id).rating_deadline_at, ($event.target as HTMLInputElement)?.value || '')" />
                  <input :value="getTimePart(getSetDraft(set.id).rating_deadline_at)" class="rounded-lg border border-stone-700 bg-stone-900 px-3 py-2" type="time" step="60" @input="getSetDraft(set.id).rating_deadline_at = setTimePart(getSetDraft(set.id).rating_deadline_at, ($event.target as HTMLInputElement)?.value || '')" />
                </div>
                <div class="rounded-xl border border-stone-800 bg-stone-950/60 p-3">
                  <p class="text-sm font-semibold">Parfums auswählen</p>
                  <div class="mt-3 max-h-72 space-y-2 overflow-auto pr-1">
                    <label
                      v-for="perfume in perfumes"
                      :key="`${set.id}-${perfume.id}`"
                      class="flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-2 text-left text-sm transition"
                      :class="getSetDraft(set.id).perfume_ids.includes(perfume.id)
                        ? 'border-amber-400 bg-amber-400/10 text-amber-200'
                        : 'border-stone-700 bg-stone-900 text-stone-200 hover:bg-stone-800'"
                    >
                      <input
                        class="mt-1 h-4 w-4 accent-amber-400"
                        type="checkbox"
                        :checked="getSetDraft(set.id).perfume_ids.includes(perfume.id)"
                        @change="toggleSetDraftPerfume(set.id, perfume.id)"
                      />
                      <div>
                        <div class="font-medium">{{ perfume.name }}</div>
                        <div class="text-xs text-stone-400">{{ perfume.brand_name || 'Ohne Marke' }}</div>
                      </div>
                    </label>
                  </div>
                  <p class="mt-2 text-xs" :class="getSetDraft(set.id).perfume_ids.length === 5 ? 'text-emerald-300' : 'text-stone-400'">
                    Ausgewählt: {{ getSetDraft(set.id).perfume_ids.length }} / 5
                  </p>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                  <button class="rounded-lg border border-stone-700 px-4 py-2 text-sm text-stone-200 hover:bg-stone-800" @click="resetSetDraft(set.id)">
                    Zurücksetzen
                  </button>
                  <button class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 disabled:opacity-60" :disabled="savingSetId === set.id || getSetDraft(set.id).perfume_ids.length !== 5 || !String(getSetDraft(set.id).title || '').trim()" @click="updateSampleSet(set.id)">
                    {{ savingSetId === set.id ? 'Speichert...' : 'Set speichern' }}
                  </button>
                </div>
              </div>
            </article>
          </div>
        </section>

        <section v-if="activeTab === 'users'" class="rounded-3xl border border-stone-800 bg-stone-900/75 p-6 shadow-2xl">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-2xl font-semibold">Nutzerübersicht</h2>
              <p class="mt-1 text-sm text-stone-400">{{ filteredUsers.length }} von {{ users.length }} Nutzern</p>
            </div>
          </div>

          <div class="mt-4 grid gap-3 md:grid-cols-[1fr_240px]">
            <input v-model="userSearch" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5" type="text" placeholder="Suche nach E-Mail oder ID" />
            <div class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5 text-sm text-stone-300">
              Gesamt: {{ users.length }} Nutzerkonten
            </div>
          </div>

          <div v-if="!filteredUsers.length" class="mt-5 rounded-2xl border border-stone-800 bg-stone-950/60 p-5 text-sm text-stone-300">
            Kein Nutzer zur Suche gefunden.
          </div>

          <div v-else class="mt-5 overflow-hidden rounded-2xl border border-stone-800">
            <table class="min-w-full divide-y divide-stone-800 text-sm">
              <thead class="bg-stone-950/80 text-left text-stone-300">
                <tr>
                  <th class="px-4 py-3 font-semibold">ID</th>
                  <th class="px-4 py-3 font-semibold">E-Mail</th>
                  <th class="px-4 py-3 font-semibold">Name</th>
                  <th class="px-4 py-3 font-semibold">Rolle</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-stone-800 bg-stone-950/50 text-stone-200">
                <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-stone-900/80">
                  <td class="px-4 py-3">{{ user.id }}</td>
                  <td class="px-4 py-3">{{ user.email || '-' }}</td>
                  <td class="px-4 py-3">{{ [user.first_name, user.last_name].filter(Boolean).join(' ') || '-' }}</td>
                  <td class="px-4 py-3">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="user.is_admin ? 'bg-amber-400/20 text-amber-200' : 'bg-stone-800 text-stone-300'">
                      {{ user.is_admin ? 'Admin' : 'User' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section v-if="activeTab === 'ratings'" class="rounded-3xl border border-stone-800 bg-stone-900/75 p-6 shadow-2xl">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="text-2xl font-semibold">Auswertung 2. Fragebogen</h2>
              <p class="mt-1 text-sm text-stone-400">Alle Antworten aus Sample-Bewertungen inkl. Passgenauigkeit und Duftfamilien.</p>
            </div>
            <span class="rounded-full border border-stone-700 bg-stone-950 px-3 py-1 text-xs font-semibold text-stone-300">
              {{ filteredAnalyticsRows.length }} Datensätze
            </span>
          </div>

          <div class="mt-4 grid gap-3 md:grid-cols-3">
            <select v-model="analyticsSetId" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5">
              <option value="all">Alle Sample-Sets</option>
              <option v-for="set in analyticsSetOptions" :key="`aset-${set.id}`" :value="String(set.id)">{{ set.title }}</option>
            </select>
            <select v-model="analyticsUserId" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5">
              <option value="all">Alle User</option>
              <option v-for="user in analyticsUserOptions" :key="`auser-${user.id}`" :value="String(user.id)">{{ user.label }}</option>
            </select>
            <select v-model="analyticsPerfumeId" class="rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5">
              <option value="all">Alle Düfte</option>
              <option v-for="perfume in analyticsPerfumeOptions" :key="`aperf-${perfume.id}`" :value="String(perfume.id)">{{ perfume.label }}</option>
            </select>
          </div>

          <div v-if="analyticsLoading" class="mt-5 rounded-2xl border border-stone-800 bg-stone-950/60 p-5 text-sm text-stone-300">
            Lade Bewertungsdaten...
          </div>

          <div v-else class="mt-5 space-y-6">
            <div v-if="analyticsPerfumeId !== 'all'" class="grid gap-4 lg:grid-cols-2">
              <article class="rounded-2xl border border-stone-800 bg-stone-950/60 p-4">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-stone-300">Passgenauigkeit (1-5)</h3>
                <div class="mt-3 space-y-2">
                  <div v-for="bar in passgenauigkeitChart" :key="`pm-${bar.label}`" class="grid grid-cols-[52px_1fr_40px] items-center gap-3 text-xs text-stone-300">
                    <span>{{ bar.label }}</span>
                    <div class="h-2 overflow-hidden rounded bg-stone-800">
                      <div class="h-full bg-amber-400" :style="{ width: bar.width }" />
                    </div>
                    <span class="text-right">{{ bar.count }}</span>
                  </div>
                </div>
              </article>

              <article class="rounded-2xl border border-stone-800 bg-stone-950/60 p-4">
                <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-stone-300">Duftfamilien (Nennungen)</h3>
                <div class="mt-3 space-y-2">
                  <div v-if="!duftfamilienChart.length" class="text-xs text-stone-400">Keine Duftfamilien-Antworten für diesen Filter.</div>
                  <div v-for="bar in duftfamilienChart" :key="`df-${bar.label}`" class="grid grid-cols-[110px_1fr_40px] items-center gap-3 text-xs text-stone-300">
                    <span class="truncate">{{ bar.label }}</span>
                    <div class="h-2 overflow-hidden rounded bg-stone-800">
                      <div class="h-full bg-emerald-400" :style="{ width: bar.width }" />
                    </div>
                    <span class="text-right">{{ bar.count }}</span>
                  </div>
                </div>
              </article>
            </div>

            <article class="rounded-2xl border border-stone-800 bg-stone-950/60 p-4">
              <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-stone-300">User × Set Übersicht (Passgenauigkeit je Duft)</h3>
              <div v-if="!userSetSummary.length" class="mt-3 text-sm text-stone-400">Keine Bewertungsdaten für den aktuellen Filter.</div>
              <div v-else class="mt-3 overflow-auto">
                <table class="min-w-full divide-y divide-stone-800 text-sm">
                  <thead class="bg-stone-950/80 text-left text-stone-300">
                    <tr>
                      <th class="px-3 py-2 font-semibold">User</th>
                      <th class="px-3 py-2 font-semibold">Sample-Set</th>
                      <th class="px-3 py-2 font-semibold">Düfte / Passgenauigkeit</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-stone-800 bg-stone-950/40 text-stone-200">
                    <tr v-for="row in userSetSummary" :key="`summary-${row.userId}-${row.setId}-${row.userLabel}`" class="align-top">
                      <td class="px-3 py-2">{{ row.userLabel }}</td>
                      <td class="px-3 py-2">{{ row.setTitle }}</td>
                      <td class="px-3 py-2">
                        <div class="flex flex-wrap gap-2">
                          <span v-for="entry in row.entries" :key="`${row.userId}-${row.setId}-${entry.perfumeLabel}`" class="rounded-full border border-stone-700 bg-stone-900 px-2.5 py-1 text-xs">
                            {{ entry.perfumeLabel }}: {{ entry.passgenauigkeit ? `${entry.passgenauigkeit}/5` : '-' }}
                          </span>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </article>

            <article class="rounded-2xl border border-stone-800 bg-stone-950/60 p-4">
              <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-stone-300">Einzelbewertungen als Liste pro Parfüm</h3>
              <div v-if="!ratingsByPerfume.length" class="mt-3 text-sm text-stone-400">Keine Einzelbewertungen für den aktuellen Filter.</div>
              <div v-else class="mt-3 space-y-4">
                <article v-for="group in ratingsByPerfume" :key="`perf-group-${group.perfumeId}`" class="rounded-xl border border-stone-800 bg-stone-900/50 p-3">
                  <h4 class="text-sm font-semibold text-amber-300">{{ group.perfumeLabel }}</h4>
                  <div class="mt-2 space-y-2">
                    <div
                      v-for="entry in group.rows"
                      :key="`entry-${group.perfumeId}-${entry.ratingId}`"
                      class="rounded-lg border border-stone-800 bg-stone-950/70 p-2 text-xs text-stone-200"
                    >
                      <p><span class="text-stone-400">User:</span> {{ entry.userLabel }}</p>
                      <p><span class="text-stone-400">Set:</span> {{ entry.setTitle }}</p>
                      <p><span class="text-stone-400">Gesamt-Score:</span> {{ entry.overallScore ?? '-' }} · <span class="text-stone-400">Passgenauigkeit:</span> {{ entry.passgenauigkeit ? `${entry.passgenauigkeit}/5` : '-' }}</p>
                      <p><span class="text-stone-400">Duftfamilien:</span> {{ entry.duftfamilien.length ? entry.duftfamilien.join(', ') : '-' }}</p>
                      <div class="mt-1.5 rounded-md border border-stone-800 bg-stone-900/70 p-2">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-stone-400">Alle Antworten</p>
                        <div v-if="entry.allAnswers.length" class="grid gap-1">
                          <p v-for="answer in entry.allAnswers" :key="`ans-${entry.ratingId}-${answer.key}`" class="text-[11px] text-stone-200">
                            <span class="text-stone-400">{{ answer.label }}:</span> {{ answer.value }}
                          </p>
                        </div>
                        <p v-else class="text-[11px] text-stone-500">Keine Detailantworten gespeichert.</p>
                      </div>
                      <p><span class="text-stone-400">Bewertet am:</span> {{ entry.ratedAt }}</p>
                    </div>
                  </div>
                </article>
              </div>
            </article>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>

type LoginPayload = {
  email: string
  password: string
}

type RegisterPayload = {
  email: string
  password: string
  firstName?: string
  lastName?: string
  age: number
}

type ForgotPasswordPayload = {
  email: string
  appUrl?: string
}

type ResetPasswordPayload = {
  token: string
  password: string
}

type SampleItem = {
  user_sample_id: number
  sample_status: 'assigned' | 'delivered' | 'rated'
  assigned_at: string
  rated_at: string | null
  sample_id: number
  code: string
  perfume_name: string
  brand_name: string | null
  description?: string | null
  image_path?: string | null
  image_url?: string | null
}

type AdminUser = {
  id: number
  email: string
  first_name: string | null
  last_name: string | null
  is_admin: number | boolean
  sample_count: number
  assigned_sets_summary?: string | null
}

type AdminDefaultSampleSet = {
  id: number
  title: string
  status: string
  rating_deadline_at?: string | null
  resolution: 'title-match' | 'first-active' | 'configured-default' | 'list-fallback' | 'shared-resolver'
}

type PerfumeItem = {
  id: number
  name: string
  brand_name: string | null
  description: string | null
  image_path?: string | null
  image_url?: string | null
  size_ml?: number | null
  price_cents?: number
  discount_percent?: number
  is_active?: number | boolean
}

type AdminSampleSet = {
  id: number
  title: string
  description: string | null
  image_path?: string | null
  image_url?: string | null
  status: 'active' | 'inactive'
  perfume_count: number
  assigned_count: number
  next_rating_deadline_at?: string | null
  overdue_assignments?: number
  perfume_ids: number[]
}

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

type SampleSetItem = {
  user_sample_set_id: number
  set_status: 'assigned' | 'delivered' | 'completed'
  assigned_at: string
  rating_deadline_at?: string | null
  completed_at: string | null
  sample_set_id: number
  title: string
  description: string | null
  image_path?: string | null
  image_url?: string | null
  perfume_count: number
}

type SampleSetDetailPerfume = {
  perfume_id: number
  name: string
  brand_name: string | null
  description: string | null
  image_path?: string | null
  image_url?: string | null
  size_ml?: number | null
  price_cents?: number
  sort_order: number
  rating_id?: number | null
  overall_score?: number | null
  longevity_score?: number | null
  sillage_score?: number | null
}

type SaveRatingPayload = {
  userSampleId: number
  overallScore: number
  longevityScore: number
  sillageScore: number
  answers: Record<string, string | number | string[]>
}

type SavePerfumeRatingPayload = {
  userSampleSetId: number
  perfumeId: number
  overallScore: number
  longevityScore: number
  sillageScore: number
  answers: Record<string, string | number | string[]>
}

export const useAuthApi = () => {
  const config = useRuntimeConfig()

  const normalizeApiBase = (rawValue: unknown) => {
    let value = String(rawValue || '').trim()
    if (!value) {
      return '/api'
    }

    // Keep relative base paths untouched (e.g. "/api").
    if (value.startsWith('/')) {
      return value.replace(/\/+$/, '')
    }

    // Fix malformed protocol values like "http:api.nichecult.de".
    value = value.replace(/^https:(?!\/\/)/i, 'https://')
    value = value.replace(/^http:(?!\/\/)/i, 'https://')

    // Always prefer HTTPS for remote API hosts.
    value = value.replace(/^http:\/\//i, 'https://')

    // Fix protocol-relative values like "//api.nichecult.de".
    if (value.startsWith('//')) {
      value = `https:${value}`
    }

    // Add protocol if only host is provided.
    if (!/^https?:\/\//i.test(value)) {
      value = `https://${value}`
    }

    return value.replace(/\/+$/, '')
  }

  const apiBase = computed(() => normalizeApiBase(config.public.apiBase as string))

  const apiUrl = (path: string) => {
    const normalizedPath = path.replace(/^\/+/, '')
    return `${apiBase.value}/${normalizedPath}`
  }

  const login = async (payload: LoginPayload) => {
    return await $fetch<{ ok: boolean; token?: string; error?: string }>(apiUrl('login.php'), {
      method: 'POST',
      body: payload,
    })
  }

  const register = async (payload: RegisterPayload) => {
    return await $fetch<{ ok: boolean; userId?: number; error?: string }>(apiUrl('register.php'), {
      method: 'POST',
      body: payload,
    })
  }

  const requestPasswordReset = async (payload: ForgotPasswordPayload) => {
    return await $fetch<{ ok: boolean; message?: string; error?: string }>(apiUrl('forgot-password.php'), {
      method: 'POST',
      body: payload,
    })
  }

  const resetPassword = async (payload: ResetPasswordPayload) => {
    return await $fetch<{ ok: boolean; error?: string }>(apiUrl('reset-password.php'), {
      method: 'POST',
      body: payload,
    })
  }

  const me = async (token: string) => {
    return await $fetch<{ ok: boolean; user?: Record<string, unknown>; kurationDone?: boolean; error?: string }>(apiUrl('me.php'), {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const listSamples = async (token: string) => {
    return await $fetch<{ ok: boolean; samples?: SampleItem[]; error?: string }>(apiUrl('samples.php'), {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const saveSampleRating = async (token: string, payload: SaveRatingPayload) => {
    return await $fetch<{ ok: boolean; ratingId?: number; error?: string }>(apiUrl('save-rating.php'), {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body: payload,
    })
  }

  const listAdminUsers = async (token: string) => {
    return await $fetch<{ ok: boolean; users?: AdminUser[]; error?: string }>(apiUrl('admin-users.php'), {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const getAdminDefaultSampleSet = async (token: string) => {
    return await $fetch<{ ok: boolean; defaultSampleSet?: AdminDefaultSampleSet; error?: string }>(apiUrl('admin-default-sample-set.php'), {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const listAdminPerfumes = async (token: string) => {
    return await $fetch<{ ok: boolean; perfumes?: PerfumeItem[]; error?: string }>(apiUrl('admin-list-perfumes.php'), {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const listAdminSampleSets = async (token: string) => {
    return await $fetch<{ ok: boolean; sampleSets?: AdminSampleSet[]; error?: string }>(apiUrl('admin-list-sample-sets.php'), {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const deleteAdminUser = async (token: string, userId: number) => {
    const body = new FormData()
    body.append('id', String(userId))
    return await $fetch<{ ok: boolean; error?: string }>(apiUrl('admin-delete-user.php'), {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body,
    })
  }

  const listAdminRatingAnalytics = async (token: string) => {
    return await $fetch<{ ok: boolean; rows?: AdminRatingAnalyticsRow[]; error?: string }>(apiUrl('admin-rating-analytics.php'), {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const listPerfumes = async () => {
    return await $fetch<{ ok: boolean; perfumes?: PerfumeItem[]; error?: string }>(apiUrl('perfumes.php'))
  }

  const listSampleSets = async (token: string) => {
    return await $fetch<{ ok: boolean; sampleSets?: SampleSetItem[]; error?: string }>(apiUrl('sample-sets.php'), {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const getSampleSetDetail = async (token: string, userSampleSetId: number) => {
    return await $fetch<{ ok: boolean; sampleSet?: Record<string, unknown>; perfumes?: SampleSetDetailPerfume[]; favoritePerfumeId?: number | null; error?: string }>(apiUrl('sample-set-detail.php'), {
      query: {
        user_sample_set_id: userSampleSetId,
      },
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const savePerfumeRating = async (token: string, payload: SavePerfumeRatingPayload) => {
    return await $fetch<{ ok: boolean; ratingId?: number; setStatus?: string; error?: string }>(apiUrl('save-perfume-rating.php'), {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body: payload,
    })
  }

  const assignSampleStartSet = async (token: string) => {
    const url = apiUrl('start-assign-sample-set.php')
    console.info('[sample-start] POST URL', url)

    return await $fetch<{ ok: boolean; user_sample_set_id?: number; already_existed?: boolean; error?: string }>(url, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const assignQrSampleSet = async (token: string) => {
    return await assignSampleStartSet(token)
  }

  const adminBackfillSampleSet = async (token: string) => {
    return await $fetch<{ ok: boolean; assigned_count?: number; skipped_count?: number; default_set_id?: number; default_set_title?: string; errors?: string[]; error?: string }>(apiUrl('admin-backfill-sample-set.php'), {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const adminSetDefaultSampleSet = async (token: string, sampleSetId: number) => {
    const body = new FormData()
    body.append('sample_set_id', String(sampleSetId))

    return await $fetch<{ ok: boolean; assigned_count?: number; default_set_id?: number; default_set_title?: string; error?: string }>(apiUrl('admin-set-default-sample-set.php'), {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body,
    })
  }

  return {
    login,
    register,
    requestPasswordReset,
    resetPassword,
    me,
    listSamples,
    saveSampleRating,
    listAdminUsers,
    getAdminDefaultSampleSet,
    deleteAdminUser,
    listAdminPerfumes,
    listAdminSampleSets,
    listAdminRatingAnalytics,
    listPerfumes,
    listSampleSets,
    getSampleSetDetail,
    savePerfumeRating,
    assignSampleStartSet,
    assignQrSampleSet,
    adminBackfillSampleSet,
    adminSetDefaultSampleSet,
  }
}

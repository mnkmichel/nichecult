type LoginPayload = {
  email: string
  password: string
}

type RegisterPayload = {
  email: string
  password: string
  firstName?: string
  lastName?: string
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
}

type PerfumeItem = {
  id: number
  name: string
  brand_name: string | null
  description: string | null
  image_path?: string | null
  image_url?: string | null
  price_cents?: number
  discount_percent?: number
  is_active?: number | boolean
}

type SampleSetItem = {
  user_sample_set_id: number
  set_status: 'assigned' | 'delivered' | 'completed'
  assigned_at: string
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

  const apiBase = computed(() => (config.public.apiBase as string).replace(/\/$/, ''))

  const login = async (payload: LoginPayload) => {
    return await $fetch<{ ok: boolean; token?: string; error?: string }>(`${apiBase.value}/login.php`, {
      method: 'POST',
      body: payload,
    })
  }

  const register = async (payload: RegisterPayload) => {
    return await $fetch<{ ok: boolean; userId?: number; error?: string }>(`${apiBase.value}/register.php`, {
      method: 'POST',
      body: payload,
    })
  }

  const me = async (token: string) => {
    return await $fetch<{ ok: boolean; user?: Record<string, unknown>; error?: string }>(`${apiBase.value}/me.php`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const listSamples = async (token: string) => {
    return await $fetch<{ ok: boolean; samples?: SampleItem[]; error?: string }>(`${apiBase.value}/samples.php`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const saveSampleRating = async (token: string, payload: SaveRatingPayload) => {
    return await $fetch<{ ok: boolean; ratingId?: number; error?: string }>(`${apiBase.value}/save-rating.php`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body: payload,
    })
  }

  const listAdminUsers = async (token: string) => {
    return await $fetch<{ ok: boolean; users?: AdminUser[]; error?: string }>(`${apiBase.value}/admin-users.php`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const listAdminPerfumes = async (token: string) => {
    return await $fetch<{ ok: boolean; perfumes?: PerfumeItem[]; error?: string }>(`${apiBase.value}/admin-list-perfumes.php`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const listPerfumes = async () => {
    return await $fetch<{ ok: boolean; perfumes?: PerfumeItem[]; error?: string }>(`${apiBase.value}/perfumes.php`)
  }

  const listSampleSets = async (token: string) => {
    return await $fetch<{ ok: boolean; sampleSets?: SampleSetItem[]; error?: string }>(`${apiBase.value}/sample-sets.php`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const getSampleSetDetail = async (token: string, userSampleSetId: number) => {
    return await $fetch<{ ok: boolean; sampleSet?: Record<string, unknown>; perfumes?: SampleSetDetailPerfume[]; error?: string }>(`${apiBase.value}/sample-set-detail.php`, {
      query: {
        user_sample_set_id: userSampleSetId,
      },
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  }

  const savePerfumeRating = async (token: string, payload: SavePerfumeRatingPayload) => {
    return await $fetch<{ ok: boolean; ratingId?: number; setStatus?: string; error?: string }>(`${apiBase.value}/save-perfume-rating.php`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
      },
      body: payload,
    })
  }

  return {
    login,
    register,
    me,
    listSamples,
    saveSampleRating,
    listAdminUsers,
    listAdminPerfumes,
    listPerfumes,
    listSampleSets,
    getSampleSetDetail,
    savePerfumeRating,
  }
}

export default defineNuxtRouteMiddleware(async () => {
  if (process.server) {
    return
  }

  const token = localStorage.getItem('nichecult_token')
  if (!token) {
    return navigateTo('/admin/login')
  }

  const config = useRuntimeConfig()
  const apiBase = (config.public.apiBase as string).replace(/\/$/, '')

  try {
    const res = await $fetch<{ ok: boolean; user?: { is_admin?: number | boolean } }>(`${apiBase}/me.php`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })

    if (!res.ok || !res.user || !res.user.is_admin) {
      localStorage.removeItem('nichecult_token')
      return navigateTo('/admin/login')
    }
  } catch {
    localStorage.removeItem('nichecult_token')
    return navigateTo('/admin/login')
  }
})

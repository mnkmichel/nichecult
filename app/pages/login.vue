<script setup lang="ts">
const { login, me } = useAuthApi()

const form = reactive({
  email: '',
  password: '',
})

const loading = ref(false)
const error = ref('')
const profile = ref<Record<string, unknown> | null>(null)

const submit = async () => {
  error.value = ''
  loading.value = true

  try {
    const res = await login(form)

    if (!res.ok || !res.token) {
      error.value = res.error || 'Login fehlgeschlagen'
      return
    }

    localStorage.setItem('nichecult_token', res.token)
    const meRes = await me(res.token)
    profile.value = meRes.user || null

    if (meRes.kurationDone) {
      localStorage.setItem('nichecult_kuration_ever_done', '1')
    }

    await navigateTo('/')
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Fehler beim Login'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-stone-100 px-4 py-10">
    <div class="mx-auto w-full max-w-md rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
      <h1 class="text-2xl font-bold">Login</h1>
      <p class="mt-1 text-sm text-stone-600">Melde dich mit deinem Konto an.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <input v-model="form.email" class="w-full rounded-lg border border-stone-300 px-3 py-2" type="email" placeholder="E-Mail" required />
        <input v-model="form.password" class="w-full rounded-lg border border-stone-300 px-3 py-2" type="password" placeholder="Passwort" required />

        <div class="text-right">
          <NuxtLink to="/forgot-password" class="text-sm text-stone-700 underline hover:text-stone-900">Passwort vergessen?</NuxtLink>
        </div>

        <button :disabled="loading" class="w-full rounded-lg bg-stone-900 px-4 py-2 font-semibold text-white disabled:opacity-60">
          {{ loading ? 'Anmelden...' : 'Einloggen' }}
        </button>
      </form>

      <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
      <p v-if="profile" class="mt-4 text-sm text-emerald-700">
        Eingeloggt als {{ profile.email }}
      </p>

      <NuxtLink to="/register" class="mt-5 inline-block text-sm text-stone-700 underline">Noch kein Konto? Registrieren</NuxtLink>
    </div>
  </main>
</template>

<script setup lang="ts">
const { login, me } = useAuthApi()

const form = reactive({
  email: '',
  password: '',
})

const loading = ref(false)
const error = ref('')

const submit = async () => {
  error.value = ''
  loading.value = true

  try {
    const res = await login(form)

    if (!res.ok || !res.token) {
      error.value = res.error || 'Admin-Login fehlgeschlagen'
      return
    }

    const profile = await me(res.token)
    if (!profile.ok || !profile.user || !profile.user.is_admin) {
      error.value = 'Dieses Konto hat keine Admin-Rechte.'
      localStorage.removeItem('nichecult_token')
      return
    }

    localStorage.setItem('nichecult_token', res.token)
    await navigateTo('/admin')
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Admin-Login fehlgeschlagen'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-stone-950 px-4 py-12 text-stone-100">
    <div class="mx-auto max-w-md rounded-3xl border border-stone-800 bg-stone-900/80 p-6 shadow-2xl">
      <p class="text-xs uppercase tracking-[0.3em] text-amber-400">Nice Cult Admin</p>
      <h1 class="mt-2 text-3xl font-bold">Admin Login</h1>
      <p class="mt-2 text-sm text-stone-400">Nur fuer interne Verwaltung von Samples und Nutzerzuweisungen.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <input v-model="form.email" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3 text-white" type="email" placeholder="Admin E-Mail" required />
        <input v-model="form.password" class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-3 text-white" type="password" placeholder="Passwort" required />
        <button :disabled="loading" class="w-full rounded-xl bg-amber-400 px-4 py-3 font-semibold text-stone-950 disabled:opacity-60">
          {{ loading ? 'Pruefe...' : 'Als Admin einloggen' }}
        </button>
      </form>

      <p v-if="error" class="mt-4 text-sm text-red-400">{{ error }}</p>
    </div>
  </main>
</template>

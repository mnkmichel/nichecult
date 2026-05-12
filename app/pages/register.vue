<script setup lang="ts">
const { register, login, me } = useAuthApi()
const route = useRoute()

const redirectTo = computed(() => {
  const r = route.query.redirect
  return typeof r === 'string' && r.startsWith('/') ? r : '/'
})

const loginLink = computed(() =>
  redirectTo.value !== '/' ? `/login?redirect=${encodeURIComponent(redirectTo.value)}` : '/login'
)

const form = reactive({
  firstName: '',
  lastName: '',
  age: null as number | null,
  email: '',
  password: '',
})

const loading = ref(false)
const error = ref('')
const success = ref('')

const submit = async () => {
  error.value = ''
  success.value = ''
  loading.value = true

  try {
    const res = await register(form)

    if (!res.ok) {
      error.value = res.error || 'Registrierung fehlgeschlagen'
      return
    }

    const loginRes = await login({
      email: form.email,
      password: form.password,
    })

    if (!loginRes.ok || !loginRes.token) {
      await navigateTo(loginLink.value)
      return
    }

    localStorage.setItem('nichecult_token', loginRes.token)
    const meRes = await me(loginRes.token)
    if (meRes.kurationDone) {
      localStorage.setItem('nichecult_kuration_ever_done', '1')
      window.dispatchEvent(new Event('nichecult:kuration-completed'))
    }

    success.value = 'Registrierung erfolgreich. Sie werden weitergeleitet...'
    form.age = null
    form.password = ''
    await navigateTo(redirectTo.value)
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Fehler bei der Registrierung'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-stone-100 px-4 py-10">
    <div class="mx-auto w-full max-w-md rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
      <h1 class="text-2xl font-bold">Konto erstellen</h1>
      <p class="mt-1 text-sm text-stone-600">Erstelle dein Nice Cult-Profil.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <input v-model="form.firstName" class="w-full rounded-lg border border-stone-300 px-3 py-2" type="text" placeholder="Vorname" />
        <input v-model="form.lastName" class="w-full rounded-lg border border-stone-300 px-3 py-2" type="text" placeholder="Nachname" />
        <input v-model.number="form.age" class="w-full rounded-lg border border-stone-300 px-3 py-2" type="number" min="12" max="120" placeholder="Alter" required />
        <input v-model="form.email" class="w-full rounded-lg border border-stone-300 px-3 py-2" type="email" placeholder="E-Mail" required />
        <input v-model="form.password" class="w-full rounded-lg border border-stone-300 px-3 py-2" type="password" placeholder="Passwort (min. 8 Zeichen)" required />

        <button :disabled="loading" class="w-full rounded-lg bg-stone-900 px-4 py-2 font-semibold text-white disabled:opacity-60">
          {{ loading ? 'Speichern...' : 'Registrieren' }}
        </button>
      </form>

      <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
      <p v-if="success" class="mt-4 text-sm text-emerald-700">{{ success }}</p>

      <NuxtLink :to="loginLink" class="mt-5 inline-block text-sm text-stone-700 underline">Zum Login</NuxtLink>
    </div>
  </main>
</template>

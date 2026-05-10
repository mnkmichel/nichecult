<script setup lang="ts">
const { register } = useAuthApi()

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

    success.value = 'Registrierung erfolgreich. Du kannst dich jetzt einloggen.'
    form.age = null
    form.password = ''
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

      <NuxtLink to="/login" class="mt-5 inline-block text-sm text-stone-700 underline">Zum Login</NuxtLink>
    </div>
  </main>
</template>

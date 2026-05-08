<script setup lang="ts">
const route = useRoute()
const { resetPassword } = useAuthApi()

const token = computed(() => String(route.query.token || ''))

const form = reactive({
  password: '',
  passwordConfirm: '',
})

const loading = ref(false)
const error = ref('')
const success = ref('')

const canSubmit = computed(() => {
  return token.value !== ''
    && form.password.length >= 8
    && form.passwordConfirm.length >= 8
    && form.password === form.passwordConfirm
})

const submit = async () => {
  error.value = ''
  success.value = ''

  if (!canSubmit.value) {
    error.value = 'Bitte pruefen Sie Token und Passwort-Eingaben.'
    return
  }

  loading.value = true
  try {
    const res = await resetPassword({
      token: token.value,
      password: form.password,
    })

    if (!res.ok) {
      error.value = res.error || 'Zuruecksetzen fehlgeschlagen'
      return
    }

    success.value = 'Ihr Passwort wurde erfolgreich aktualisiert.'
    form.password = ''
    form.passwordConfirm = ''
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Zuruecksetzen fehlgeschlagen'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-stone-100 px-4 py-10">
    <div class="mx-auto w-full max-w-md rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
      <h1 class="text-2xl font-bold">Passwort zuruecksetzen</h1>
      <p class="mt-1 text-sm text-stone-600">Waehlen Sie ein neues Passwort mit mindestens 8 Zeichen.</p>

      <p v-if="token === ''" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        Ungueltiger Reset-Link. Bitte fordern Sie einen neuen Link an.
      </p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <input v-model="form.password" class="w-full rounded-lg border border-stone-300 px-3 py-2" type="password" placeholder="Neues Passwort" minlength="8" required />
        <input v-model="form.passwordConfirm" class="w-full rounded-lg border border-stone-300 px-3 py-2" type="password" placeholder="Passwort wiederholen" minlength="8" required />

        <button :disabled="loading || !canSubmit" class="w-full rounded-lg bg-stone-900 px-4 py-2 font-semibold text-white disabled:opacity-60">
          {{ loading ? 'Speichern...' : 'Passwort speichern' }}
        </button>
      </form>

      <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
      <p v-if="success" class="mt-4 text-sm text-emerald-700">{{ success }}</p>

      <NuxtLink to="/login" class="mt-5 inline-block text-sm text-stone-700 underline">Zum Login</NuxtLink>
    </div>
  </main>
</template>

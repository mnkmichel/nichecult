<script setup lang="ts">
const { requestPasswordReset } = useAuthApi()

const form = reactive({
  email: '',
})

const loading = ref(false)
const error = ref('')
const success = ref('')

const submit = async () => {
  error.value = ''
  success.value = ''
  loading.value = true

  try {
    const appUrl = typeof window !== 'undefined' ? window.location.origin : undefined
    const res = await requestPasswordReset({
      email: form.email,
      appUrl,
    })

    if (!res.ok) {
      error.value = res.error || 'Anfrage fehlgeschlagen'
      return
    }

    success.value = res.message || 'Wenn ein Konto mit dieser E-Mail existiert, haben wir einen Link zum Zuruecksetzen geschickt.'
  } catch (e: any) {
    error.value = e?.data?.error || e?.message || 'Anfrage fehlgeschlagen'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-stone-100 px-4 py-10">
    <div class="mx-auto w-full max-w-md rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
      <h1 class="text-2xl font-bold">Passwort vergessen</h1>
      <p class="mt-1 text-sm text-stone-600">Geben Sie Ihre E-Mail ein. Wir senden Ihnen einen Link zum Zuruecksetzen.</p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <input v-model="form.email" class="w-full rounded-lg border border-stone-300 px-3 py-2" type="email" placeholder="E-Mail" required />

        <button :disabled="loading" class="w-full rounded-lg bg-stone-900 px-4 py-2 font-semibold text-white disabled:opacity-60">
          {{ loading ? 'Senden...' : 'Reset-Link anfordern' }}
        </button>
      </form>

      <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
      <p v-if="success" class="mt-4 text-sm text-emerald-700">{{ success }}</p>

      <NuxtLink to="/login" class="mt-5 inline-block text-sm text-stone-700 underline">Zurueck zum Login</NuxtLink>
    </div>
  </main>
</template>

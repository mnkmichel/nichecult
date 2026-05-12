<script setup lang="ts">
definePageMeta({ middleware: [] })

const { assignSampleStartSet, me } = useAuthApi()

const status = ref<'loading' | 'error'>('loading')
const errorMessage = ref('')

onMounted(async () => {
  const token = localStorage.getItem('nichecult_token')

  if (!token) {
    await navigateTo('/login?redirect=/sample-start')
    return
  }

  try {
    const meRes = await me(token)
    if (!meRes.ok) {
      localStorage.removeItem('nichecult_token')
      await navigateTo('/login?redirect=/sample-start')
      return
    }
  } catch {
    localStorage.removeItem('nichecult_token')
    await navigateTo('/login?redirect=/sample-start')
    return
  }

  try {
    const res = await assignSampleStartSet(token)
    if (!res.ok || !res.user_sample_set_id) {
      errorMessage.value = res.error || 'Zuweisung fehlgeschlagen. Bitte versuche es erneut.'
      status.value = 'error'
      return
    }

    await navigateTo(`/sets/${res.user_sample_set_id}`)
  } catch (e: any) {
    errorMessage.value = e?.data?.error || e?.message || 'Ein Fehler ist aufgetreten.'
    status.value = 'error'
  }
})
</script>

<template>
  <main class="flex min-h-screen flex-col items-center justify-center bg-stone-100 px-4">
    <div class="w-full max-w-sm rounded-2xl border border-stone-200 bg-white p-8 text-center shadow-sm">
      <template v-if="status === 'loading'">
        <div class="mb-4 flex justify-center">
          <svg class="h-10 w-10 animate-spin text-stone-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
        </div>
        <p class="text-sm text-stone-600">Ihre Samples werden vorbereitet...</p>
      </template>

      <template v-else-if="status === 'error'">
        <p class="text-sm font-semibold text-red-600">{{ errorMessage }}</p>
        <NuxtLink to="/" class="mt-5 inline-block text-sm text-stone-700 underline">Zurueck zur Startseite</NuxtLink>
      </template>
    </div>
  </main>
</template>

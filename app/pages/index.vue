<script setup lang="ts">
const token = useState<string | null>('auth-token', () => null)
const duftkurationDone = ref(false)

onMounted(() => {
  token.value = localStorage.getItem('nichecult_token')
  duftkurationDone.value = localStorage.getItem('nichecult_duftkuration_done') === '1'
})
</script>

<template>
  <main class="min-h-screen bg-[linear-gradient(160deg,#f6f1e8_0%,#efe4d1_45%,#e6d8bf_100%)] text-stone-900">
    <section class="mx-auto max-w-5xl px-6 py-16 md:py-20">
      <p class="mb-3 text-sm uppercase tracking-[0.32em] text-amber-800">Nichecult</p>
      <h1 class="max-w-3xl text-4xl font-bold leading-tight md:text-6xl">Parfum finden, testen und intelligent kuratieren</h1>
      <p class="mt-5 max-w-3xl text-lg text-stone-700">
        Starte mit deiner Duft-Kuration, entdecke passende Parfums und verwalte danach deine Samples mit klarem Status.
      </p>

      <nav class="mt-10 inline-flex flex-wrap gap-2 rounded-2xl border border-stone-300 bg-white/80 p-2 shadow-sm">
        <NuxtLink to="/parfum" class="rounded-xl border border-transparent bg-stone-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-stone-800">
          Parfum
        </NuxtLink>
        <NuxtLink to="/duftkuration" class="rounded-xl border border-stone-300 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:bg-stone-100">
          Duft-Kuration
        </NuxtLink>
        <NuxtLink
          v-if="token && duftkurationDone"
          to="/sets"
          class="rounded-xl border border-emerald-700 bg-emerald-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-600"
        >
          Ihre Samples
        </NuxtLink>
      </nav>

      <div class="mt-8 flex flex-wrap gap-3">
        <NuxtLink to="/register" class="rounded-xl bg-stone-900 px-5 py-3 font-semibold text-white hover:bg-stone-800">
          Jetzt registrieren
        </NuxtLink>
        <NuxtLink to="/login" class="rounded-xl border border-stone-300 bg-white px-5 py-3 font-semibold hover:bg-stone-50">
          Login
        </NuxtLink>
      </div>

      <div v-if="token && !duftkurationDone" class="mt-8 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900">
        Bitte zuerst die Duft-Kuration abschliessen. Danach wird der Reiter Ihre Samples freigeschaltet.
      </div>

      <div v-if="token && duftkurationDone" class="mt-8 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
        Duft-Kuration abgeschlossen. Ihre Samples sind jetzt freigeschaltet.
      </div>
    </section>
  </main>
</template>

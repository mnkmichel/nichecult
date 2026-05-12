<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

const duftkurationDone = ref(false)

onMounted(() => {
  duftkurationDone.value = localStorage.getItem('nichecult_duftkuration_done') === '1'
})
</script>

<template>
  <main class="nc-page relative isolate overflow-hidden bg-[#ece8e1] text-[#201d1d]">
    <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-[radial-gradient(circle,#f7dba8_0%,transparent_70%)] opacity-55 blur-xl" />
    <div class="pointer-events-none absolute -bottom-28 right-8 h-80 w-80 rounded-full bg-[radial-gradient(circle,#d8c09c_0%,transparent_70%)] opacity-45 blur-xl" />

    <section class="nc-page-frame relative z-10 flex min-h-[calc(100vh-4rem)] flex-col">
      <SiteHeaderNav title="Startseite" active="home" />

      <div class="flex min-h-0 flex-1 items-stretch">
        <article class="flex min-h-0 w-full flex-col items-center justify-center rounded-[46px] border border-[#ddcfb8] bg-[linear-gradient(170deg,#ded2be_0%,#d4c6af_100%)] p-7 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.55),0_20px_46px_-24px_rgba(60,42,24,0.8)] md:p-10 lg:px-14 lg:py-12">
          <h2 class="text-[38px] font-semibold leading-[1.05] md:text-[62px] lg:text-[74px]">
            Finden Sie Ihren
            <br>
            Signature-Duft
          </h2>

          <p class="mt-5 max-w-4xl text-[21px] leading-[1.2] text-[#322f2f] md:text-[34px] lg:text-[42px]">
            mit exklusiven Duftproben
            <br>
            abgestimmt auf Ihren Geschmack.
          </p>

          <NuxtLink to="/duftkuration" class="mt-8 inline-flex h-15 items-center justify-center rounded-lg border border-[#d4c39d] bg-[linear-gradient(180deg,#c4a765_0%,#b69047_100%)] px-10 text-center text-[24px] font-semibold leading-[1.05] text-[#171414] shadow-[0_10px_16px_-10px_rgba(64,44,18,0.8)] transition hover:-translate-y-0.5 hover:brightness-95 md:h-20 md:px-14 md:text-[38px]">
            Parfum
            finden
          </NuxtLink>
        </article>
      </div>
    </section>
  </main>
</template>

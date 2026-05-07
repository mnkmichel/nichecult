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

      <div class="grid min-h-0 flex-1 items-stretch gap-4 lg:grid-cols-[0.9fr_1.35fr]">
        <article class="flex min-h-0 flex-col items-center justify-center rounded-[40px] border border-[#ddcfb8] bg-[linear-gradient(170deg,#ded2be_0%,#d4c6af_100%)] p-5 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.55),0_18px_40px_-24px_rgba(60,42,24,0.8)] md:p-7 lg:px-10 lg:py-8">
          <h2 class="text-[30px] font-semibold leading-[1.08] md:text-[48px] lg:text-[54px]">
            Finden Sie Ihren
            <br>
            Signature-Duft
          </h2>

          <p class="mt-4 max-w-110 text-[18px] leading-[1.22] text-[#322f2f] md:text-[29px] lg:text-[35px]">
            mit exklusiven Duftproben
            <br>
            abgestimmt auf Ihren Geschmack.
          </p>

          <NuxtLink to="/duftkuration" class="mt-5 inline-flex h-14 items-center justify-center rounded-lg border border-[#d4c39d] bg-[linear-gradient(180deg,#c4a765_0%,#b69047_100%)] px-8 text-center text-[22px] font-semibold leading-[1.05] text-[#171414] shadow-[0_10px_16px_-10px_rgba(64,44,18,0.8)] transition hover:-translate-y-0.5 hover:brightness-95 md:h-19.5 md:px-12 md:text-[34px]">
            Parfum
            finden
          </NuxtLink>
        </article>

        <article class="min-h-0 rounded-[14px] border border-[#ddd8ce] bg-[linear-gradient(120deg,#ffffff_0%,#f6f6f6_45%,#ffffff_100%)] p-4 shadow-[0_20px_35px_-28px_rgba(36,30,22,0.85)] md:p-7">
          <div class="grid h-full grid-rows-3 items-stretch">
            <div class="grid grid-cols-3 items-center gap-4 text-center">
              <p class="text-base font-extrabold tracking-[0.08em] text-[#141414] md:text-[40px]">CREED</p>
              <p class="text-[12px] font-bold tracking-[0.14em] text-[#1f1f1f] md:text-[28px]">ACQUA
                <br>DI
                <br>PARMA</p>
              <p class="text-[11px] font-semibold tracking-[0.08em] text-[#232323] md:text-[22px]">PARFUMS DE
                <br>MARLY</p>
            </div>

            <div class="grid grid-cols-3 items-center gap-4 text-center">
              <p class="text-[12px] font-semibold tracking-[0.12em] text-[#232323] md:text-[28px]">WIDIAN</p>
              <p class="text-base font-extrabold tracking-[0.12em] text-[#141414] md:text-[36px]">XERJOFF</p>
              <p class="text-[12px] font-semibold tracking-[0.09em] text-[#232323] md:text-[28px]">AMOUAGE</p>
            </div>

            <div class="grid grid-cols-3 items-center gap-4 text-center">
              <p class="text-[12px] font-semibold tracking-[0.11em] text-[#232323] md:text-[28px]">INITIO</p>
              <p class="text-base italic text-[#1a1a1a] md:text-[35px]">Kilian</p>
              <p class="text-base font-bold text-[#131313] md:text-[35px]">TOM FORD</p>
            </div>
          </div>
        </article>
      </div>
    </section>
  </main>
</template>

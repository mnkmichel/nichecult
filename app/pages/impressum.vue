<script setup lang="ts">
useSeoMeta({
  title: 'Impressum | Niche Cult',
  description: 'Impressum und Pflichtangaben gemäß § 5 DDG sowie weitere rechtliche Hinweise.',
})
</script>

<template>
  <main class="nc-page relative isolate overflow-hidden bg-[#ece8e1] text-[#201d1d]">
    <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-[radial-gradient(circle,#f7dba8_0%,transparent_70%)] opacity-55 blur-xl" />
    <div class="pointer-events-none absolute -bottom-28 right-8 h-80 w-80 rounded-full bg-[radial-gradient(circle,#d8c09c_0%,transparent_70%)] opacity-45 blur-xl" />

    <section class="nc-page-frame relative z-10 mx-auto min-h-[calc(100vh-4rem)] max-w-5xl py-8 md:py-12">
      <div class="rounded-3xl border border-[#d9cdb8] bg-white/90 p-6 shadow-[0_25px_45px_-30px_rgba(45,30,16,0.9)] md:p-10">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#e8ddcb] pb-6">
          <div>
            <p class="text-xs uppercase tracking-[0.26em] text-amber-800" style="font-family: Georgia, serif;">Niche Cult</p>
            <h1 class="mt-2 text-3xl font-semibold md:text-4xl">Impressum</h1>
            <p class="mt-3 max-w-2xl text-sm text-stone-600 md:text-base">
              Dieses Impressum enthält die gesetzlich erforderlichen Anbieterangaben.
              Bitte ersetze die Platzhalter in eckigen Klammern mit deinen echten Daten.
            </p>
          </div>
          <NuxtLink
            to="/"
            class="inline-flex rounded-xl border border-stone-300 bg-stone-100 px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-200"
          >
            Zur Startseite
          </NuxtLink>
        </div>

        <div class="mt-8 grid gap-5 md:grid-cols-2">
          <article class="rounded-2xl border border-[#e7ddcd] bg-[#fcfaf6] p-5">
            <h2 class="text-lg font-semibold">1. Anbieter</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              [Vollständiger Name / Firma]<br>
              [Rechtsform, falls vorhanden]<br>
              [Strasse und Hausnummer]<br>
              [PLZ Ort]<br>
              [Land]
            </p>
          </article>

          <article class="rounded-2xl border border-[#e7ddcd] bg-[#fcfaf6] p-5">
            <h2 class="text-lg font-semibold">2. Kontakt</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              Telefon: [Telefonnummer]<br>
              E-Mail: [E-Mail-Adresse]<br>
              Website: [Domain]
            </p>
          </article>

          <article class="rounded-2xl border border-[#e7ddcd] bg-[#fcfaf6] p-5">
            <h2 class="text-lg font-semibold">3. Vertretungsberechtigte Person</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              [Vorname Nachname]
            </p>
          </article>

          <article class="rounded-2xl border border-[#e7ddcd] bg-[#fcfaf6] p-5">
            <h2 class="text-lg font-semibold">4. Registereintrag (falls vorhanden)</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              Registergericht: [Registergericht]<br>
              Registernummer: [HRB/HRA/VR ...]
            </p>
          </article>

          <article class="rounded-2xl border border-[#e7ddcd] bg-[#fcfaf6] p-5">
            <h2 class="text-lg font-semibold">5. Umsatzsteuer-ID (falls vorhanden)</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              Umsatzsteuer-Identifikationsnummer gemäß § 27a UStG: [USt-IdNr.]
            </p>
          </article>

          <article class="rounded-2xl border border-[#e7ddcd] bg-[#fcfaf6] p-5">
            <h2 class="text-lg font-semibold">6. Verantwortlich für journalistisch-redaktionelle Inhalte (falls relevant)</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              [Vorname Nachname]<br>
              [Anschrift]
            </p>
          </article>
        </div>

        <div class="mt-8 space-y-4">
          <article class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
            <h2 class="text-lg font-semibold">7. EU-Streitschlichtung</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
              <a class="font-semibold text-amber-800 underline-offset-2 hover:underline" href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener noreferrer">
                https://ec.europa.eu/consumers/odr
              </a>
              . Unsere E-Mail-Adresse findest du oben im Impressum.
            </p>
          </article>

          <article class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
            <h2 class="text-lg font-semibold">8. Verbraucherstreitbeilegung</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              Wir sind nicht verpflichtet und nicht bereit, an Streitbeilegungsverfahren vor einer
              Verbraucherschlichtungsstelle teilzunehmen.
            </p>
          </article>

          <article class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
            <h2 class="text-lg font-semibold">9. Haftung für Inhalte</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              Als Diensteanbieter sind wir für eigene Inhalte auf diesen Seiten nach den allgemeinen
              Gesetzen verantwortlich. Wir übernehmen jedoch keine Gewähr für die Aktualität,
              Vollständigkeit und Richtigkeit der bereitgestellten Inhalte.
            </p>
          </article>

          <article class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
            <h2 class="text-lg font-semibold">10. Haftung für Links</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen
              Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen.
            </p>
          </article>

          <article class="rounded-2xl border border-stone-200 bg-stone-50 p-5">
            <h2 class="text-lg font-semibold">11. Urheberrecht</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">
              Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem
              deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der
              Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung
              des jeweiligen Autors bzw. Erstellers.
            </p>
          </article>
        </div>
      </div>
    </section>
  </main>
</template>

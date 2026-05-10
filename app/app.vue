<template>
  <NuxtLayout>
    <div class="flex min-h-screen flex-col">
      <div class="flex-1">
        <NuxtPage />
      </div>

      <footer class="border-t border-stone-200 bg-stone-100/80">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-3 text-xs text-stone-600 md:text-sm">
          <span>Niche Cult</span>
          <NuxtLink to="/impressum" class="font-semibold text-stone-700 underline-offset-2 hover:underline">
            Impressum
          </NuxtLink>
        </div>
      </footer>
    </div>
  </NuxtLayout>
</template>

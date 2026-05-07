<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

await navigateTo('/sets')
</script>

<template>
  <div />
</template>

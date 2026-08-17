<script setup lang="ts">
import Nav from '@/components/Nav.vue'
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <Nav />
    <main class="mx-auto max-w-5xl px-4 py-8">
      <router-view />
    </main>
  </div>
</template>
<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

import { TOKEN_KEY } from '@/api/client'

const router = useRouter()

onMounted(() => {
  window.addEventListener('medexplain:unauthorized', handleUnauthorized)
})

function handleUnauthorized() {
  localStorage.removeItem(TOKEN_KEY)
  if (router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login' })
  }
}
</script>

<template>
  <router-view />
</template>
<script setup lang="ts">
import { ref } from 'vue'

import { sendChatMessage } from '@/api/assistant'
import type { ChatMessage } from '@/types'

const messages = ref<ChatMessage[]>([])
const input = ref('')
const sending = ref(false)
const error = ref<string | null>(null)

async function send() {
  const text = input.value.trim()
  if (!text || sending.value) return

  messages.value.push({ role: 'user', content: text })
  input.value = ''
  error.value = null
  sending.value = true

  try {
    const reply = await sendChatMessage(text)
    messages.value.push({ role: 'assistant', content: reply.reply })
    if (reply.sources.length > 0) {
      const suffix = `\n\nSources: ${reply.sources.join(', ')}`
      messages.value[messages.value.length - 1].content += suffix
    }
  } catch {
    error.value = 'Unable to reach the assistant. Please try again.'
    messages.value.push({
      role: 'assistant',
      content: 'I could not answer just now. Please try again in a moment.',
    })
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="flex h-[calc(100dvh-18rem)] min-h-[24rem] flex-col space-y-4 sm:h-[calc(100dvh-14rem)]">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">AI Assistant</h1>
      <p class="mt-1 text-sm text-slate-500">
        Ask educational questions about your health data. The assistant never
        diagnoses and always defers to your clinician.
      </p>
    </div>

    <div
      class="flex-1 space-y-4 overflow-y-auto rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <div v-if="messages.length === 0" class="flex h-full items-center justify-center">
        <p class="max-w-sm text-center text-sm text-slate-500">
          Ask something like “What is HbA1c?” or “How do my recent glucose results
          compare?” Answers are educational, grounded in your own reports and
          curated medical content.
        </p>
      </div>

      <div
        v-for="(message, index) in messages"
        :key="index"
        class="flex"
        :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
      >
        <div
          class="max-w-[85%] whitespace-pre-wrap rounded-2xl px-4 py-3 text-sm"
          :class="
            message.role === 'user'
              ? 'bg-teal-600 text-white'
              : 'bg-slate-100 text-slate-800'
          "
        >
          {{ message.content }}
        </div>
      </div>

      <div v-if="sending" class="flex justify-start">
        <div class="rounded-2xl bg-slate-100 px-4 py-3 text-sm text-slate-500">
          Thinking…
        </div>
      </div>
    </div>

    <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
      {{ error }}
    </p>

    <form class="flex gap-3" @submit.prevent="send">
      <input
        v-model="input"
        type="text"
        placeholder="Ask an educational question…"
        class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
        :disabled="sending"
      />
      <button
        type="submit"
        class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="sending || !input.trim()"
      >
        Send
      </button>
    </form>
  </div>
</template>
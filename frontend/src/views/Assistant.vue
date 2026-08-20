<script setup lang="ts">
import { ref } from 'vue'

import { sendChatMessage } from '@/api/assistant'
import type { ChatMessage, HealthQueryContextItem } from '@/types'

const suggested = [
  'What changed between my last two reports?',
  'Show me how my glucose has changed over time.',
  'Which medications were active when my latest result was recorded?',
  "What's new in my health record recently?",
  'What is HbA1c?',
]

const categoryLabels: Record<string, string> = {
  fact: 'Fact',
  reference_comparison: 'Reference comparison',
  education: 'Education',
  possible_context: 'Possible context',
  question_for_professional: 'Question for a professional',
}

const messages = ref<ChatMessage[]>([])
const input = ref('')
const sending = ref(false)
const error = ref<string | null>(null)

function categoryLabel(category: string): string {
  return categoryLabels[category] ?? category
}

function formatDataUsed(message: ChatMessage): string {
  return (message.answer?.data_used ?? []).map((item) => item.label).join(', ')
}

function hasContext(items: HealthQueryContextItem[]): boolean {
  return items.length > 0
}

async function send(text = input.value) {
  const question = text.trim()
  if (!question || sending.value) return

  messages.value.push({ role: 'user', content: question })
  input.value = ''
  error.value = null
  sending.value = true

  try {
    const answer = await sendChatMessage(question)
    messages.value.push({ role: 'assistant', content: answer.summary, answer })
  } catch {
    error.value = 'Unable to reach the health intelligence service. Please try again.'
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
        Ask an educational question about your own health data. Answers are
        grounded in your reports, labs, and medications — never a diagnosis, and
        always for you to discuss with your clinician.
      </p>
    </div>

    <div
      class="flex-1 space-y-4 overflow-y-auto rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center gap-4">
        <p class="max-w-sm text-center text-sm text-slate-500">
          Ask something like “What is HbA1c?” or “How do my recent glucose results
          compare?” Answers are educational, grounded in your own reports and
          curated medical content.
        </p>
        <div class="flex flex-wrap justify-center gap-2">
          <button
            v-for="(item, index) in suggested"
            :key="index"
            type="button"
            class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600 transition-colors hover:border-teal-300 hover:bg-teal-50 hover:text-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="sending"
            @click="send(item)"
          >
            {{ item }}
          </button>
        </div>
      </div>

      <div
        v-for="(message, index) in messages"
        :key="index"
        class="flex"
        :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
      >
        <div
          v-if="message.role === 'user'"
          class="max-w-[85%] whitespace-pre-wrap rounded-2xl bg-teal-600 px-4 py-3 text-sm text-white"
        >
          {{ message.content }}
        </div>

        <div
          v-else-if="message.answer"
          class="max-w-[90%] space-y-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm"
        >
          <div>
            <p class="text-base text-slate-800">{{ message.answer.summary }}</p>
          </div>

          <div v-if="message.answer.facts.length > 0">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">What the data shows</h2>
            <ul class="mt-1.5 list-disc space-y-1 pl-5 text-slate-700">
              <li v-for="(fact, factIndex) in message.answer.facts" :key="factIndex">{{ fact }}</li>
            </ul>
          </div>

          <div v-if="message.answer.changes.length > 0">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">What changed</h2>
            <ul class="mt-1.5 list-disc space-y-1 pl-5 text-slate-700">
              <li v-for="(change, changeIndex) in message.answer.changes" :key="changeIndex">{{ change }}</li>
            </ul>
          </div>

          <div v-if="hasContext(message.answer.context)">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">In context</h2>
            <ul class="mt-1.5 space-y-2">
              <li
                v-for="(item, itemIndex) in message.answer.context"
                :key="itemIndex"
                class="rounded-lg border border-slate-100 bg-white p-3 text-slate-700"
              >
                <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-teal-600">
                  {{ categoryLabel(item.category) }}
                </span>
                {{ item.text }}
              </li>
            </ul>
          </div>

          <div v-if="message.answer.educational_explanation.length > 0">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Learn more</h2>
            <ul class="mt-1.5 list-disc space-y-1 pl-5 text-slate-700">
              <li v-for="(item, itemIndex) in message.answer.educational_explanation" :key="itemIndex">{{ item }}</li>
            </ul>
          </div>

          <div v-if="message.answer.questions_for_professional.length > 0">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Ask your clinician</h2>
            <ul class="mt-1.5 list-disc space-y-1 pl-5 text-slate-700">
              <li v-for="(item, itemIndex) in message.answer.questions_for_professional" :key="itemIndex">{{ item }}</li>
            </ul>
          </div>

          <div v-if="message.answer.data_used.length > 0">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Data used</h2>
            <p class="mt-1.5 text-slate-600">{{ formatDataUsed(message) }}</p>
          </div>

          <div v-if="message.answer.sources.length > 0">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Sources</h2>
            <ul class="mt-1.5 list-disc space-y-1 pl-5 text-slate-600">
              <li v-for="(source, sourceIndex) in message.answer.sources" :key="sourceIndex">{{ source }}</li>
            </ul>
          </div>

          <p class="border-t border-slate-100 pt-3 text-xs text-slate-500">
            {{ message.answer.disclaimer }}
          </p>
        </div>

        <div
          v-else
          class="max-w-[85%] whitespace-pre-wrap rounded-2xl bg-slate-100 px-4 py-3 text-sm text-slate-800"
        >
          {{ message.content }}
        </div>
      </div>

      <div v-if="sending" class="flex justify-start">
        <div class="rounded-2xl bg-slate-100 px-4 py-3 text-sm text-slate-500">
          Analyzing your reports and labs…
        </div>
      </div>
    </div>

    <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
      {{ error }}
    </p>

    <form class="flex gap-3" @submit.prevent="send()">
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
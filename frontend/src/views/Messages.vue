<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getConversations, getMessages, sendMessage, type Conversation, type Message, type SendMessageRequest } from '@/api/messages'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const userRole = auth.user?.role || 'patient'

const conversations = ref<Conversation[]>([])
const selectedConversation = ref<Conversation | null>(null)
const messages = ref<Message[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// Message input
const newMessage = ref('')
const sending = ref(false)

// Load conversations on mount
async function loadConversations() {
  loading.value = true
  error.value = null
  try {
    conversations.value = await getConversations(userRole)
  } catch {
    error.value = 'Failed to load conversations'
  } finally {
    loading.value = false
  }
}

// Select conversation and load messages
async function selectConversation(conversation: Conversation) {
  selectedConversation.value = conversation
  await loadMessages(conversation.user_id)
}

// Load messages for a conversation
async function loadMessages(userId: number) {
  loading.value = true
  error.value = null
  try {
    messages.value = await getMessages(userId, userRole)
  } catch {
    error.value = 'Failed to load messages'
  } finally {
    loading.value = false
  }
}

// Send message
async function sendNewMessage() {
  if (!selectedConversation.value || !newMessage.value.trim() || sending.value) return

  sending.value = true
  error.value = null

  try {
    await sendMessage({
      receiver_id: selectedConversation.value.user_id,
      content: newMessage.value,
    }, userRole)
    
    newMessage.value = ''
    await loadMessages(selectedConversation.value.user_id)
    await loadConversations()
  } catch {
    error.value = 'Failed to send message'
  } finally {
    sending.value = false
  }
}

// Format date for display
function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleString()
}

// Initialize
loadConversations()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Messages</h1>
      <p class="mt-1 text-sm text-slate-500">
        Communicate securely with your healthcare providers.
      </p>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
      <!-- Conversations List -->
      <aside>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 mb-3">Conversations</h2>
        
        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
          Loading conversations…
        </div>
        
        <div v-else-if="conversations.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
          No conversations yet.
        </div>
        
        <ul v-else class="space-y-2">
          <li v-for="conversation in conversations" :key="conversation.user_id">
            <button
              type="button"
              class="w-full rounded-lg border px-4 py-3 text-left transition-colors"
              :class="
                selectedConversation?.user_id === conversation.user_id
                  ? 'border-teal-500 bg-teal-50'
                  : 'border-slate-200 bg-white hover:border-teal-300'
              "
              @click="selectConversation(conversation)"
            >
              <div class="flex items-center justify-between">
                <p class="font-medium text-slate-800">{{ conversation.name }}</p>
                <span v-if="conversation.unread_count > 0" class="rounded-full bg-teal-600 px-2 py-0.5 text-xs text-white">
                  {{ conversation.unread_count }}
                </span>
              </div>
              <p class="truncate text-xs text-slate-500">{{ conversation.email }}</p>
              <p class="mt-1 truncate text-xs text-slate-400">
                {{ conversation.last_message || 'No messages yet' }}
              </p>
            </button>
          </li>
        </ul>
      </aside>

      <!-- Messages View -->
      <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div v-if="!selectedConversation" class="p-8 text-center text-sm text-slate-500">
          Select a conversation to view messages.
        </div>
        
        <div v-else class="flex h-[600px] flex-col">
          <!-- Conversation Header -->
          <div class="border-b border-slate-100 p-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ selectedConversation.name }}</h2>
            <p class="text-sm text-slate-500">{{ selectedConversation.email }}</p>
          </div>

          <!-- Messages List -->
          <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <div v-if="loading" class="text-sm text-slate-500">
              Loading messages…
            </div>
            
            <div v-else-if="messages.length === 0" class="text-sm text-slate-500">
              No messages yet. Start the conversation!
            </div>
            
            <div v-else class="space-y-3">
              <div
                v-for="message in messages"
                :key="message.id"
                class="max-w-[70%] rounded-lg p-3"
                :class="message.sender_name === 'You' ? 'bg-teal-600 text-white ml-auto' : 'bg-slate-100 text-slate-800'"
              >
                <p class="text-sm">{{ message.content }}</p>
                <p class="mt-1 text-xs opacity-70">
                  {{ formatDate(message.created_at) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Message Input -->
          <div class="border-t border-slate-100 p-4">
            <form @submit.prevent="sendNewMessage" class="flex gap-3">
              <input
                v-model="newMessage"
                type="text"
                placeholder="Type a message..."
                class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
              />
              <button
                type="submit"
                class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="sending || !newMessage.trim()"
              >
                {{ sending ? 'Sending…' : 'Send' }}
              </button>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

import { apiClient } from './client'

export interface Message {
  id: number
  sender_id: number
  sender_name: string
  receiver_id: number
  content: string
  is_read: boolean
  read_at: string | null
  created_at: string
}

export interface Conversation {
  user_id: number
  name: string
  email: string
  role: string
  last_message: string | null
  last_message_at: string | null
  unread_count: number
}

export interface SendMessageRequest {
  receiver_id: number
  content: string
}

export async function getConversations(role: string = 'patient'): Promise<Conversation[]> {
  const prefix = role === 'clinician' ? 'clinician' : 'patient'
  const { data } = await apiClient.get<{ success: boolean; data: Conversation[] }>(`/${prefix}/messages/conversations`)
  return data.data
}

export async function getMessages(userId: number, role: string = 'patient'): Promise<Message[]> {
  const prefix = role === 'clinician' ? 'clinician' : 'patient'
  const { data } = await apiClient.get<{ success: boolean; data: Message[] }>(`/${prefix}/messages/${userId}`)
  return data.data
}

export async function sendMessage(request: SendMessageRequest, role: string = 'patient'): Promise<Message> {
  const prefix = role === 'clinician' ? 'clinician' : 'patient'
  const { data } = await apiClient.post<{ success: boolean; data: Message }>(`/${prefix}/messages`, request)
  return data.data
}

export async function markAsRead(messageId: number, role: string = 'patient'): Promise<Message> {
  const prefix = role === 'clinician' ? 'clinician' : 'patient'
  const { data } = await apiClient.post<{ success: boolean; data: Message }>(`/${prefix}/messages/${messageId}/read`)
  return data.data
}

// Clinician messaging functions
export async function getClinicianConversations(): Promise<Conversation[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Conversation[] }>('/clinician/messages/conversations')
  return data.data
}

export async function getClinicianMessages(userId: number): Promise<Message[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Message[] }>(`/clinician/messages/${userId}`)
  return data.data
}

export async function sendClinicianMessage(request: SendMessageRequest): Promise<Message> {
  const { data } = await apiClient.post<{ success: boolean; data: Message }>('/clinician/messages', request)
  return data.data
}

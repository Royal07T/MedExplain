import type { AuthPayload, User } from '@/types'

import { apiClient } from './client'

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterPayload extends LoginCredentials {
  name: string
  password_confirmation: string
}

export async function login(credentials: LoginCredentials): Promise<AuthPayload> {
  const { data } = await apiClient.post<AuthPayload>('/auth/login', credentials)
  return data
}

export async function register(payload: RegisterPayload): Promise<AuthPayload> {
  const { data } = await apiClient.post<AuthPayload>('/auth/register', payload)
  return data
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout')
}

export async function fetchCurrentUser(): Promise<User> {
  const { data } = await apiClient.get<{ user: User }>('/user')
  return data.user
}
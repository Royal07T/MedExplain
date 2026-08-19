import type { AuthPayload, User } from '@/types'

import { apiClient } from './client'

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterPayload extends LoginCredentials {
  name: string
  first_name: string
  last_name: string
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

export interface UpdateProfilePayload {
  name: string
  first_name: string | null
  last_name: string | null
  date_of_birth: string | null
  gender: string | null
}

export async function updateProfile(payload: UpdateProfilePayload): Promise<User> {
  const { data } = await apiClient.put<{ user: User }>('/user', payload)
  return data.user
}

export async function updateAvatar(file: File): Promise<User> {
  const formData = new FormData()
  formData.append('avatar', file)
  const { data } = await apiClient.post<{ user: User }>('/user/avatar', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.user
}

export async function resendVerificationEmail(): Promise<void> {
  await apiClient.post('/auth/email/verification-notification')
}
import type { User } from '@/types'

import { apiClient } from './client'

export interface PlanInfo {
  plan: 'free' | 'pro'
  label: string
  is_pro: boolean
}

export async function getCurrentPlan(): Promise<PlanInfo> {
  const { data } = await apiClient.get<{ data: PlanInfo }>('/plan')
  return data.data
}

export async function upgradePlan(): Promise<User> {
  const { data } = await apiClient.post<{ user: User }>('/plan/upgrade')
  return data.user
}

export async function cancelPlan(): Promise<User> {
  const { data } = await apiClient.post<{ user: User }>('/plan/cancel')
  return data.user
}
import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface SettingDefinition {
  key: string
  groupe: string
  type: 'number' | 'select'
  options?: (string | number)[]
  default: string | number
  label_fr: string
  label_en: string
  value: string | number
}

export async function fetchSettings(): Promise<SettingDefinition[]> {
  const { data } = await http.get<ApiResponse<SettingDefinition[]>>('/settings')
  return data.data
}

export async function updateSettings(settings: Record<string, string | number>): Promise<void> {
  await http.put('/settings', { settings })
}

export interface EcoleProfile {
  id: number
  name: string
  code: string
  address: string | null
  phone: string | null
  email: string | null
  header_fr: string | null
  header_en: string | null
  niveau_ids: number[]
}

export interface EcoleProfilePayload {
  name: string
  address?: string | null
  phone?: string | null
  email?: string | null
  header_fr?: string | null
  header_en?: string | null
  niveau_ids: number[]
}

export async function fetchEcole(): Promise<EcoleProfile> {
  const { data } = await http.get<ApiResponse<EcoleProfile>>('/ecole')
  return data.data
}

export async function updateEcole(payload: EcoleProfilePayload): Promise<EcoleProfile> {
  const { data } = await http.put<ApiResponse<EcoleProfile>>('/ecole', payload)
  return data.data
}

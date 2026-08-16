import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface SettingDefinition {
  key: string
  groupe: string
  type: 'number' | 'select' | 'text' | 'richtext'
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
  type: 'maternelle' | 'primaire' | 'secondaire'
  header_fr: string | null
  header_en: string | null
  logo_url: string | null
  cachet_url: string | null
  signature_url: string | null
}

export type ImageEcole = 'logo' | 'cachet' | 'signature'

export interface EcoleProfilePayload {
  name: string
  address?: string | null
  phone?: string | null
  email?: string | null
  header_fr?: string | null
  header_en?: string | null
}

export async function fetchEcole(): Promise<EcoleProfile> {
  const { data } = await http.get<ApiResponse<EcoleProfile>>('/ecole')
  return data.data
}

export async function updateEcole(payload: EcoleProfilePayload): Promise<EcoleProfile> {
  const { data } = await http.put<ApiResponse<EcoleProfile>>('/ecole', payload)
  return data.data
}

export async function uploadImageEcole(type: ImageEcole, file: File): Promise<EcoleProfile> {
  const formData = new FormData()
  formData.append('image', file)
  const { data } = await http.post<ApiResponse<EcoleProfile>>(`/ecole/images/${type}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function supprimerImageEcole(type: ImageEcole): Promise<EcoleProfile> {
  const { data } = await http.delete<ApiResponse<EcoleProfile>>(`/ecole/images/${type}`)
  return data.data
}

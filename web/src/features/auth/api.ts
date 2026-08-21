import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import type { AuthUser } from '@/shared/store/authStore'

interface LoginPayload {
  identifiant: string
  password: string
}

interface LoginResult {
  user: AuthUser
  token: string
}

export async function login(payload: LoginPayload): Promise<LoginResult> {
  const { data } = await http.post<ApiResponse<LoginResult>>('/auth/login', payload)
  return data.data
}

export async function fetchMe(): Promise<AuthUser> {
  const { data } = await http.get<ApiResponse<AuthUser>>('/auth/me')
  return data.data
}

export async function logout(): Promise<void> {
  await http.post('/auth/logout')
}

export async function changerMotDePasse(payload: {
  ancien_mot_de_passe: string
  nouveau_mot_de_passe: string
  nouveau_mot_de_passe_confirmation: string
}): Promise<AuthUser> {
  const { data } = await http.post<ApiResponse<AuthUser>>('/auth/mot-de-passe', payload)
  return data.data
}

export interface ProfilPayload {
  name: string
  email: string
  phone?: string | null
}

export async function updateProfil(payload: ProfilPayload): Promise<AuthUser> {
  const { data } = await http.put<ApiResponse<AuthUser>>('/auth/profil', payload)
  return data.data
}

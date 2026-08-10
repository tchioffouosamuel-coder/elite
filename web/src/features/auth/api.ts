import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import type { AuthUser } from '@/shared/store/authStore'

interface LoginPayload {
  email: string
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

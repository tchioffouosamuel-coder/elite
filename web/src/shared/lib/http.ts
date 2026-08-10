import axios from 'axios'
import type { AxiosError } from 'axios'
import { useAuthStore } from '@/shared/store/authStore'
import { useUiStore } from '@/shared/store/uiStore'
import type { ApiError } from '@/shared/types/api'

export const API_BASE_URL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api/v1'

export const http = axios.create({
  baseURL: API_BASE_URL,
  headers: { Accept: 'application/json' },
})

http.interceptors.request.use((config) => {
  const { token, user } = useAuthStore.getState()
  const { locale } = useUiStore.getState()

  if (token) config.headers.Authorization = `Bearer ${token}`
  if (user?.school_id) config.headers['X-School-Id'] = String(user.school_id)
  config.headers['X-Locale'] = locale

  return config
})

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    // Un jeton Sanctum expiré ne peut pas être "rafraîchi" silencieusement
    // (il n'y a pas de refresh-token séparé) : on ferme simplement la session.
    if (error.response?.status === 401) {
      useAuthStore.getState().clearSession()
      if (!window.location.pathname.startsWith('/connexion')) {
        window.location.href = '/connexion'
      }
    }

    const apiError: ApiError = {
      message: error.response?.data?.message ?? error.message,
      status: error.response?.status ?? 0,
      errors: error.response?.data?.errors ?? null,
    }

    return Promise.reject(apiError)
  },
)

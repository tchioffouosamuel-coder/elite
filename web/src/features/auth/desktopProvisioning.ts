import axios from 'axios'
import { http } from '@/shared/lib/http'
import type { AuthUser } from '@/shared/store/authStore'
import type { ApiResponse } from '@/shared/types/api'

interface SessionDesktop {
  token: string
  user: AuthUser
}

/**
 * Interroge l'instance Laravel locale : ce poste est-il déjà lié à un
 * compte (cf. DesktopProvisioningController::session()) ? `null` si aucun
 * poste n'a encore été provisionné — pas une erreur, le premier lancement
 * normal d'une installation neuve.
 */
export async function authentifierSessionDesktop(): Promise<SessionDesktop | null> {
  let token: string
  try {
    const reponse = await http.get<ApiResponse<{ token: string }>>('/desktop/session')
    token = reponse.data.data.token
  } catch (err) {
    if (axios.isAxiosError(err) && err.response?.status === 404) return null
    throw err
  }

  // `session()` ne renvoie qu'un jeton et le compte brut : le profil complet
  // (rôles, privilèges, écoles accessibles) vient de `/auth/me`, comme pour
  // toute connexion — c'est lui que consomme le reste de l'application.
  const { data } = await http.get<ApiResponse<AuthUser>>('/auth/me', {
    headers: { Authorization: `Bearer ${token}` },
  })

  return { token, user: data.data }
}

/**
 * Premier lancement d'un poste desktop : authentifie l'utilisateur sur le
 * serveur distant de son établissement, puis lie ce poste à son compte en
 * transmettant les jetons obtenus à l'instance locale (qui en profite pour
 * tirer un premier jeu de données — cf. DesktopProvisioningController::provisionner()).
 */
export async function provisionnerPoste(params: {
  serveurUrl: string
  identifiant: string
  password: string
}): Promise<SessionDesktop> {
  const baseUrl = `${params.serveurUrl.replace(/\/+$/, '')}/api/v1`
  const distant = axios.create({ baseURL: baseUrl, headers: { Accept: 'application/json' } })

  const { data } = await distant.post<
    ApiResponse<{ user: AuthUser; token: string; refresh_token: string }>
  >('/auth/login', { identifiant: params.identifiant, password: params.password })

  const { user, token, refresh_token: refreshToken } = data.data
  const ecole = user.ecoles_accessibles?.find((e) => e.id === user.school_id) ?? null

  await http.post('/desktop/provisionner', {
    serveur_url: params.serveurUrl,
    token,
    refresh_token: refreshToken,
    user: {
      id: user.id,
      name: user.name,
      email: user.email,
      phone: user.phone,
      school_id: user.school_id,
      roles: user.roles,
      permissions: user.permissions,
    },
    school: ecole ? { name: ecole.name, code: ecole.code, type: ecole.type } : undefined,
  })

  const session = await authentifierSessionDesktop()
  if (!session) throw new Error('Le poste vient d’être provisionné mais aucune session locale n’a pu être ouverte.')

  return session
}

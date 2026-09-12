import axios from 'axios'
import { http } from '@/shared/lib/http'
import type { AuthUser } from '@/shared/store/authStore'
import type { ApiResponse } from '@/shared/types/api'

interface SessionDesktop {
  token: string
  user: AuthUser
  /**
   * Faux tant que ce compte n'a pas, au moins une fois, intégralement
   * répliqué ses données depuis le provisioning (cf.
   * `DesktopProvisioningController::connexion()` et `SyncPull::handle()`) —
   * un jeton valide ne suffit pas à entrer dans l'application : un premier
   * clonage interrompu (réseau coupé, application fermée en plein milieu)
   * laisserait sinon le poste « déjà lié », avec un mot de passe local
   * valide, mais sans la moindre donnée réellement disponible hors-ligne.
   * `LoginPage` doit forcer `PremiereSynchronisationModal` tant que ce
   * drapeau reste faux, quel que soit le chemin qui a mené à cette session
   * (première liaison ou reconnexion locale).
   */
  clonageInitialComplet: boolean
}

/**
 * Connexion locale : ce compte a-t-il déjà été lié à ce poste (cf.
 * DesktopProvisioningController::connexion()) ? `null` si ce couple
 * identifiant/mot de passe ne correspond à aucun compte provisionné ICI —
 * pas forcément une erreur, ça peut être le tout premier lancement de ce
 * compte sur ce poste (plusieurs comptes pouvant s'y relayer), auquel cas
 * `lierPoste()` prend le relais.
 */
export async function connecterSessionDesktop(params: {
  identifiant: string
  password: string
}): Promise<SessionDesktop | null> {
  let token: string
  let clonageInitialComplet: boolean
  try {
    const reponse = await http.post<ApiResponse<{ token: string; clonage_initial_complet: boolean }>>(
      '/desktop/connexion',
      params,
    )
    token = reponse.data.data.token
    clonageInitialComplet = reponse.data.data.clonage_initial_complet
  } catch (err) {
    if (axios.isAxiosError(err) && (err.response?.status === 404 || err.response?.status === 401)) return null
    throw err
  }

  // `connexion()` ne renvoie qu'un jeton et le compte brut : le profil complet
  // (rôles, privilèges, écoles accessibles) vient de `/auth/me`, comme pour
  // toute connexion — c'est lui que consomme le reste de l'application.
  const { data } = await http.get<ApiResponse<AuthUser>>('/auth/me', {
    headers: { Authorization: `Bearer ${token}` },
  })

  return { token, user: data.data, clonageInitialComplet }
}

/**
 * Première connexion de CE compte sur CE poste : authentifie l'utilisateur
 * sur le serveur distant de son établissement, puis lie ce poste à son
 * compte en transmettant les jetons obtenus (et le mot de passe qui vient de
 * servir, pour permettre une reconnexion locale future — cf.
 * `connecterSessionDesktop`) à l'instance locale.
 *
 * Ne déclenche PLUS elle-même le premier clonage complet des données :
 * `POST /desktop/provisionner` ne fait plus que créer la ligne
 * `desktop_provisioning` (cf. `DesktopProvisioningController::provisionner()`)
 * et répond en un instant. C'est à l'appelant (`LoginPage`) de lancer
 * ensuite `window.desktop.runInitialSync()` — via
 * `PremiereSynchronisationModal` — avant d'ouvrir une session locale : lancer
 * ce clonage ICI, dans la même requête HTTP que le provisioning, bloquait
 * l'écran de connexion sans le moindre retour visuel pendant toute sa durée
 * (plusieurs minutes sur un grand établissement, avec les photos) — ce qui
 * se lisait, pour l'utilisateur, comme une connexion qui ne « passe » pas.
 *
 * Un poste desktop accueille plusieurs comptes : provisionner un second
 * compte n'efface pas le premier.
 */
export async function lierPoste(params: {
  serveurUrl: string
  identifiant: string
  password: string
}): Promise<void> {
  const baseUrl = `${params.serveurUrl.replace(/\/+$/, '')}/api/v1`
  const distant = axios.create({ baseURL: baseUrl, headers: { Accept: 'application/json' } })

  const { data } = await distant.post<
    ApiResponse<{ user: AuthUser; token: string; refresh_token: string }>
  >('/auth/login', { identifiant: params.identifiant, password: params.password })

  const { user, token, refresh_token: refreshToken } = data.data

  // Toutes les écoles accessibles au compte, pas la seule `school_id` du
  // profil : un compte non borné à une seule école (super admin d'un
  // complexe, ou compte de direction transverse) doit pouvoir répliquer
  // chacune d'elles sur ce poste.
  const ecoles = user.ecoles_accessibles ?? []

  await http.post('/desktop/provisionner', {
    serveur_url: params.serveurUrl,
    token,
    refresh_token: refreshToken,
    password: params.password,
    user: {
      id: user.id,
      name: user.name,
      email: user.email,
      phone: user.phone,
      school_id: user.school_id,
      roles: user.roles,
      permissions: user.permissions,
    },
    schools: ecoles.map((e) => ({ id: e.id, name: e.name, code: e.code, type: e.type })),
  })
}

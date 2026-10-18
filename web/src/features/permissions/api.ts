import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface Privilege {
  code: string
  libelle: string
}

export interface ModulePrivileges {
  code: string
  libelle: string
  permissions: Privilege[]
}

export interface FonctionPrivileges {
  id: number
  label_fr: string
  label_en: string | null
  label: string
  personnels_count: number
  permissions: string[]
}

export interface MatricePermissions {
  modules: ModulePrivileges[]
  fonctions: FonctionPrivileges[]
}

export async function fetchMatricePermissions(): Promise<MatricePermissions> {
  const { data } = await http.get<ApiResponse<MatricePermissions>>('/permissions')
  return data.data
}

export async function updatePermissionsFonction(
  fonctionId: number,
  permissions: string[],
): Promise<{ message: string; permissions: string[] }> {
  const { data } = await http.put<ApiResponse<{ id: number; permissions: string[] }>>(
    `/fonctions-referentiel/${fonctionId}/permissions`,
    { permissions },
  )

  return { message: data.message, permissions: data.data.permissions }
}

// ----------------------------------------------- Privilèges individuels

export interface PrivilegesUtilisateur {
  personnel_id: number
  nom_complet: string
  modules: ModulePrivileges[]
  /** Hérités de la fonction de l'agent — non modifiables depuis cet écran. */
  permissions_fonction: string[]
  /** Accordés en propre à ce compte, en plus de la fonction. */
  permissions_directes: string[]
}

export async function fetchPermissionsUtilisateur(personnelId: number): Promise<PrivilegesUtilisateur> {
  const { data } = await http.get<ApiResponse<PrivilegesUtilisateur>>(`/personnels/${personnelId}/permissions`)
  return data.data
}

export async function updatePermissionsUtilisateur(
  personnelId: number,
  permissions: string[],
): Promise<{ message: string; permissions: string[] }> {
  const { data } = await http.put<ApiResponse<{ personnel_id: number; permissions_directes: string[] }>>(
    `/personnels/${personnelId}/permissions`,
    { permissions },
  )

  return { message: data.message, permissions: data.data.permissions_directes }
}

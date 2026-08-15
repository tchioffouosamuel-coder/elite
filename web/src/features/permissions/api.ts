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

import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'

export interface MatriculeNationalLigne {
  id: number
  matricule: string | null
  matricule_national: string | null
  nom_complet: string
  classe: string | null
  school: string | null
}

export async function fetchMatriculesNationaux(search = ''): Promise<MatriculeNationalLigne[]> {
  const { data } = await http.get<ApiResponse<MatriculeNationalLigne[]>>('/matricules-nationaux', {
    params: { search: search || undefined, per_page: 2000 },
  })
  return data.data
}

/** `matriculeNational` vide efface le matricule national de l'élève. */
export async function majMatriculeNational(id: number, matriculeNational: string): Promise<MatriculeNationalLigne> {
  const { data } = await http.put<ApiResponse<{ id: number; matricule_national: string | null }>>(
    `/matricules-nationaux/${id}`,
    { matricule_national: matriculeNational },
  )
  return data.data as MatriculeNationalLigne
}

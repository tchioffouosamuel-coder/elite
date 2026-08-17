import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import {
  fetchNiveauxScolaires,
  createNiveauScolaire,
  updateNiveauScolaire,
  deleteNiveauScolaire,
} from '@/features/primaire/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

/**
 * Niveaux d'enseignement du primaire et de la maternelle. Chaque niveau est
 * confié à un animateur : c'est l'équivalent du département et de son chef au
 * secondaire, mais rattaché aux classes d'un même degré (SIL, CP, CE1…).
 */
export function NiveauxScolairesPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const { data, isLoading, isError } = useQuery({ queryKey: ['niveaux-scolaires'], queryFn: fetchNiveauxScolaires })
  const { data: personnels } = useQuery({
    queryKey: ['personnels', { page: 1, per_page: 100 }],
    queryFn: () => fetchPersonnels({ per_page: 100 }),
  })

  const [code, setCode] = useState('')
  const [libelle, setLibelle] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['niveaux-scolaires'] })

  const handleAdd = async () => {
    if (!code.trim() || !libelle.trim()) return
    setSubmitting(true)
    setErreur(null)
    try {
      await createNiveauScolaire({
        code: code.trim(),
        libelle: libelle.trim(),
        ordre: (data?.length ?? 0) + 1,
      })
      setCode('')
      setLibelle('')
      invalider()
    } catch (err) {
      setErreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  const handleAnimateur = async (niveauId: number, animateurId: string) => {
    const niveau = data?.find((n) => n.id === niveauId)
    if (!niveau) return

    setErreur(null)
    try {
      await updateNiveauScolaire(niveauId, {
        code: niveau.code,
        libelle: niveau.libelle,
        ordre: niveau.ordre,
        animateur_personnel_id: animateurId ? Number(animateurId) : null,
      })
      invalider()
    } catch (err) {
      setErreur((err as ApiError).message)
    }
  }

  const handleDelete = async (niveauId: number) => {
    setErreur(null)
    try {
      await deleteNiveauScolaire(niveauId)
      invalider()
    } catch (err) {
      setErreur((err as ApiError).message)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="font-display text-2xl font-semibold text-navy-900">{t('niveaux.title')}</h1>
        <p className="mt-1 text-sm text-navy-400">{t('niveaux.hint')}</p>
      </div>

      {can('pedagogie.manage') && (
        <div className="flex max-w-2xl flex-wrap items-end gap-2">
          <Input
            label={t('niveaux.code')}
            value={code}
            onChange={(e) => setCode(e.target.value)}
            placeholder="CE1"
            className="w-32"
          />
          <Input
            label={t('niveaux.libelle')}
            value={libelle}
            onChange={(e) => setLibelle(e.target.value)}
            placeholder="Cours Élémentaire 1"
            className="min-w-56 flex-1"
          />
          <Button onClick={handleAdd} disabled={submitting}>
            <Plus className="h-4 w-4" />
            {t('common.add')}
          </Button>
        </div>
      )}

      {erreur && <p className="text-sm text-red-500">{erreur}</p>}

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState label={t('niveaux.empty')} />
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>{t('niveaux.code')}</Th>
              <Th>{t('niveaux.libelle')}</Th>
              <Th>{t('niveaux.classes')}</Th>
              <Th>{t('niveaux.animateur')}</Th>
              {can('pedagogie.manage') && <Th>{t('common.actions')}</Th>}
            </tr>
          </Thead>
          <tbody>
            {data.map((niveau) => (
              <Tr key={niveau.id}>
                <Td className="font-mono text-xs font-semibold">{niveau.code}</Td>
                <Td className="font-medium">{niveau.libelle}</Td>
                <Td>{niveau.nb_classes ?? 0}</Td>
                <Td>
                  {can('pedagogie.manage') ? (
                    <select
                      value={niveau.animateur_personnel_id ?? ''}
                      onChange={(e) => handleAnimateur(niveau.id, e.target.value)}
                      className="w-full max-w-56 rounded-lg border border-navy-200 bg-white px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
                    >
                      <option value="">—</option>
                      {personnels?.map((p) => (
                        <option key={p.id} value={p.id}>
                          {p.nom_complet}
                        </option>
                      ))}
                    </select>
                  ) : (
                    (niveau.animateur?.nom_complet ?? '—')
                  )}
                </Td>
                {can('pedagogie.manage') && (
                  <Td>
                    <button
                      onClick={() => handleDelete(niveau.id)}
                      title={t('common.delete')}
                      className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </Td>
                )}
              </Tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}

import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, CheckCircle2, Pencil, CalendarPlus } from 'lucide-react'
import {
  fetchAnneesScolaires,
  activerAnneeScolaire,
  genererSeancesAnnee,
  fetchTrimestresAll,
  activerTrimestre,
  genererSeancesTrimestre,
  type AnneeScolaire,
} from '@/features/session/api'
import type { Trimestre } from '@/features/pedagogie/api'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { AnneeScolaireFormModal } from '@/features/session/pages/AnneeScolaireFormModal'
import { TrimestreFormModal } from '@/features/session/pages/TrimestreFormModal'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

export function SessionPage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const { data: annees, isLoading: loadingAnnees } = useQuery({ queryKey: ['annees-scolaires'], queryFn: fetchAnneesScolaires })
  const { data: trimestres, isLoading: loadingTrimestres } = useQuery({ queryKey: ['trimestres-all'], queryFn: fetchTrimestresAll })

  const [showAnneeForm, setShowAnneeForm] = useState(false)
  const [showTrimestreForm, setShowTrimestreForm] = useState(false)
  const [editingAnnee, setEditingAnnee] = useState<AnneeScolaire | null>(null)
  const [editingTrimestre, setEditingTrimestre] = useState<Trimestre | null>(null)
  const [selectedAnneeId, setSelectedAnneeId] = useState<number | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)

  useEffect(() => {
    if (selectedAnneeId !== null || !annees) return
    const active = annees.find((a) => a.is_active) ?? annees[0]
    if (active) setSelectedAnneeId(active.id)
  }, [annees, selectedAnneeId])

  const invalidateAnnees = () => queryClient.invalidateQueries({ queryKey: ['annees-scolaires'] })
  const invalidateTrimestres = () => queryClient.invalidateQueries({ queryKey: ['trimestres-all'] })

  // Activer bascule la période de référence de tout l'établissement : notes,
  // bulletins et absences suivent. D'où la confirmation explicite.
  const handleActivateAnnee = async (id: number, libelle: string) => {
    const confirme = await confirmer({
      titre: `Activer l'année ${libelle} ?`,
      message: "Elle devient l'année de référence de l'établissement. L'année active actuelle est désactivée.",
      action: 'Activer',
      destructif: false,
    })
    if (!confirme) return

    setBusyId(id)
    try {
      await activerAnneeScolaire(id)
      invalidateAnnees()
      succes(t('session.annee_activated', { libelle }))
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setBusyId(null)
    }
  }

  const handleActivateTrimestre = async (id: number, libelle: string) => {
    const confirme = await confirmer({
      titre: `Activer le ${libelle} ?`,
      message: 'Les saisies de notes et les bulletins porteront désormais sur ce trimestre.',
      action: 'Activer',
      destructif: false,
    })
    if (!confirme) return

    setBusyId(id)
    try {
      await activerTrimestre(id)
      invalidateTrimestres()
      succes(t('session.trimestre_activated', { libelle }))
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setBusyId(null)
    }
  }

  // Crée les séances de toutes les classes accessibles à l'agent, depuis leur
  // emploi du temps — pas besoin de rouvrir chaque classe une par une.
  const handleGenererSeancesAnnee = async (id: number, libelle: string) => {
    const confirme = await confirmer({
      titre: t('session.generer_seances_confirm_title', { libelle }),
      message: t('session.generer_seances_confirm_message'),
      action: t('session.generer_seances'),
      destructif: false,
    })
    if (!confirme) return

    setBusyId(id)
    try {
      const resultat = await genererSeancesAnnee(id)
      succes(t('session.seances_generees', resultat))
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setBusyId(null)
    }
  }

  const handleGenererSeancesTrimestre = async (id: number, libelle: string) => {
    const confirme = await confirmer({
      titre: t('session.generer_seances_confirm_title', { libelle }),
      message: t('session.generer_seances_confirm_message'),
      action: t('session.generer_seances'),
      destructif: false,
    })
    if (!confirme) return

    setBusyId(id)
    try {
      const resultat = await genererSeancesTrimestre(id)
      succes(t('session.seances_generees', resultat))
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setBusyId(null)
    }
  }

  const selectedAnnee = annees?.find((a) => a.id === selectedAnneeId) ?? null
  const trimestresAnnee = (trimestres ?? [])
    .filter((tr) => tr.annee_scolaire_id === selectedAnneeId)
    .sort((a, b) => a.ordre - b.ordre)
  const prochainOrdre = (trimestresAnnee.at(-1)?.ordre ?? 0) + 1

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-semibold text-navy-900">{t('session.title')}</h1>
      </div>

      <Card>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="font-display text-base font-bold tracking-tight text-navy-800">{t('session.annees')}</h2>
          <Button size="sm" onClick={() => setShowAnneeForm(true)}>
            <Plus className="h-4 w-4" />
            {t('session.add_annee')}
          </Button>
        </div>

        {loadingAnnees ? (
          <Spinner />
        ) : !annees || annees.length === 0 ? (
          <EmptyState />
        ) : (
          <Table>
            <Thead>
              <tr>
                <Th>{t('session.libelle')}</Th>
                <Th>{t('session.date_debut')}</Th>
                <Th>{t('session.date_fin')}</Th>
                <Th>{t('common.actions')}</Th>
              </tr>
            </Thead>
            <tbody>
              {annees.map((a) => (
                <Tr
                  key={a.id}
                  className={a.id === selectedAnneeId ? 'bg-cream-50' : undefined}
                  onClick={() => setSelectedAnneeId(a.id)}
                >
                  <Td className="cursor-pointer font-medium">
                    <div className="flex items-center gap-2">
                      {a.libelle}
                      {a.is_active && <Badge tone="green">{t('session.active')}</Badge>}
                    </div>
                  </Td>
                  <Td>{a.date_debut}</Td>
                  <Td>{a.date_fin}</Td>
                  <Td>
                    <div className="flex items-center gap-1">
                      <button
                        onClick={(e) => {
                          e.stopPropagation()
                          setEditingAnnee(a)
                        }}
                        className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
                      >
                        <Pencil className="h-3.5 w-3.5" />
                        {t('common.edit')}
                      </button>
                      <button
                        onClick={(e) => {
                          e.stopPropagation()
                          handleGenererSeancesAnnee(a.id, a.libelle)
                        }}
                        disabled={busyId === a.id}
                        className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
                      >
                        <CalendarPlus className="h-3.5 w-3.5" />
                        {t('session.generer_seances')}
                      </button>
                      {!a.is_active && (
                        <button
                          onClick={(e) => {
                            e.stopPropagation()
                            handleActivateAnnee(a.id, a.libelle)
                          }}
                          disabled={busyId === a.id}
                          className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
                        >
                          <CheckCircle2 className="h-3.5 w-3.5" />
                          {t('session.activate')}
                        </button>
                      )}
                    </div>
                  </Td>
                </Tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>

      <Card>
        <div className="mb-4 flex items-center justify-between">
          <h2 className="font-display text-base font-bold tracking-tight text-navy-800">
            {t('session.trimestres')} {selectedAnnee ? `— ${selectedAnnee.libelle}` : ''}
          </h2>
          {selectedAnnee && (
            <Button size="sm" onClick={() => setShowTrimestreForm(true)}>
              <Plus className="h-4 w-4" />
              {t('session.add_trimestre')}
            </Button>
          )}
        </div>

        {!selectedAnnee ? (
          <EmptyState label={t('session.select_annee')} />
        ) : loadingTrimestres ? (
          <Spinner />
        ) : trimestresAnnee.length === 0 ? (
          <EmptyState label={t('session.no_trimestres')} />
        ) : (
          <Table>
            <Thead>
              <tr>
                <Th>{t('session.ordre')}</Th>
                <Th>{t('session.libelle')}</Th>
                <Th>{t('session.date_debut')}</Th>
                <Th>{t('session.date_fin')}</Th>
                <Th>{t('common.actions')}</Th>
              </tr>
            </Thead>
            <tbody>
              {trimestresAnnee.map((tr) => (
                <Tr key={tr.id}>
                  <Td>{tr.ordre}</Td>
                  <Td className="font-medium">
                    <div className="flex items-center gap-2">
                      {tr.libelle}
                      {tr.is_active && <Badge tone="green">{t('session.active')}</Badge>}
                    </div>
                  </Td>
                  <Td>{tr.date_debut}</Td>
                  <Td>{tr.date_fin}</Td>
                  <Td>
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => setEditingTrimestre(tr)}
                        className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
                      >
                        <Pencil className="h-3.5 w-3.5" />
                        {t('common.edit')}
                      </button>
                      <button
                        onClick={() => handleGenererSeancesTrimestre(tr.id, tr.libelle)}
                        disabled={busyId === tr.id}
                        className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
                      >
                        <CalendarPlus className="h-3.5 w-3.5" />
                        {t('session.generer_seances')}
                      </button>
                      {!tr.is_active && (
                        <button
                          onClick={() => handleActivateTrimestre(tr.id, tr.libelle)}
                          disabled={busyId === tr.id}
                          className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-cream-100"
                        >
                          <CheckCircle2 className="h-3.5 w-3.5" />
                          {t('session.activate')}
                        </button>
                      )}
                    </div>
                  </Td>
                </Tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>

      {showAnneeForm && (
        <AnneeScolaireFormModal
          onClose={() => setShowAnneeForm(false)}
          onCreated={() => {
            setShowAnneeForm(false)
            invalidateAnnees()
          }}
        />
      )}
      {editingAnnee && (
        <AnneeScolaireFormModal
          edition={editingAnnee}
          onClose={() => setEditingAnnee(null)}
          onCreated={() => {
            setEditingAnnee(null)
            invalidateAnnees()
          }}
        />
      )}
      {showTrimestreForm && selectedAnnee && (
        <TrimestreFormModal
          anneeScolaireId={selectedAnnee.id}
          prochainOrdre={prochainOrdre}
          onClose={() => setShowTrimestreForm(false)}
          onCreated={() => {
            setShowTrimestreForm(false)
            invalidateTrimestres()
          }}
        />
      )}
      {editingTrimestre && (
        <TrimestreFormModal
          anneeScolaireId={editingTrimestre.annee_scolaire_id}
          prochainOrdre={editingTrimestre.ordre}
          edition={editingTrimestre}
          onClose={() => setEditingTrimestre(null)}
          onCreated={() => {
            setEditingTrimestre(null)
            invalidateTrimestres()
          }}
        />
      )}
    </div>
  )
}

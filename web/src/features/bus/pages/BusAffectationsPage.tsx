import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Bus, MapPin, Trash2, UserPlus } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import {
  fetchElevesTransport,
  retirerAffectation,
  type EleveTransport,
} from '@/features/bus/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TONE_PAIEMENT: Record<string, 'green' | 'gold' | 'red' | 'neutral'> = {
  solde: 'green',
  partiel: 'gold',
  impaye: 'red',
  sans_frais: 'neutral',
}

/**
 * Tous les élèves de l'école, souscription bus incluse — le point d'entrée
 * unique pour savoir qui est éligible au transport, sur quel trajet, et si
 * c'est réglé. Souscrire ne s'y fait plus au hasard d'un trajet précis :
 * on part de l'élève, on choisit le trajet ensuite.
 */
export function BusAffectationsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [classeFiltre, setClasseFiltre] = useState<number | ''>('')
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  const { data: classes } = useQuery({ queryKey: ['classes', 'select'], queryFn: () => fetchClasses() })
  const { data: eleves, isLoading } = useQuery({
    queryKey: ['bus-eleves', classeFiltre],
    queryFn: () => fetchElevesTransport(classeFiltre || undefined),
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['bus-eleves'] })
    queryClient.invalidateQueries({ queryKey: ['bus-trajets'] })
  }

  const handleToggleSelect = (id: number) => {
    const copie = new Set(selectedIds)
    copie.has(id) ? copie.delete(id) : copie.add(id)
    setSelectedIds(copie)
  }

  const handleSelectAll = (lignes: EleveTransport[]) => {
    if (selectedIds.size === lignes.length && lignes.length > 0) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(lignes.map((e) => e.id)))
    }
  }

  const souscrireUnEleve = (eleve: EleveTransport) => {
    navigate('/bus/souscription', {
      state: {
        eleveIds: [eleve.id],
        eleveNoms: [eleve.nom_complet],
        affectationId: eleve.bus?.affectation_id,
        affectationActuelle: eleve.bus
          ? {
              trajet_id: eleve.bus.trajet.id,
              arret_id: eleve.bus.arret?.id ?? null,
              option_trajet: eleve.bus.option_trajet,
            }
          : undefined,
        retour: '/bus/eleves',
      },
    })
  }

  const souscrireSelection = () => {
    const lignes = (eleves ?? []).filter((e) => selectedIds.has(e.id))
    navigate('/bus/souscription', {
      state: {
        eleveIds: lignes.map((e) => e.id),
        eleveNoms: lignes.map((e) => e.nom_complet),
        retour: '/bus/eleves',
      },
    })
  }

  const retirerUnEleve = async (eleve: EleveTransport) => {
    if (!eleve.bus) return
    if (!(await confirmerSuppression(eleve.nom_complet))) return

    try {
      await retirerAffectation(eleve.bus.affectation_id)
      invalidate()
      succes(t('bus.affectation_deleted'))
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const colonnes: Colonne<EleveTransport>[] = [
    ...(can('bus.manage')
      ? [
          {
            cle: 'selection',
            sticky: 'left',
            largeur: '44px',
            entete: eleves ? (
              <input
                type="checkbox"
                checked={selectedIds.size === eleves.length && eleves.length > 0}
                onChange={() => handleSelectAll(eleves ?? [])}
                className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
              />
            ) : null,
            cellule: (e: EleveTransport) => (
              <input
                type="checkbox"
                checked={selectedIds.has(e.id)}
                onChange={() => handleToggleSelect(e.id)}
                className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
              />
            ),
          } satisfies Colonne<EleveTransport>,
        ]
      : []),
    {
      cle: 'eleve',
      entete: t('bus.eleve'),
      largeur: '210px',
      valeur: (e) => `${e.nom_complet} ${e.matricule ?? ''}`,
      cellule: (e) => (
        <div className="min-w-0">
          <div className="truncate font-semibold text-navy-900">{e.nom_complet}</div>
          <div className="truncate text-xs text-navy-400">
            {e.matricule ?? '—'} · {e.classe?.nom ?? '—'}
          </div>
        </div>
      ),
    },
    {
      cle: 'trajet',
      entete: t('bus.trajets_title'),
      largeur: '150px',
      valeur: (e) => e.bus?.trajet.nom,
      cellule: (e) => (e.bus ? e.bus.trajet.nom : <span className="text-navy-300">{t('bus.aucune_souscription')}</span>),
    },
    {
      cle: 'arret',
      entete: t('bus.arret_select'),
      largeur: '130px',
      valeur: (e) => e.bus?.arret?.nom,
      cellule: (e) =>
        e.bus?.arret ? (
          <span className="inline-flex items-center gap-1 text-navy-600">
            <MapPin className="h-3.5 w-3.5 text-navy-300" />
            {e.bus.arret.nom}
          </span>
        ) : (
          '—'
        ),
      masquerMobile: true,
    },
    {
      cle: 'option',
      entete: t('bus.option_trajet'),
      largeur: '110px',
      valeur: (e) => e.bus?.option_trajet,
      cellule: (e) => (e.bus ? t(`bus.${e.bus.option_trajet}`) : '—'),
      masquerMobile: true,
    },
    {
      cle: 'tarif',
      entete: t('bus.tarif_mensuel'),
      largeur: '120px',
      valeur: (e) => e.bus?.tarif_mensuel ?? -1,
      cellule: (e) => (e.bus?.tarif_mensuel ? `${e.bus.tarif_mensuel.toLocaleString('fr-FR')} FCFA` : '—'),
    },
    {
      cle: 'paiement',
      entete: t('bus.statut_paiement'),
      largeur: '120px',
      valeur: (e) => e.bus?.statut_paiement,
      cellule: (e) =>
        e.bus ? (
          <Badge tone={TONE_PAIEMENT[e.bus.statut_paiement] ?? 'neutral'}>
            {t(`bus.statut_paiement_${e.bus.statut_paiement}`)}
          </Badge>
        ) : (
          '—'
        ),
    },
    ...(can('bus.manage')
      ? [
          {
            cle: 'actions',
            entete: '',
            sticky: 'right',
            largeur: '150px',
            cellule: (e: EleveTransport) => (
              <div className="flex justify-end gap-1.5">
                <Button size="sm" variant="secondary" onClick={() => souscrireUnEleve(e)}>
                  <UserPlus className="h-3.5 w-3.5" />
                  {e.bus ? t('common.edit') : t('bus.souscrire')}
                </Button>
                {e.bus && (
                  <button
                    title={t('bus.affectation_remove')}
                    onClick={() => retirerUnEleve(e)}
                    className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                )}
              </div>
            ),
          } satisfies Colonne<EleveTransport>,
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('bus.affectations')} sousTitre={t('bus.affectations_subtitle')} icon={Bus} />

      {selectedIds.size > 0 && can('bus.manage') && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="font-medium text-navy-900">{t('bus.eleves_selectionnes', { count: selectedIds.size })}</p>
            <div className="flex flex-wrap gap-2">
              <Button onClick={souscrireSelection}>
                <UserPlus className="h-4 w-4" />
                {t('bus.souscrire_lot')} ({selectedIds.size})
              </Button>
              <button
                onClick={() => setSelectedIds(new Set())}
                className="rounded-lg px-4 py-2 text-sm font-medium text-navy-600 hover:bg-navy-50 whitespace-nowrap"
              >
                {t('common.cancel')}
              </button>
            </div>
          </div>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={eleves ?? []}
          cleLigne={(e) => e.id}
          placeholderRecherche={t('bus.search_eleve')}
          messageVide={t('bus.empty_eleves')}
          largeurMin={600}
          outils={
            <Select value={classeFiltre} onChange={(e) => setClasseFiltre(e.target.value ? Number(e.target.value) : '')}>
              <option value="">{t('bus.toutes_classes')}</option>
              {classes?.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nom}
                </option>
              ))}
            </Select>
          }
        />
      )}
    </div>
  )
}

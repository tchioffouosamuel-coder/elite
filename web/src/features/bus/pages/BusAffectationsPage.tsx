import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Bus, Clock, MapPin, TrendingDown, TrendingUp, Trash2, UserPlus, Users, Wallet } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import {
  fetchElevesTransport,
  fetchStatsTransport,
  retirerAffectation,
  retirerAffectationsLot,
  type EleveTransport,
} from '@/features/bus/api'
import { batchDeleteEleves } from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { StatCard } from '@/shared/ui/Card'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const COLONNES_IMPORT_BUS = ['Matricule', 'Nom', 'Bus', 'Date', 'Classe', 'Tarif', 'Arrêt', 'Mois', 'Option', 'Mode']

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
  const user = useAuthStore((s) => s.user)
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  // Un super admin en mode agrégé n'a, par défaut, aucune école ciblée pour
  // l'import : côté serveur, `Tenant::schoolId()` en choisirait alors une au
  // hasard et l'import échouerait pour tout élève qui ne s'y trouve pas (cf.
  // ScopeEtablissement). Concentré sur une seule école (`activeSchoolId` posé
  // via le SchoolSwitcher), il n'y a rien à choisir de plus.
  const ecolesImport =
    user?.is_super_admin && !activeSchoolId && (user.ecoles_accessibles?.length ?? 0) > 1
      ? user.ecoles_accessibles.map((e) => ({ id: e.id, nom: e.name }))
      : undefined

  const [classeFiltre, setClasseFiltre] = useState<number | ''>('')
  const [nonPreinscritsSeuls, setNonPreinscritsSeuls] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [suppressionEnCours, setSuppressionEnCours] = useState(false)
  const [retraitEnCours, setRetraitEnCours] = useState(false)

  const { data: classes } = useQuery({ queryKey: ['classes', 'select'], queryFn: () => fetchClasses() })
  const { data: elevesBruts, isLoading } = useQuery({
    queryKey: ['bus-eleves', classeFiltre],
    queryFn: () => fetchElevesTransport(classeFiltre || undefined),
  })
  const { data: stats } = useQuery({ queryKey: ['bus-stats'], queryFn: () => fetchStatsTransport() })
  const eleves = (nonPreinscritsSeuls
    ? elevesBruts?.filter((e) => !e.preinscrit_annee_active)
    : elevesBruts
  )?.slice().sort((a, b) => Number(!!b.bus) - Number(!!a.bus))

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['bus-eleves'] })
    queryClient.invalidateQueries({ queryKey: ['bus-trajets'] })
    queryClient.invalidateQueries({ queryKey: ['bus-stats'] })
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
            arret_nom: eleve.bus.arret?.nom ?? null,
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

  /** Retrait des souscriptions du lot sélectionné — jamais la fiche élève elle-même, cf. `supprimerSelection` pour ça. Ignore les élèves sans souscription active dans la sélection. */
  const retirerSelection = async () => {
    const lignes = (eleves ?? []).filter((e) => selectedIds.has(e.id) && e.bus)
    if (lignes.length === 0) return
    if (!(await confirmerSuppression(
      `${lignes.length} souscription(s) bus`,
      'Une souscription avec des versements existants est suspendue plutôt que supprimée, pour garder son historique de paiement.',
    ))) return

    setRetraitEnCours(true)
    try {
      const { retirees } = await retirerAffectationsLot(lignes.map((e) => e.bus!.affectation_id))
      succes(`${retirees} souscription(s) retirée(s).`)
      setSelectedIds(new Set())
      invalidate()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setRetraitEnCours(false)
    }
  }

  const supprimerSelection = async () => {
    const lignes = (eleves ?? []).filter((e) => selectedIds.has(e.id))
    if (lignes.length === 0) return
    if (!(await confirmerSuppression(
      `${lignes.length} fiche(s) élève`,
      'Ces fiches seront définitivement supprimées — action irréversible, sans sauvegarde possible. Ne sélectionnez que des fiches sans historique réel.',
    ))) return

    setSuppressionEnCours(true)
    try {
      const { deleted } = await batchDeleteEleves(lignes.map((e) => e.id))
      succes(`${deleted} fiche(s) supprimée(s).`)
      setSelectedIds(new Set())
      invalidate()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSuppressionEnCours(false)
    }
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
    ...(can('bus.souscrire')
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
          <div className="flex flex-wrap items-center gap-1.5 text-xs text-navy-400">
            <span className="truncate">{e.matricule ?? '—'} · {e.classe?.nom ?? '—'}</span>
            {!e.preinscrit_annee_active && <Badge tone="red">Non préinscrit</Badge>}
          </div>
          {e.moratoire && (
            <div className="mt-0.5 flex items-center gap-1 text-[11px] font-semibold text-gold-600">
              <Clock className="h-3 w-3" />
              Moratoire — expire dans {e.moratoire.jours_restants} j
            </div>
          )}
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
            {e.bus.arret.nom}{e.bus.arret.lieu_dit ? ` — ${e.bus.arret.lieu_dit}` : ''}
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
    {
      cle: 'actions',
      entete: '',
      sticky: 'right',
      largeur: can('bus.souscrire') ? '220px' : '110px',
      cellule: (e: EleveTransport) => (
        <div className="flex justify-end gap-1.5">
          {e.bus && (
            <Button
              size="sm"
              variant="secondary"
              onClick={() => navigate(`/bus/affectations/${e.bus!.affectation_id}/paiements`)}
            >
              <Wallet className="h-3.5 w-3.5" />
              {t('bus.paiements')}
            </Button>
          )}
          {can('bus.souscrire') && (
            <Button size="sm" variant="secondary" onClick={() => souscrireUnEleve(e)}>
              <UserPlus className="h-3.5 w-3.5" />
              {e.bus ? t('common.edit') : t('bus.souscrire')}
            </Button>
          )}
          {can('bus.souscrire') && e.bus && (
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

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('bus.affectations')}
        sousTitre={t('bus.affectations_subtitle')}
        icon={Bus}
        actions={
          can('bus.souscrire') && (
            <ImportExportBar
              titreImport={t('bus.import_title')}
              importUrl="bus/affectations/import"
              exportUrl="bus/affectations/export"
              modeleUrl="bus/affectations/modele"
              colonnes={COLONNES_IMPORT_BUS}
              nomFichier="souscriptions-bus"
              onImported={invalidate}
              ecoles={ecolesImport}
            />
          )
        }
      />

      {stats && (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {stats.par_ecole.length > 1 ? (
            stats.par_ecole
              .filter((p) => p.school)
              .map((p) => (
                <StatCard key={p.school!.id} label={`Souscrits — ${p.school!.name}`} value={p.souscrits} icon={Users} accent="navy" />
              ))
          ) : (
            <StatCard label="Souscrits au transport" value={stats.total_souscrits} icon={Users} accent="navy" />
          )}
          <StatCard
            label={`Net perçu — ${stats.mois_courant.mois}`}
            value={`${stats.mois_courant.paye.toLocaleString('fr-FR')} FCFA`}
            icon={TrendingUp}
            accent="green"
            hint={`Dû ce mois-ci : ${stats.mois_courant.du.toLocaleString('fr-FR')} FCFA`}
          />
          <StatCard
            label={`Net à recouvrer — ${stats.mois_courant.mois}`}
            value={`${stats.mois_courant.reste.toLocaleString('fr-FR')} FCFA`}
            icon={TrendingDown}
            accent={stats.mois_courant.reste > 0 ? 'red' : 'green'}
          />
        </div>
      )}

      {selectedIds.size > 0 && can('bus.souscrire') && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="font-medium text-navy-900">{t('bus.eleves_selectionnes', { count: selectedIds.size })}</p>
            <div className="flex flex-wrap gap-2">
              <Button onClick={souscrireSelection}>
                <UserPlus className="h-4 w-4" />
                {t('bus.souscrire_lot')} ({selectedIds.size})
              </Button>
              {(eleves ?? []).some((e) => selectedIds.has(e.id) && e.bus) && (
                <Button variant="danger" disabled={retraitEnCours} onClick={() => void retirerSelection()}>
                  <Trash2 className="h-4 w-4" />
                  Retirer les souscriptions ({(eleves ?? []).filter((e) => selectedIds.has(e.id) && e.bus).length})
                </Button>
              )}
              <Button variant="danger" disabled={suppressionEnCours} onClick={() => void supprimerSelection()}>
                <Trash2 className="h-4 w-4" />
                Supprimer la sélection ({selectedIds.size})
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
            <div className="flex flex-wrap items-center gap-2">
              <Select value={classeFiltre} onChange={(e) => setClasseFiltre(e.target.value ? Number(e.target.value) : '')}>
                <option value="">{t('bus.toutes_classes')}</option>
                {classes?.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.nom}
                  </option>
                ))}
              </Select>
              <label className="flex items-center gap-1.5 rounded-lg border border-navy-200 bg-white px-3 py-2 text-sm text-navy-700 shadow-soft">
                <input
                  type="checkbox"
                  checked={nonPreinscritsSeuls}
                  onChange={(e) => setNonPreinscritsSeuls(e.target.checked)}
                  className="h-4 w-4 rounded border-navy-300 text-red-600 focus:ring-red-500"
                />
                Non préinscrits uniquement
              </label>
            </div>
          }
        />
      )}
    </div>
  )
}

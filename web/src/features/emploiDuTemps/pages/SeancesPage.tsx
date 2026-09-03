import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, ClipboardCheck, Download, Lock, UserCheck } from 'lucide-react'
import { fetchClasses, fetchMaClasse, type Classe } from '@/features/classes/api'
import { fetchSeances, type Seance } from '@/features/emploiDuTemps/api'
import { useAuthStore } from '@/shared/store/authStore'
import { estSecondaire } from '@/shared/lib/ecole'
import { ouvrirDocument } from '@/shared/lib/download'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'

/** Lundi de la semaine en cours, au format `YYYY-MM-DD` — semaine par défaut de la fiche téléchargée. */
function lundiCourant(): string {
  const date = new Date()
  const jour = date.getDay() || 7 // dimanche = 0 → 7, pour rester dans la semaine ISO
  date.setDate(date.getDate() - jour + 1)
  return date.toISOString().slice(0, 10)
}

function dateLocaleAujourdhui(): string {
  const date = new Date()
  const mois = String(date.getMonth() + 1).padStart(2, '0')
  const jour = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${mois}-${jour}`
}

export function SeancesPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const estEnseignant = useAuthStore((s) => s.user?.est_enseignant ?? false)
  const navigate = useNavigate()
  const [classeId, setClasseId] = useState<number | ''>('')

  // Au primaire et à la maternelle, un enseignant est titulaire d'une seule
  // classe : pas de sélecteur à parcourir, les séances de sa classe uniquement.
  const restreintATitulaire = estEnseignant && !estSecondaire()

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses(), enabled: !restreintATitulaire })
  const { data: maClasse, isLoading: maClasseEnChargement } = useQuery({
    queryKey: ['ma-classe'],
    queryFn: fetchMaClasse,
    enabled: restreintATitulaire,
  })

  useEffect(() => {
    if (restreintATitulaire && maClasse && classeId === '') {
      setClasseId(maClasse.id)
    }
  }, [restreintATitulaire, maClasse, classeId])

  const classeActive = classeId ? Number(classeId) : null

  const { data: seances, isLoading } = useQuery({
    queryKey: ['seances', classeActive],
    queryFn: () => fetchSeances(classeActive!),
    enabled: classeActive !== null,
  })

  const seancesDuJour = seances?.filter((seance) => seance.date_seance === dateLocaleAujourdhui()) ?? []
  const autresSeances = seances?.filter((seance) => seance.date_seance !== dateLocaleAujourdhui()) ?? []

  const colonnesClasses: Colonne<Classe>[] = [
    {
      cle: 'nom',
      entete: t('emploiDuTemps.classe_label'),
      valeur: (c) => c.nom,
      cellule: (c) => <span className="font-semibold text-navy-900">{c.nom}</span>,
    },
    {
      cle: 'seances',
      entete: t('emploiDuTemps.seances_col'),
      valeur: (c) => c.seances_count ?? 0,
      cellule: (c) => c.seances_count ?? 0,
    },
    {
      cle: 'actions',
      entete: '',
      cellule: (c) => (
        <div className="flex items-center justify-end gap-2">
          <Button
            size="sm"
            variant="secondary"
            onClick={() => ouvrirDocument(`/classes/${c.id}/fiche-appel/pdf`, { semaine: lundiCourant() })}
          >
            <Download className="h-4 w-4" />
            {t('emploiDuTemps.telecharger_fiche_hebdo')}
          </Button>
          {can('appel.manage') && (
            <Button size="sm" onClick={() => setClasseId(c.id)}>
              <UserCheck className="h-4 w-4" />
              {t('emploiDuTemps.faire_appel')}
            </Button>
          )}
        </div>
      ),
    },
  ]

  const colonnes: Colonne<Seance>[] = [
    {
      cle: 'date',
      entete: t('emploiDuTemps.date_col'),
      valeur: (s) => s.date_seance,
      cellule: (s) => <span className="font-medium">{new Date(s.date_seance).toLocaleDateString('fr-FR')}</span>,
    },
    {
      cle: 'horaire',
      entete: t('emploiDuTemps.horaire_col'),
      valeur: (s) => s.heure_debut,
      cellule: (s) => `${s.heure_debut}–${s.heure_fin}`,
    },
    {
      cle: 'matiere',
      entete: t('emploiDuTemps.matiere_label'),
      valeur: (s) => s.matiere,
      cellule: (s) => <span className="font-semibold text-navy-900">{s.matiere}</span>,
    },
    {
      cle: 'enseignant',
      entete: t('emploiDuTemps.enseignant_col'),
      valeur: (s) => s.enseignant,
      cellule: (s) => s.enseignant ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'statut',
      entete: t('emploiDuTemps.statut_col'),
      valeur: (s) => s.statut,
      cellule: (s) => (
        <div className="flex items-center gap-1.5">
          <Badge tone={s.statut === 'effectuee' ? 'green' : s.statut === 'annulee' ? 'red' : 'neutral'}>
            {s.statut === 'effectuee'
              ? t('emploiDuTemps.statut_effectuee')
              : s.statut === 'annulee'
                ? t('emploiDuTemps.statut_annulee')
                : t('emploiDuTemps.statut_prevue')}
          </Badge>
          {s.verrouille && (
            <Lock className="h-3.5 w-3.5 text-navy-400" aria-label={t('emploiDuTemps.appel_verrouille') ?? undefined} />
          )}
        </div>
      ),
    },
    {
      cle: 'absents',
      entete: t('emploiDuTemps.absents_col'),
      valeur: (s) => s.absents,
      cellule: (s) => (s.absents > 0 ? <Badge tone="red">{s.absents}</Badge> : '—'),
    },
    {
      cle: 'actions',
      entete: '',
      cellule: (s) =>
        can('appel.manage') ? (
          <Button
            size="sm"
            variant="secondary"
            onClick={() => navigate(`/seances/${s.id}/appel`)}
            disabled={!s.demarree}
            title={!s.demarree ? t('emploiDuTemps.appel_pas_encore_commencee', { heure: s.heure_debut }) : undefined}
          >
            <UserCheck className="h-4 w-4" />
            {t('emploiDuTemps.faire_appel')}
          </Button>
        ) : null,
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('nav.seances')} icon={ClipboardCheck} />

      {restreintATitulaire ? (
        <>
          {maClasse && (
            <div className="flex flex-col gap-1.5">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('emploiDuTemps.classe_label')}</span>
              <span className="text-sm font-semibold text-navy-800">{maClasse.nom}</span>
            </div>
          )}
          {maClasseEnChargement ? (
            <Spinner />
          ) : !maClasse ? (
            <Card>
              <EmptyState label={t('classes.aucune_classe_confiee')} />
            </Card>
          ) : isLoading ? (
            <Spinner />
          ) : (
            <>
              <Card>
                <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Séances du jour</h2>
                <DataTable
                  colonnes={colonnes}
                  lignes={seancesDuJour}
                  cleLigne={(s) => s.id}
                  placeholderRecherche={t('emploiDuTemps.search_placeholder')}
                  messageVide="Aucune séance prévue aujourd'hui."
                  largeurMin={820}
                />
              </Card>
              <Card>
                <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Séances des autres jours</h2>
                <DataTable
                  colonnes={colonnes}
                  lignes={autresSeances}
                  cleLigne={(s) => s.id}
                  placeholderRecherche={t('emploiDuTemps.search_placeholder')}
                  messageVide="Aucune autre séance."
                  largeurMin={820}
                />
              </Card>
            </>
          )}
        </>
      ) : classeActive === null ? (
        <DataTable
          colonnes={colonnesClasses}
          lignes={classes ?? []}
          cleLigne={(c) => c.id}
          placeholderRecherche={t('emploiDuTemps.search_classe_placeholder')}
          messageVide={t('emploiDuTemps.empty_classes')}
          largeurMin={640}
        />
      ) : (
        <>
          <button
            onClick={() => setClasseId('')}
            className="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
          >
            <ArrowLeft className="h-4 w-4" />
            {t('emploiDuTemps.retour_classes')}
          </button>
          <div className="flex flex-col gap-1.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('emploiDuTemps.classe_label')}</span>
            <span className="text-sm font-semibold text-navy-800">{classes?.find((c) => c.id === classeActive)?.nom}</span>
          </div>
          {isLoading ? (
            <Spinner />
          ) : (
            <DataTable
              colonnes={colonnes}
              lignes={seances ?? []}
              cleLigne={(s) => s.id}
              placeholderRecherche={t('emploiDuTemps.search_placeholder')}
              messageVide={t('emploiDuTemps.empty_seances')}
              largeurMin={820}
            />
          )}
        </>
      )}

    </div>
  )
}

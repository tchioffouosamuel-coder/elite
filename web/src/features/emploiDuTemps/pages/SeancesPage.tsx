import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { ClipboardCheck, UserCheck } from 'lucide-react'
import { fetchClasses, fetchMaClasse } from '@/features/classes/api'
import { fetchSeances, type Seance } from '@/features/emploiDuTemps/api'
import { useAuthStore } from '@/shared/store/authStore'
import { estSecondaire } from '@/shared/lib/ecole'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'

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
        <Badge tone={s.statut === 'effectuee' ? 'green' : s.statut === 'annulee' ? 'red' : 'neutral'}>
          {s.statut === 'effectuee'
            ? t('emploiDuTemps.statut_effectuee')
            : s.statut === 'annulee'
              ? t('emploiDuTemps.statut_annulee')
              : t('emploiDuTemps.statut_prevue')}
        </Badge>
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
          <Button size="sm" variant="secondary" onClick={() => navigate(`/seances/${s.id}/appel`)}>
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
        maClasse && (
          <div className="flex flex-col gap-1.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('emploiDuTemps.classe_label')}</span>
            <span className="text-sm font-semibold text-navy-800">{maClasse.nom}</span>
          </div>
        )
      ) : (
        <Select
          label={t('emploiDuTemps.classe_label')}
          value={classeId}
          onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}
          className="max-w-xs"
        >
          <option value="">{t('emploiDuTemps.select_classe_placeholder')}</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </Select>
      )}

      {restreintATitulaire && maClasseEnChargement ? (
        <Spinner />
      ) : restreintATitulaire && !maClasse ? (
        <Card>
          <EmptyState label={t('classes.aucune_classe_confiee')} />
        </Card>
      ) : !classeActive ? (
        <Card>
          <EmptyState label={t('emploiDuTemps.choisir_classe_seances_hint')} />
        </Card>
      ) : isLoading ? (
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

    </div>
  )
}

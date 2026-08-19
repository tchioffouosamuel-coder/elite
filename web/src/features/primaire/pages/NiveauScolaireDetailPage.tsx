import { useParams, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Layers } from 'lucide-react'
import { fetchNiveauxScolaires } from '@/features/primaire/api'
import { fetchClasses, type Classe } from '@/features/classes/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

/** Classes rattachées à un niveau d'enseignement du primaire (SIL, CP, CE1…). */
export function NiveauScolaireDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const niveauId = Number(id)

  const { data: niveaux, isLoading: chargementNiveaux, isError: erreurNiveaux } = useQuery({
    queryKey: ['niveaux-scolaires'],
    queryFn: fetchNiveauxScolaires,
  })
  const { data: classes, isLoading: chargementClasses, isError: erreurClasses } = useQuery({
    queryKey: ['classes'],
    queryFn: () => fetchClasses(),
  })

  const niveau = niveaux?.find((n) => n.id === niveauId)
  const classesDuNiveau = (classes ?? []).filter((c) => c.niveau_scolaire_id === niveauId)

  if (chargementNiveaux || chargementClasses) return <Spinner />
  if (erreurNiveaux || erreurClasses || !niveau) return <ErrorState />

  const colonnes: Colonne<Classe>[] = [
    {
      cle: 'nom',
      entete: t('classes.nom'),
      valeur: (c) => c.nom,
      cellule: (c) => <span className="font-semibold text-navy-900">{c.nom}</span>,
    },
    {
      cle: 'school',
      entete: t('classes.ecole'),
      valeur: (c) => c.school?.name,
      cellule: (c) => <span className="text-navy-600">{c.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'effectif',
      entete: t('classes.effectif'),
      valeur: (c) => c.effectif ?? 0,
      cellule: (c) => <span className="tabular-nums">{c.effectif ?? 0}</span>,
    },
    {
      cle: 'titulaire',
      entete: t('classes.titulaire'),
      valeur: (c) => c.titulaire?.nom_complet,
      cellule: (c) => c.titulaire?.nom_complet ?? '—',
      masquerMobile: true,
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to="/niveaux" className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </Link>
        <PageHeader titre={t('niveaux.classes_title', { code: niveau.code, libelle: niveau.libelle })} icon={Layers} />
      </div>

      <DataTable
        colonnes={colonnes}
        lignes={classesDuNiveau}
        cleLigne={(c) => c.id}
        placeholderRecherche={t('classes.search_placeholder')}
        messageVide={t('niveaux.empty_classes')}
        largeurMin={640}
      />
    </div>
  )
}

import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQueries, useQuery } from '@tanstack/react-query'
import { Eye, IdCard } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { fetchEleves } from '@/features/eleves/api'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'

interface ResumeClasse {
  id: number
  nom: string
  effectif: number
  photos: number
  pourcentage: number
}

/**
 * Photos et cartes scolaires par classe — équivalent de manage_photos.php et
 * generate_IDcards_for_a_class.php dans _smapp : on suit la couverture photo de
 * la classe puis on édite la planche de cartes recto-verso en un seul PDF.
 */
export function IdentificationPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const elevesParClasse = useQueries({
    queries: (classes ?? []).map((classe) => ({
      queryKey: ['eleves-identification-summary', classe.id],
      queryFn: () => fetchEleves({ classe_id: classe.id, per_page: 1000 }),
    })),
  })

  const resumesClasses: ResumeClasse[] = (classes ?? []).map((classe, index) => {
    const elevesClasse = elevesParClasse[index]?.data?.items ?? []
    const effectif = classe.effectif ?? elevesClasse.length
    const photos = elevesClasse.filter((eleve) => eleve.photo_url).length

    return {
      id: classe.id,
      nom: classe.nom,
      effectif,
      photos,
      pourcentage: effectif > 0 ? Math.min(100, Math.round((photos / effectif) * 100)) : 0,
    }
  })

  const colonnes: Colonne<ResumeClasse>[] = [
    {
      cle: 'nom',
      entete: t('identification.class_name'),
      valeur: (classe) => classe.nom,
      cellule: (classe) => <span className="font-semibold text-navy-900">{classe.nom}</span>,
    },
    {
      cle: 'effectif',
      entete: t('identification.class_enrolled'),
      valeur: (classe) => classe.effectif,
      cellule: (classe) => <span className="font-semibold tabular-nums text-navy-800">{classe.effectif}</span>,
    },
    {
      cle: 'photos',
      entete: t('identification.class_photos'),
      valeur: (classe) => classe.photos,
      cellule: (classe) => (
        <span className="tabular-nums text-navy-700">
          <span className="font-semibold">{classe.photos}</span>
          <span className="text-navy-400"> / {classe.effectif}</span>
        </span>
      ),
    },
    {
      cle: 'progression',
      entete: t('identification.class_progress'),
      valeur: (classe) => classe.pourcentage,
      cellule: (classe) => (
        <div className="flex items-center gap-3">
          <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-navy-100">
            <div
              className={`h-full rounded-full transition-all ${classe.pourcentage === 100 ? 'bg-green-500' : 'bg-gold-500'}`}
              style={{ width: `${classe.pourcentage}%` }}
            />
          </div>
          <span className="w-10 text-right text-xs font-semibold tabular-nums text-navy-600">{classe.pourcentage}%</span>
        </div>
      ),
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (classe) => (
        <Button variant="secondary" onClick={() => navigate(`/identification/classes/${classe.id}`)}>
          <Eye className="h-4 w-4" />
          {t('identification.view_details')}
        </Button>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <IdCard className="h-6 w-6 text-gold-500" />
          <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('identification.title')}</h1>
        </div>
      </div>

      <DataTable
        colonnes={colonnes}
        lignes={resumesClasses}
        cleLigne={(classe) => classe.id}
        placeholderRecherche={t('identification.search_classes_placeholder')}
        messageVide={t('identification.empty_classes')}
        largeurMin={760}
      />
    </div>
  )
}

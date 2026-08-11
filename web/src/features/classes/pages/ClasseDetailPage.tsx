import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, IdCard } from 'lucide-react'
import { fetchClasse } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Tabs } from '@/shared/ui/Tabs'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ouvrirDocument } from '@/shared/lib/download'
import { AffectationsTab } from '@/features/pedagogie/pages/AffectationsTab'
import { NotesTab } from '@/features/notes/pages/NotesTab'
import { AbsencesTab } from '@/features/discipline/pages/AbsencesTab'
import { ResultatsTab } from '@/features/resultats/pages/ResultatsTab'
import { ResponsablesTab } from '@/features/classes/pages/ResponsablesTab'
import { ElevesTab } from '@/features/classes/pages/ElevesTab'
import { NotesPrimaireTab } from '@/features/primaire/pages/NotesPrimaireTab'

export function ClasseDetailPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const activeSchool = useAuthStore((s) => s.activeSchool)
  const { id } = useParams<{ id: string }>()
  const classeId = Number(id)
  const [tab, setTab] = useState('affectations')

  // Le primaire et la maternelle notent par volets d'évaluation, le secondaire
  // par séquence : deux grilles de saisie distinctes.
  const estSecondaire = (activeSchool()?.type ?? 'secondaire') === 'secondaire'

  const { data: classe, isLoading, isError } = useQuery({ queryKey: ['classe', classeId], queryFn: () => fetchClasse(classeId) })

  const tabs = [
    can('pedagogie.view') && { key: 'affectations', label: t('pedagogie.title') },
    can('eleves.view') && { key: 'eleves', label: t('eleves.title') },
    can('notes.view') && { key: 'notes', label: t('notes.title') },
    can('discipline.view') && { key: 'absences', label: t('discipline.title') },
    can('notes.view') && { key: 'resultats', label: t('resultats.title') },
    // Professeur principal, censeur, surveillant général et conseiller
    // d'orientation sont des fonctions du secondaire. Au primaire et en
    // maternelle, le titulaire tient seul la classe : l'onglet n'aurait que
    // des listes vides à proposer.
    estSecondaire && can('classes.view') && { key: 'responsables', label: 'Responsables' },
  ].filter(Boolean) as { key: string; label: string }[]

  if (isLoading) return <Spinner />
  if (isError || !classe) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-start justify-between">
        <div>
          <Link to="/classes" className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
            <ArrowLeft className="h-4 w-4" />
            {t('common.back')}
          </Link>
          <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{classe.nom}</h1>
          <p className="text-sm text-navy-400">
            {[
              classe.niveau_scolaire?.libelle ?? classe.niveau?.name_fr,
              classe.filiere,
              classe.titulaire ? `${t('classes.titulaire')} : ${classe.titulaire.nom_complet}` : null,
            ]
              .filter(Boolean)
              .join(' · ')}
          </p>
        </div>
        {can('eleves.view') && (
          <Button variant="secondary" onClick={() => ouvrirDocument(`/classes/${classeId}/cartes-scolaires`)}>
            <IdCard className="h-4 w-4" />
            {t('export.carte')}
          </Button>
        )}
      </div>

      <Tabs tabs={tabs} active={tab} onChange={setTab} />

      {tab === 'affectations' && <AffectationsTab classeId={classeId} />}
      {tab === 'eleves' && <ElevesTab classeId={classeId} />}
      {tab === 'notes' && (estSecondaire ? <NotesTab classeId={classeId} /> : <NotesPrimaireTab classeId={classeId} />)}
      {tab === 'absences' && <AbsencesTab classeId={classeId} />}
      {tab === 'resultats' && <ResultatsTab classeId={classeId} />}
      {tab === 'responsables' && estSecondaire && <ResponsablesTab classeId={classeId} />}
    </div>
  )
}

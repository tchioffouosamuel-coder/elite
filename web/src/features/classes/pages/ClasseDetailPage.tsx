import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { fetchClasse } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Tabs } from '@/shared/ui/Tabs'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { AffectationsTab } from '@/features/pedagogie/pages/AffectationsTab'
import { NotesTab } from '@/features/notes/pages/NotesTab'
import { AbsencesTab } from '@/features/discipline/pages/AbsencesTab'
import { ResultatsTab } from '@/features/resultats/pages/ResultatsTab'

export function ClasseDetailPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const { id } = useParams<{ id: string }>()
  const classeId = Number(id)
  const [tab, setTab] = useState('affectations')

  const { data: classe, isLoading, isError } = useQuery({ queryKey: ['classe', classeId], queryFn: () => fetchClasse(classeId) })

  const tabs = [
    can('pedagogie.view') && { key: 'affectations', label: t('pedagogie.title') },
    can('notes.view') && { key: 'notes', label: t('notes.title') },
    can('discipline.view') && { key: 'absences', label: t('discipline.title') },
    can('notes.view') && { key: 'resultats', label: t('resultats.title') },
  ].filter(Boolean) as { key: string; label: string }[]

  if (isLoading) return <Spinner />
  if (isError || !classe) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to="/classes" className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </Link>
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{classe.nom}</h1>
        <p className="text-sm text-navy-400">{classe.niveau?.name_fr} · {classe.filiere}</p>
      </div>

      <Tabs tabs={tabs} active={tab} onChange={setTab} />

      {tab === 'affectations' && <AffectationsTab classeId={classeId} />}
      {tab === 'notes' && <NotesTab classeId={classeId} />}
      {tab === 'absences' && <AbsencesTab classeId={classeId} />}
      {tab === 'resultats' && <ResultatsTab classeId={classeId} />}
    </div>
  )
}

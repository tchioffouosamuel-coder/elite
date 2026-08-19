import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useAuthStore } from '@/shared/store/authStore'
import { estSecondaire as estSecondaireHelper } from '@/shared/lib/ecole'
import { Tabs } from '@/shared/ui/Tabs'
import { AffectationsTab } from '@/features/pedagogie/pages/AffectationsTab'
import { NotesTab } from '@/features/notes/pages/NotesTab'
import { AbsencesTab } from '@/features/discipline/pages/AbsencesTab'
import { ResultatsTab } from '@/features/resultats/pages/ResultatsTab'
import { ResponsablesTab } from '@/features/classes/pages/ResponsablesTab'
import { ElevesTab } from '@/features/classes/pages/ElevesTab'
import { NotesPrimaireTab } from '@/features/primaire/pages/NotesPrimaireTab'
import type { Classe } from '@/features/classes/api'

/**
 * Onglets d'une classe (matières, élèves, notes, discipline, résultats),
 * partagés entre la fiche classe (`ClasseDetailPage`) et « Ma classe », la
 * vue du titulaire sur sa propre classe.
 */
export function ClasseTabs({ classe }: { classe: Classe }) {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const classeId = classe.id
  const [tab, setTab] = useState('affectations')

  // Le primaire et la maternelle notent par volets d'évaluation, le secondaire
  // par séquence : deux grilles de saisie distinctes. Le type vient de l'école
  // de LA classe affichée — pas de l'école active globale, qui n'a pas de
  // valeur unique en mode agrégé (super admin, "Toutes les écoles").
  const estSecondaire = estSecondaireHelper(classe.school?.type)

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
    estSecondaire && can('classes.view') && { key: 'responsables', label: t('classes.responsables_tab') },
  ].filter(Boolean) as { key: string; label: string }[]

  return (
    <div className="flex flex-col gap-5">
      <Tabs tabs={tabs} active={tab} onChange={setTab} />

      {tab === 'affectations' && (
        <AffectationsTab classeId={classeId} titulaireId={classe.titulaire_id} ecoleType={classe.school?.type} />
      )}
      {tab === 'eleves' && <ElevesTab classeId={classeId} />}
      {tab === 'notes' && (estSecondaire ? <NotesTab classeId={classeId} /> : <NotesPrimaireTab classeId={classeId} />)}
      {tab === 'absences' && <AbsencesTab classeId={classeId} ecoleType={classe.school?.type} />}
      {tab === 'resultats' && <ResultatsTab classeId={classeId} />}
      {tab === 'responsables' && estSecondaire && <ResponsablesTab classeId={classeId} />}
    </div>
  )
}

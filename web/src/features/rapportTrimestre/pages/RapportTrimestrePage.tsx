import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardList, Download } from 'lucide-react'
import {
  definirTexteTrimestre,
  fetchTextesTrimestre,
  type RubriqueTexteTrimestre,
} from '@/features/rapportTrimestre/api'
import { fetchSchools } from '@/features/classes/api'
import { fetchTrimestresAll } from '@/features/session/api'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { useAuthStore } from '@/shared/store/authStore'
import { erreur, succes } from '@/shared/lib/alertes'
import { telechargerFichier } from '@/shared/lib/download'
import type { ApiError } from '@/shared/types/api'

const RUBRIQUES: { value: RubriqueTexteTrimestre; label: string }[] = [
  { value: 'introduction', label: 'Introduction' },
  { value: 'observations_structure', label: 'Observations — structure' },
  { value: 'observations_eleves', label: 'Observations — élèves' },
  { value: 'observations_personnel', label: 'Observations — personnel' },
  { value: 'difficultes_rencontrees', label: 'Difficultés rencontrées' },
  { value: 'conclusion_generale', label: 'Conclusion générale' },
]

export function RapportTrimestrePage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const user = useAuthStore((s) => s.user)
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const setActiveSchool = useAuthStore((s) => s.setActiveSchool)
  const queryClient = useQueryClient()

  const [exportEnCours, setExportEnCours] = useState(false)
  const [trimestreChoisi, setTrimestreChoisi] = useState<number | null>(null)

  const { data: ecoles = [] } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres-all', activeSchoolId], queryFn: fetchTrimestresAll })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]
  const trimestreId = trimestreChoisi ?? trimestreActif?.id ?? null

  const { data: textes } = useQuery({
    queryKey: ['rapport-trimestre-textes', trimestreId],
    queryFn: () => fetchTextesTrimestre(trimestreId!),
    enabled: !!trimestreId,
  })

  const peutModifier = can('rapport_trimestre.manage')

  const exporterDocx = async () => {
    if (!trimestreId) return

    setExportEnCours(true)
    try {
      await telechargerFichier('/rapport-trimestre/complet/docx', { trimestre_id: trimestreId }, 'rapport-fin-trimestre.docx')
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setExportEnCours(false)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('rapportTrimestre.title')}
        sousTitre={t('rapportTrimestre.subtitle')}
        icon={ClipboardList}
        actions={
          <div className="flex items-center gap-2">
            {user?.is_super_admin && ecoles.length > 1 && (
              <Select
                value={activeSchoolId ?? ''}
                onChange={(event) => setActiveSchool(event.target.value ? Number(event.target.value) : null)}
                className="min-w-[180px]"
              >
                <option value="">Toutes les écoles</option>
                {ecoles.map((ecole) => (
                  <option key={ecole.id} value={ecole.id}>
                    {ecole.name}
                  </option>
                ))}
              </Select>
            )}
            {trimestres && trimestres.length > 0 && (
              <Select
                value={trimestreId ?? ''}
                onChange={(e) => setTrimestreChoisi(Number(e.target.value))}
              >
                {trimestres.map((tr) => (
                  <option key={tr.id} value={tr.id}>{tr.libelle}</option>
                ))}
              </Select>
            )}
            <Button variant="secondary" disabled={exportEnCours || !trimestreId} onClick={exporterDocx}>
              <Download className="h-4 w-4" />
              {t('rapportTrimestre.export_docx')}
            </Button>
          </div>
        }
      />

      {!trimestreId ? (
        <Spinner />
      ) : (
        <Card>
          <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">
            {t('rapportTrimestre.section_textes')}
          </h2>
          <div className="grid gap-3 sm:grid-cols-2">
            {RUBRIQUES.map((r) => (
              <ChampTexteLibre
                key={r.value}
                rubrique={r.value}
                label={r.label}
                valeur={textes?.[r.value] ?? null}
                trimestreId={trimestreId}
                peutModifier={peutModifier}
                onSaved={() => queryClient.invalidateQueries({ queryKey: ['rapport-trimestre-textes'] })}
              />
            ))}
          </div>
        </Card>
      )}
    </div>
  )
}

function ChampTexteLibre({
  rubrique,
  label,
  valeur,
  trimestreId,
  peutModifier,
  onSaved,
}: {
  rubrique: RubriqueTexteTrimestre
  label: string
  valeur: string | null
  trimestreId: number
  peutModifier: boolean
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [contenu, setContenu] = useState(valeur ?? '')
  const [submitting, setSubmitting] = useState(false)
  const modifie = contenu !== (valeur ?? '')

  const enregistrer = async () => {
    setSubmitting(true)
    try {
      await definirTexteTrimestre(rubrique, trimestreId, contenu)
      succes(t('rapportTrimestre.updated'))
      onSaved()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex flex-col gap-1">
      <label className="text-xs font-semibold text-navy-500">{label}</label>
      <textarea
        className="min-h-[70px] rounded-xl border border-navy-100 bg-white p-2 text-sm text-navy-900 focus:border-gold-400 focus:outline-none disabled:bg-cream-50"
        value={contenu}
        disabled={!peutModifier}
        onChange={(e) => setContenu(e.target.value)}
      />
      {peutModifier && modifie && (
        <Button type="button" variant="secondary" disabled={submitting} onClick={enregistrer} className="self-end">
          {t('common.save')}
        </Button>
      )}
    </div>
  )
}

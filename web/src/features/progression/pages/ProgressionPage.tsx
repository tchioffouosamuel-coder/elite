import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { GitBranch } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { fetchClasseMatieres } from '@/features/pedagogie/api'
import { fetchProgressionEtablissement } from '@/features/progression/api'
import { useAuthStore } from '@/shared/store/authStore'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Select } from '@/shared/ui/Field'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { ProgrammeEditor } from '@/features/progression/pages/ProgrammeEditor'
import { EvaluationsEditor } from '@/features/progression/pages/EvaluationsEditor'
import { ChampsPersonnalisesEditor } from '@/features/progression/pages/ChampsPersonnalisesEditor'

/** Barre d'avancement, lue d'un coup d'œil dans un tableau. */
function Jauge({ taux }: { taux: number }) {
  return (
    <div className="flex items-center gap-2">
      <div className="h-2 w-24 overflow-hidden rounded-full bg-navy-100">
        <div className="h-full rounded-full bg-gold-500" style={{ width: `${taux}%` }} />
      </div>
      <span className="text-xs font-semibold tabular-nums text-navy-600">{taux} %</span>
    </div>
  )
}

export function ProgressionPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)

  const [classeId, setClasseId] = useState<number | ''>('')
  const [classeMatiereId, setClasseMatiereId] = useState<number | ''>('')

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: affectations } = useQuery({
    queryKey: ['classe-matieres', classeId],
    queryFn: () => fetchClasseMatieres(Number(classeId)),
    enabled: !!classeId,
  })
  const { data: etablissement, isLoading } = useQuery({
    queryKey: ['progression-etablissement'],
    queryFn: fetchProgressionEtablissement,
  })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('progression.title')} sousTitre={t('progression.hint')} icon={GitBranch} />

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Select
          label={t('eleves.classe')}
          value={classeId}
          onChange={(e) => {
            setClasseId(e.target.value ? Number(e.target.value) : '')
            setClasseMatiereId('')
          }}
        >
          <option value="">{t('progression.vue_ensemble')}</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </Select>

        {classeId !== '' && (
          <Select
            label={t('matieres.title')}
            value={classeMatiereId}
            onChange={(e) => setClasseMatiereId(e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">—</option>
            {affectations?.map((a) => (
              <option key={a.id} value={a.id}>
                {a.matiere.nom}
              </option>
            ))}
          </Select>
        )}
      </div>

      {classeMatiereId !== '' ? (
        can('pedagogie.view') && (
          <div className="flex flex-col gap-5">
            <ProgrammeEditor classeMatiereId={Number(classeMatiereId)} />
            <EvaluationsEditor classeMatiereId={Number(classeMatiereId)} />
            <ChampsPersonnalisesEditor classeMatiereId={Number(classeMatiereId)} />
          </div>
        )
      ) : isLoading ? (
        <Spinner />
      ) : !etablissement || etablissement.length === 0 ? (
        <EmptyState label={t('progression.vide_etablissement')} />
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>{t('eleves.classe')}</Th>
              <Th>{t('progression.lecons')}</Th>
              <Th>{t('progression.avancement_court')}</Th>
              <Th>{t('progression.par_matiere')}</Th>
            </tr>
          </Thead>
          <tbody>
            {etablissement.map((ligne) => (
              <Tr key={ligne.classe_id}>
                <Td className="font-medium">
                  {ligne.classe}
                  {ligne.niveau && <span className="block text-xs text-navy-400">{ligne.niveau}</span>}
                </Td>
                <Td className="tabular-nums">
                  {ligne.traitees} / {ligne.lecons}
                </Td>
                <Td>
                  <Jauge taux={ligne.taux} />
                </Td>
                <Td>
                  <div className="flex flex-col gap-1">
                    {ligne.matieres
                      .filter((m) => m.lecons > 0)
                      .map((m) => (
                        <span key={m.classe_matiere_id} className="text-xs text-navy-500">
                          {m.matiere} — {m.traitees}/{m.lecons} ({m.taux} %)
                        </span>
                      ))}
                    {ligne.matieres.every((m) => m.lecons === 0) && (
                      <span className="text-xs text-navy-300">{t('progression.aucun_programme')}</span>
                    )}
                  </div>
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}

import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, BookOpen, ChevronRight, GitBranch } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { fetchProgressionClasse, fetchProgressionEtablissement, fetchProgramme } from '@/features/progression/api'
import { useAuthStore } from '@/shared/store/authStore'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { Button } from '@/shared/ui/Button'
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
  const { classeId, classeMatiereId } = useParams()
  const classeIdNumber = classeId ? Number(classeId) : null
  const classeMatiereIdNumber = classeMatiereId ? Number(classeMatiereId) : null

  if (classeMatiereIdNumber) {
    return <ProgrammeMatiereView classeMatiereId={classeMatiereIdNumber} />
  }

  if (classeIdNumber) {
    return <MatieresClasseView classeId={classeIdNumber} />
  }

  return <ClassesProgressionView />
}

function ClassesProgressionView() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: etablissement, isLoading } = useQuery({
    queryKey: ['progression-etablissement'],
    queryFn: fetchProgressionEtablissement,
  })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('progression.title')} sousTitre={t('progression.hint')} icon={GitBranch} />

      {isLoading ? (
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
              <Tr
                key={ligne.classe_id}
                role="button"
                tabIndex={0}
                onClick={() => navigate(`/progression/classes/${ligne.classe_id}`)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault()
                    navigate(`/progression/classes/${ligne.classe_id}`)
                  }
                }}
                className="cursor-pointer"
              >
                <Td className="font-medium">
                  <span className="flex items-center gap-2 text-navy-900">
                    {ligne.classe}
                    <ChevronRight className="h-4 w-4 text-navy-300" />
                  </span>
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

function MatieresClasseView({ classeId }: { classeId: number }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: matieres, isLoading } = useQuery({
    queryKey: ['progression-classe', classeId],
    queryFn: () => fetchProgressionClasse(classeId),
  })

  const classe = classes?.find((c) => c.id === classeId)
  const titre = classe?.nom ?? t('eleves.classe')
  const sousTitre = classe
    ? [classe.niveau?.name_fr, classe.niveau_scolaire?.libelle, classe.filiere].filter(Boolean).join(' • ')
    : t('progression.matieres_classe_hint')

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <PageHeader titre={titre} sousTitre={sousTitre || t('progression.matieres_classe_hint')} icon={BookOpen} />
        <Button type="button" variant="secondary" onClick={() => navigate('/progression')}>
          <ArrowLeft className="h-4 w-4" />
          {t('progression.retour_classes')}
        </Button>
      </div>

      {isLoading ? (
        <Spinner />
      ) : !matieres || matieres.length === 0 ? (
        <EmptyState label={t('progression.aucune_matiere_classe')} />
      ) : (
        <Table>
          <Thead>
            <tr>
              <Th>{t('matieres.title')}</Th>
              <Th>{t('pedagogie.enseignant')}</Th>
              <Th>{t('progression.lecons')}</Th>
              <Th>{t('progression.avancement_court')}</Th>
            </tr>
          </Thead>
          <tbody>
            {matieres.map((matiere) => (
              <Tr
                key={matiere.classe_matiere_id}
                role="button"
                tabIndex={0}
                onClick={() => navigate(`/progression/matieres/${matiere.classe_matiere_id}`)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault()
                    navigate(`/progression/matieres/${matiere.classe_matiere_id}`)
                  }
                }}
                className="cursor-pointer"
              >
                <Td className="font-medium">
                  <span className="flex items-center gap-2 text-navy-900">
                    {matiere.matiere}
                    <ChevronRight className="h-4 w-4 text-navy-300" />
                  </span>
                </Td>
                <Td>{matiere.enseignant ?? '—'}</Td>
                <Td className="tabular-nums">
                  {matiere.traitees} / {matiere.lecons}
                </Td>
                <Td>
                  <Jauge taux={matiere.taux} />
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}

function ProgrammeMatiereView({ classeMatiereId }: { classeMatiereId: number }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const can = useAuthStore((s) => s.can)
  const { data: programme } = useQuery({
    queryKey: ['programme', classeMatiereId],
    queryFn: () => fetchProgramme(classeMatiereId),
  })

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <PageHeader titre={t('progression.title')} sousTitre={t('progression.programme_matiere_hint')} icon={GitBranch} />
        <Button
          type="button"
          variant="secondary"
          onClick={() => navigate(programme ? `/progression/classes/${programme.classe.id}` : '/progression')}
        >
          <ArrowLeft className="h-4 w-4" />
          {programme ? t('progression.retour_matieres') : t('common.back')}
        </Button>
      </div>

      {can('pedagogie.view') && (
        <div className="flex flex-col gap-5">
          <ProgrammeEditor classeMatiereId={classeMatiereId} />
          <EvaluationsEditor classeMatiereId={classeMatiereId} />
          <ChampsPersonnalisesEditor classeMatiereId={classeMatiereId} />
        </div>
      )}
    </div>
  )
}

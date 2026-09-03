import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, BookOpen, ChevronRight, Download, FileSpreadsheet, GitBranch, Search, Upload } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import {
  fetchProgressionClasse,
  fetchProgressionEtablissement,
  fetchProgramme,
  telechargerModeleProgressionClasse,
  importerProgressionClasse,
  type TauxClasse,
} from '@/features/progression/api'
import { useAuthStore } from '@/shared/store/authStore'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Table, Thead, Th, Tr, Td } from '@/shared/ui/Table'
import { Spinner, EmptyState } from '@/shared/ui/Feedback'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { succes, erreur as alerteErreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
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
    return <ProgrammeMatiereView classeMatiereId={classeMatiereIdNumber} lectureSeule={estLectureSeule()} />
  }

  if (classeIdNumber) {
    return <MatieresClasseView classeId={classeIdNumber} />
  }

  return <ClassesProgressionView />
}

function estLectureSeule(): boolean {
  const { user, activeSchool } = useAuthStore.getState()
  const type = activeSchool()?.type

  return Boolean(user?.est_enseignant && (type === 'primaire' || type === 'maternelle'))
}

function ClassesProgressionView() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const [classeImport, setClasseImport] = useState<TauxClasse | null>(null)
  const [telechargementId, setTelechargementId] = useState<number | null>(null)
  const { data: etablissement, isLoading } = useQuery({
    queryKey: ['progression-etablissement'],
    queryFn: fetchProgressionEtablissement,
  })
  const classesFiltrees = (etablissement ?? []).filter((ligne) => {
    const terme = recherche.trim().toLocaleLowerCase()
    if (!terme) return true

    return [ligne.classe, ligne.niveau].filter(Boolean).some((valeur) => valeur!.toLocaleLowerCase().includes(terme))
  })

  const telecharger = async (ligne: TauxClasse) => {
    setTelechargementId(ligne.classe_id)
    try {
      await telechargerModeleProgressionClasse(ligne.classe_id, ligne.classe)
    } catch (err) {
      alerteErreur((err as ApiError).message)
    } finally {
      setTelechargementId(null)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('progression.title')} sousTitre={t('progression.hint')} icon={GitBranch} />

      <div className="relative w-full sm:max-w-xs">
        <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-300" />
        <input
          type="search"
          value={recherche}
          onChange={(event) => setRecherche(event.target.value)}
          placeholder={t('progression.recherche_classe')}
          aria-label={t('progression.recherche_classe')}
          className="w-full rounded-xl border border-navy-200 bg-white py-2.5 pl-10 pr-3 text-sm shadow-soft transition-colors placeholder:text-navy-300 focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
        />
      </div>

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
              <Th>{t('progression.import_groupe.colonne_actions')}</Th>
            </tr>
          </Thead>
          <tbody>
            {classesFiltrees.map((ligne) => (
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
                <Td>
                  <div className="flex items-center gap-1.5" onClick={(event) => event.stopPropagation()}>
                    <button
                      type="button"
                      title={t('progression.import_groupe.telecharger_modele')}
                      disabled={telechargementId === ligne.classe_id}
                      onClick={() => telecharger(ligne)}
                      className="rounded-lg border border-navy-200 p-1.5 text-navy-500 shadow-soft transition-colors hover:bg-navy-50 disabled:opacity-50"
                    >
                      <Download className="h-4 w-4" />
                    </button>
                    <button
                      type="button"
                      title={t('progression.import_groupe.importer')}
                      onClick={() => setClasseImport(ligne)}
                      className="rounded-lg border border-navy-200 p-1.5 text-navy-500 shadow-soft transition-colors hover:bg-navy-50"
                    >
                      <Upload className="h-4 w-4" />
                    </button>
                  </div>
                </Td>
              </Tr>
            ))}
          </tbody>
        </Table>
      )}

      {classeImport && (
        <ImportModeleClasseModal
          classe={classeImport}
          onClose={() => setClasseImport(null)}
          onImporte={() => queryClient.invalidateQueries({ queryKey: ['progression-etablissement'] })}
        />
      )}
    </div>
  )
}

/** Import groupé de la fiche de progression d'une classe : un classeur, une feuille par matière. */
function ImportModeleClasseModal({
  classe,
  onClose,
  onImporte,
}: {
  classe: TauxClasse
  onClose: () => void
  onImporte: () => void
}) {
  const { t } = useTranslation()
  const [fichier, setFichier] = useState<File | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)

  const envoyer = async () => {
    if (!fichier) return
    setEnvoi(true)
    setErreur(null)
    try {
      const resultat = await importerProgressionClasse(classe.classe_id, fichier)
      onImporte()
      onClose()
      succes(
        t('progression.import_groupe.resultat', {
          creees: resultat.creees,
          completees: resultat.completees,
          matieres: resultat.matieres_importees,
        }) +
        (resultat.feuilles_ignorees.length > 0
          ? ` ${t('progression.import_groupe.feuilles_ignorees', { count: resultat.feuilles_ignorees.length })}`
          : ''),
      )
    } catch (err) {
      setErreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={t('progression.import_groupe.titre', { classe: classe.classe })} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <div className="flex gap-2 rounded-lg bg-cream-100 p-3 text-xs text-navy-600">
          <FileSpreadsheet className="h-4 w-4 flex-none text-navy-400" />
          <div className="flex flex-col gap-1">
            <p>{t('progression.import_groupe.etape_1')}</p>
            <p>{t('progression.import_groupe.etape_2')}</p>
            <p className="text-navy-500">{t('progression.import_groupe.etape_3')}</p>
          </div>
        </div>

        <label className="flex flex-col gap-1.5">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('import.file')}</span>
          <input
            type="file"
            accept=".xlsx,.xls"
            onChange={(e) => setFichier(e.target.files?.[0] ?? null)}
            className="w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm shadow-soft file:mr-3 file:rounded-lg file:border-0 file:bg-navy-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-cream-50"
          />
        </label>

        {erreur && <p className="text-sm text-red-500">{erreur}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={envoyer} disabled={!fichier || envoi}>
            <Upload className="h-4 w-4" />
            {t('import.submit')}
          </Button>
        </div>
      </div>
    </Modal>
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

function ProgrammeMatiereView({ classeMatiereId, lectureSeule }: { classeMatiereId: number; lectureSeule: boolean }) {
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
          <ProgrammeEditor classeMatiereId={classeMatiereId} lectureSeule={lectureSeule} />
          {!lectureSeule && <EvaluationsEditor classeMatiereId={classeMatiereId} />}
          {!lectureSeule && <ChampsPersonnalisesEditor classeMatiereId={classeMatiereId} />}
        </div>
      )}
    </div>
  )
}

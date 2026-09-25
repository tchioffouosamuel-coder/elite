import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, CalendarClock, Camera, FileSpreadsheet, FileText, Upload } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Input, Select } from '@/shared/ui/Field'
import { Tabs } from '@/shared/ui/Tabs'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { ImportModal } from '@/shared/ui/ImportModal'
import { ImportPresenceOcrModal } from '@/features/personnel/components/ImportPresenceOcrModal'
import { ExportButton } from '@/shared/ui/ExportButton'
import { TemplateDownloadButton } from '@/shared/ui/TemplateDownloadButton'
import { useAuthStore } from '@/shared/store/authStore'
import {
  COLONNES_IMPORT_PRESENCE_PERSONNEL,
  annulerValidationPresence,
  enTeteEcoleSuivi,
  fetchDepartementsEcole,
  fetchIncoherencesPresencePersonnel,
  fetchPersonnels,
  fetchSuiviActivite,
  ouvrirFichePresencePersonnel,
  type GranulariteSuivi,
} from '@/features/personnel/api'
import { fetchSousSystemesEcole } from '@/features/classes/sous-systemes/api'
import { confirmer, succes } from '@/shared/lib/alertes'

function debutDuMois(): string {
  const d = new Date()
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10)
}

function finDuMois(): string {
  const d = new Date()
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10)
}

/**
 * Vue admin transverse de ce que « Ma journée » calcule déjà pour un seul
 * enseignant : prévu vs réalisé, mais pour tout le personnel et ventilé par
 * période — de quoi tracer l'activité et rapprocher la paie.
 */
export function SuiviActivitePage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const ecoles = user?.ecoles_accessibles ?? []
  const plusieursEcoles = ecoles.length > 1

  // Le suivi est propre à une école : un onglet par école accessible, dont
  // l'id part en `X-School-Id` sur chaque requête de la page — sans dépendre
  // de l'école active du reste de l'application (ni la modifier). Ouvre par
  // défaut sur l'école active si elle en fait partie, sinon la première.
  const [ongletEcole, setOngletEcole] = useState<number | null>(() =>
    ecoles.some((e) => e.id === activeSchoolId) ? activeSchoolId : (ecoles[0]?.id ?? null),
  )
  // Compte mono-école : aucun en-tête, l'API retient d'elle-même son école.
  const ecoleId = plusieursEcoles ? ongletEcole : null
  const enTete = enTeteEcoleSuivi(ecoleId)

  const [du, setDu] = useState(debutDuMois())
  const [au, setAu] = useState(finDuMois())
  const [datePresence, setDatePresence] = useState(new Date().toISOString().slice(0, 10))
  const [granularite, setGranularite] = useState<GranulariteSuivi>('jour')
  const [choixImportPresenceOuvert, setChoixImportPresenceOuvert] = useState(false)
  const [importPresenceOuvert, setImportPresenceOuvert] = useState(false)
  const [importPresenceOcrOuvert, setImportPresenceOcrOuvert] = useState(false)
  // '' = tout le personnel, 'p:<id>' = un enseignant précis,
  // 's:<id>' = toute une section (sous-système), 'd:<id>' = tout un département.
  const [selection, setSelection] = useState('')

  // Filtres bornés à l'école de l'onglet : un enseignant, une section ou un
  // département d'une autre école resterait sinon sélectionnable puis
  // introuvable une fois le suivi filtré côté API.
  const { data: personnels } = useQuery({
    queryKey: ['personnels-suivi-activite-filtre', ecoleId],
    queryFn: () => fetchPersonnels({ per_page: 500, schoolId: ecoleId ?? undefined }),
  })

  const { data: sousSystemes } = useQuery({
    queryKey: ['sous-systemes-suivi-activite-filtre', ecoleId],
    queryFn: () => fetchSousSystemesEcole(ecoleId),
  })

  const { data: departements } = useQuery({
    queryKey: ['departements-suivi-activite-filtre', ecoleId],
    queryFn: () => fetchDepartementsEcole(ecoleId),
  })

  const [type, idBrut] = selection.split(':')
  const idSelection = idBrut ? Number(idBrut) : null
  const personnelId = type === 'p' ? idSelection : null
  const sousSystemeId = type === 's' ? idSelection : null
  const departementId = type === 'd' ? idSelection : null

  const { data, isLoading, isError } = useQuery({
    queryKey: ['suivi-activite', ecoleId, du, au, granularite, selection],
    queryFn: () =>
      fetchSuiviActivite(
        {
          date_debut: du,
          date_fin: au,
          granularite,
          personnel_id: personnelId,
          sous_systeme_id: sousSystemeId,
          departement_id: departementId,
        },
        ecoleId,
      ),
  })

  const { data: incoherences } = useQuery({
    queryKey: ['suivi-activite-incoherences-presence', ecoleId, du, au, personnelId],
    queryFn: () =>
      fetchIncoherencesPresencePersonnel(
        {
          date_debut: du,
          date_fin: au,
          personnel_id: personnelId,
        },
        ecoleId,
      ),
  })

  const periodes = Array.from(new Set(data?.flatMap((ligne) => ligne.periodes.map((p) => p.periode)) ?? [])).sort()

  const choisirEcole = (id: number) => {
    setOngletEcole(id)
    // Change de source : la sélection précédente n'a plus de raison
    // d'appartenir à la nouvelle école, on efface plutôt que de garder une
    // sélection incohérente.
    setSelection('')
  }

  const choisirSelection = (valeur: string) => setSelection(valeur)

  const rafraichirPresences = () => {
    void queryClient.invalidateQueries({ queryKey: ['suivi-activite'] })
    void queryClient.invalidateQueries({ queryKey: ['suivi-activite-incoherences-presence'] })
  }

  const annulerValidation = async (seanceId: number) => {
    const ok = await confirmer({
      titre: t('personnel.suivi_activite.cancel_validation_title'),
      message: t('personnel.suivi_activite.cancel_validation_message'),
      action: t('personnel.suivi_activite.cancel_validation_action'),
    })
    if (!ok) return
    await annulerValidationPresence(seanceId, ecoleId)
    succes(t('personnel.suivi_activite.cancel_validation_success'))
    rafraichirPresences()
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('personnel.suivi_activite.title')}
        sousTitre={t('personnel.suivi_activite.subtitle')}
        icon={CalendarClock}
        actions={
          <>
              <Input
                type="date"
                value={datePresence}
                onChange={(e) => setDatePresence(e.target.value)}
                className="w-40"
                title={t('personnel.suivi_activite.daily_sheet_date')}
              />
              <Button type="button" variant="secondary" onClick={() => void ouvrirFichePresencePersonnel(datePresence, ecoleId)}>
                <FileText className="h-4 w-4" />
                {t('personnel.suivi_activite.daily_sheet')}
              </Button>
              <TemplateDownloadButton
                url="/personnels/presences-journalieres/modele"
                params={{ date: datePresence }}
                nomFichier={`modele-presence-personnel-${datePresence}.xlsx`}
                headers={enTete}
              />
              <ExportButton
                url="/personnels/presences-journalieres/export"
                params={{ date_debut: du, date_fin: au }}
                nomFichier="presences-personnel.xlsx"
                headers={enTete}
              />
              <Button type="button" variant="secondary" onClick={() => setChoixImportPresenceOuvert(true)}>
                <Upload className="h-4 w-4" />
                {t('import.submit')}
              </Button>
            </>
        }
      />

      {plusieursEcoles && (
        <Tabs
          tabs={ecoles.map((ecole) => ({ key: String(ecole.id), label: ecole.name }))}
          active={String(ongletEcole ?? '')}
          onChange={(cle) => choisirEcole(Number(cle))}
        />
      )}

      {choixImportPresenceOuvert && (
        <Modal title={t('personnel.suivi_activite.import_presence_title')} onClose={() => setChoixImportPresenceOuvert(false)}>
          <div className="flex flex-col gap-2.5">
            <button
              type="button"
              onClick={() => {
                setChoixImportPresenceOuvert(false)
                setImportPresenceOuvert(true)
              }}
              className="flex items-center gap-3 rounded-xl border border-navy-200 bg-white p-3.5 text-left shadow-soft transition-colors hover:border-navy-300 hover:bg-cream-50"
            >
              <FileSpreadsheet className="h-5 w-5 flex-none text-navy-500" />
              <span>
                <span className="block text-sm font-semibold text-navy-800">{t('personnel.suivi_activite.import_presence_fichier')}</span>
                <span className="block text-xs text-navy-400">{t('import.template_hint')} {COLONNES_IMPORT_PRESENCE_PERSONNEL.join(', ')}</span>
              </span>
            </button>
            <button
              type="button"
              onClick={() => {
                setChoixImportPresenceOuvert(false)
                setImportPresenceOcrOuvert(true)
              }}
              className="flex items-center gap-3 rounded-xl border border-navy-200 bg-white p-3.5 text-left shadow-soft transition-colors hover:border-navy-300 hover:bg-cream-50"
            >
              <Camera className="h-5 w-5 flex-none text-navy-500" />
              <span>
                <span className="block text-sm font-semibold text-navy-800">{t('personnel.suivi_activite.import_presence_photo')}</span>
                <span className="block text-xs text-navy-400">{t('personnel.suivi_activite.import_ocr_hint')}</span>
              </span>
            </button>
          </div>
        </Modal>
      )}

      {importPresenceOuvert && (
        <ImportModal
          title={t('personnel.suivi_activite.import_presence_title')}
          url="/personnels/presences-journalieres/import"
          columns={COLONNES_IMPORT_PRESENCE_PERSONNEL}
          extraFields={{ date: datePresence }}
          ecoles={
            plusieursEcoles
              ? ecoles.filter((e) => e.id === ecoleId).map((e) => ({ id: e.id, nom: e.name }))
              : undefined
          }
          ecoleId={ecoleId ?? undefined}
          onClose={() => setImportPresenceOuvert(false)}
          onImported={rafraichirPresences}
        />
      )}

      {importPresenceOcrOuvert && (
        <ImportPresenceOcrModal
          date={datePresence}
          schoolId={ecoleId}
          onClose={() => setImportPresenceOcrOuvert(false)}
          onImported={rafraichirPresences}
        />
      )}

      <Card>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <Input label={t('personnel.suivi_activite.from')} type="date" value={du} onChange={(e) => setDu(e.target.value)} />
          <Input label={t('personnel.suivi_activite.to')} type="date" value={au} onChange={(e) => setAu(e.target.value)} />
          <Select label={t('personnel.enseignant')} value={selection} onChange={(e) => choisirSelection(e.target.value)}>
            <option value="">{t('personnel.suivi_activite.all_staff')}</option>
            {!!sousSystemes?.length && (
              <>
                <option value="header:sections" disabled>
                  {t('personnel.suivi_activite.group_sections')}
                </option>
                {sousSystemes.map((s) => (
                  <option key={`s:${s.id}`} value={`s:${s.id}`}>
                    {t('personnel.suivi_activite.all_of_section', { nom: s.nom })}
                  </option>
                ))}
              </>
            )}
            {!!departements?.length && (
              <>
                <option value="header:departements" disabled>
                  {t('personnel.suivi_activite.group_departments')}
                </option>
                {departements.map((d) => (
                  <option key={`d:${d.id}`} value={`d:${d.id}`}>
                    {t('personnel.suivi_activite.all_of_department', { nom: d.nom })}
                  </option>
                ))}
              </>
            )}
            {!!personnels?.length && (
              <>
                <option value="header:staff" disabled>
                  {t('personnel.suivi_activite.group_staff')}
                </option>
                {personnels.map((p) => (
                  <option key={`p:${p.id}`} value={`p:${p.id}`}>
                    {p.nom_complet}
                  </option>
                ))}
              </>
            )}
          </Select>
        </div>
      </Card>

      <Tabs
        tabs={[
          { key: 'jour', label: t('personnel.suivi_activite.jour') },
          { key: 'semaine', label: t('personnel.suivi_activite.semaine') },
          { key: 'mois', label: t('personnel.suivi_activite.mois') },
          { key: 'annee', label: t('personnel.suivi_activite.annee') },
        ]}
        active={granularite}
        onChange={(cle) => setGranularite(cle as GranulariteSuivi)}
      />

      {!!incoherences?.length && (
        <Card>
          <div className="mb-3 flex items-center gap-2 text-red-600">
            <AlertTriangle className="h-4 w-4" />
            <h2 className="text-sm font-bold">{t('personnel.suivi_activite.presence_alerts', { count: incoherences.length })}</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[54rem] text-sm">
              <thead>
                <tr className="border-b border-red-100 text-xs uppercase tracking-wide text-navy-400">
                  <th className="py-2 text-left">{t('personnel.suivi_activite.column_staff')}</th>
                  <th className="py-2 text-left">{t('personnel.suivi_activite.course')}</th>
                  <th className="py-2 text-left">{t('personnel.suivi_activite.presence_interval')}</th>
                  <th className="py-2 text-left">{t('personnel.suivi_activite.alert_reason')}</th>
                  <th className="py-2 text-right">{t('common.actions')}</th>
                </tr>
              </thead>
              <tbody>
                {incoherences.map((alerte) => (
                  <tr key={alerte.seance_id} className="border-b border-navy-50">
                    <td className="py-2 font-semibold text-navy-700">{alerte.personnel}</td>
                    <td className="py-2 text-navy-600">
                      {alerte.date} · {alerte.heure_debut}-{alerte.heure_fin}
                      <div className="text-xs text-navy-400">{alerte.classe ?? '—'} · {alerte.matiere ?? '—'}</div>
                    </td>
                    <td className="py-2 text-navy-600">
                      {alerte.heure_arrivee ?? '—'} - {alerte.heure_depart ?? '—'}
                    </td>
                    <td className="py-2 text-red-600">
                      {alerte.motifs.map((motif) => t(`personnel.suivi_activite.${motif}`)).join(', ')}
                    </td>
                    <td className="py-2 text-right">
                      <Button type="button" size="sm" variant="secondary" onClick={() => void annulerValidation(alerte.seance_id)}>
                        {t('personnel.suivi_activite.cancel_validation_action')}
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      {isLoading ? (
        <Spinner />
      ) : isError ? (
        <ErrorState />
      ) : !data || data.length === 0 ? (
        <Card>
          <p className="text-sm text-navy-400">{t('personnel.suivi_activite.empty')}</p>
        </Card>
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[48rem] text-sm">
              <thead>
                <tr className="border-b border-navy-100 text-xs uppercase tracking-wide text-navy-400">
                  <th className="py-2 text-left">{t('personnel.suivi_activite.column_staff')}</th>
                  {periodes.map((periode) => (
                    <th key={periode} className="px-3 py-2 text-right">
                      {periode}
                    </th>
                  ))}
                  <th className="py-2 text-right">{t('personnel.suivi_activite.column_total')}</th>
                </tr>
              </thead>
              <tbody>
                {data.map((ligne) => {
                  const parPeriode = new Map(ligne.periodes.map((p) => [p.periode, p]))

                  return (
                    <tr key={ligne.personnel_id} className="border-b border-navy-50">
                      <td className="py-2">
                        <div className="font-semibold text-navy-700">{ligne.nom_complet}</div>
                        {ligne.fonction && <div className="text-xs text-navy-400">{ligne.fonction}</div>}
                      </td>
                      {periodes.map((periode) => {
                        const cellule = parPeriode.get(periode)

                        return (
                          <td key={periode} className="px-3 py-2 text-right tabular-nums">
                            {cellule ? (
                              <>
                                <div>
                                  {t('personnel.suivi_activite.hours_done_over_planned', {
                                    realisees: cellule.heures_realisees,
                                    prevues: cellule.heures_prevues,
                                  })}
                                </div>
                                {cellule.seances_en_retard > 0 && (
                                  <div className="text-xs text-red-500">
                                    {t('personnel.suivi_activite.sessions_late', { count: cellule.seances_en_retard })}
                                  </div>
                                )}
                              </>
                            ) : (
                              '—'
                            )}
                          </td>
                        )
                      })}
                      <td className="px-3 py-2 text-right font-bold tabular-nums text-navy-900">
                        {t('personnel.suivi_activite.hours_done_over_planned', {
                          realisees: ligne.totaux.heures_realisees,
                          prevues: ligne.totaux.heures_prevues,
                        })}
                        <div className="text-xs font-normal text-navy-400">
                          {t('personnel.suivi_activite.rate', { taux: ligne.totaux.taux })}
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  )
}

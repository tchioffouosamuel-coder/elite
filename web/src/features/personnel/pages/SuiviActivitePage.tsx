import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { CalendarClock } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Input, Select } from '@/shared/ui/Field'
import { Tabs } from '@/shared/ui/Tabs'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { useAuthStore } from '@/shared/store/authStore'
import { fetchDepartements, fetchPersonnels, fetchSuiviActivite, type GranulariteSuivi } from '@/features/personnel/api'
import { fetchSousSystemes } from '@/features/classes/sous-systemes/api'

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
  const user = useAuthStore((s) => s.user)
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const setActiveSchool = useAuthStore((s) => s.setActiveSchool)
  const ecoles = user?.ecoles_accessibles ?? []
  const plusieursEcoles = user?.is_super_admin && ecoles.length > 1

  const [du, setDu] = useState(debutDuMois())
  const [au, setAu] = useState(finDuMois())
  const [granularite, setGranularite] = useState<GranulariteSuivi>('jour')
  // '' = tout le personnel, 'p:<id>' = un enseignant précis,
  // 's:<id>' = toute une section (sous-système), 'd:<id>' = tout un département.
  const [selection, setSelection] = useState('')

  // Portée à l'école active : quand elle change de vraie source (le select
  // école lui-même), la liste ne doit garder que son personnel — sinon un
  // enseignant d'une autre école resterait sélectionnable puis introuvable
  // une fois le suivi filtré côté API.
  const { data: personnels } = useQuery({
    queryKey: ['personnels-suivi-activite-filtre', activeSchoolId],
    queryFn: () => fetchPersonnels({ per_page: 500, schoolId: activeSchoolId ?? undefined }),
  })

  const { data: sousSystemes } = useQuery({
    queryKey: ['sous-systemes-suivi-activite-filtre', activeSchoolId],
    queryFn: fetchSousSystemes,
    enabled: activeSchoolId !== null,
  })

  const { data: departements } = useQuery({
    queryKey: ['departements-suivi-activite-filtre', activeSchoolId],
    queryFn: fetchDepartements,
    enabled: activeSchoolId !== null,
  })

  const [type, idBrut] = selection.split(':')
  const idSelection = idBrut ? Number(idBrut) : null
  const personnelId = type === 'p' ? idSelection : null
  const sousSystemeId = type === 's' ? idSelection : null
  const departementId = type === 'd' ? idSelection : null

  const { data, isLoading, isError } = useQuery({
    queryKey: ['suivi-activite', activeSchoolId, du, au, granularite, selection],
    queryFn: () =>
      fetchSuiviActivite({
        date_debut: du,
        date_fin: au,
        granularite,
        personnel_id: personnelId,
        sous_systeme_id: sousSystemeId,
        departement_id: departementId,
      }),
    enabled: activeSchoolId !== null,
  })

  const periodes = Array.from(new Set(data?.flatMap((ligne) => ligne.periodes.map((p) => p.periode)) ?? [])).sort()

  const choisirEcole = (valeur: string) => {
    setActiveSchool(valeur ? Number(valeur) : null)
    // Change de source : la sélection précédente n'a plus de raison
    // d'appartenir à la nouvelle école, on efface plutôt que de garder une
    // sélection incohérente.
    setSelection('')
  }

  const choisirSelection = (valeur: string) => {
    setSelection(valeur)
    // Un enseignant précis pilote l'école plutôt que de forcer l'utilisateur
    // à le refaire à la main ; une section ou un département reste dans
    // l'école déjà active, à laquelle ces listes sont déjà bornées.
    if (valeur.startsWith('p:')) {
      const ecoleDuPersonnel = personnels?.find((p) => String(p.id) === valeur.slice(2))?.school_id
      if (ecoleDuPersonnel && ecoleDuPersonnel !== activeSchoolId) setActiveSchool(ecoleDuPersonnel)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('personnel.suivi_activite.title')} sousTitre={t('personnel.suivi_activite.subtitle')} icon={CalendarClock} />

      <Card>
        <div className={`grid grid-cols-1 gap-3 sm:grid-cols-2 ${plusieursEcoles ? 'lg:grid-cols-4' : 'lg:grid-cols-3'}`}>
          {plusieursEcoles && (
            <Select label={t('personnel.suivi_activite.school')} value={activeSchoolId ?? ''} onChange={(e) => choisirEcole(e.target.value)}>
              <option value="">{t('personnel.suivi_activite.all_schools')}</option>
              {ecoles.map((ecole) => (
                <option key={ecole.id} value={ecole.id}>
                  {ecole.name}
                </option>
              ))}
            </Select>
          )}
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

      {activeSchoolId === null ? (
        <Card>
          <p className="text-sm text-navy-400">{t('personnel.suivi_activite.select_school')}</p>
        </Card>
      ) : isLoading ? (
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

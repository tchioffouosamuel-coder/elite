import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { ArrowLeft, ChevronDown, HeartPulse, History, Pencil, Phone, Plus, Trash2 } from 'lucide-react'
import { deleteVisiteInfirmerie, fetchPatientInfirmerie } from '@/features/infirmerie/api'
import { telephonesTuteur } from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Avatar } from '@/shared/ui/EntityHeader'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { EmptyState, ErrorState, Spinner } from '@/shared/ui/Feedback'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function Champ({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs font-semibold text-navy-400">{label}</dt>
      <dd className="mt-1 whitespace-pre-wrap break-words text-sm text-navy-800">{children}</dd>
    </div>
  )
}

export function PatientInfirmeriePage() {
  const { t, i18n } = useTranslation()
  const { eleveId: param } = useParams<{ eleveId: string }>()
  const eleveId = Number(param)
  const [searchParams] = useSearchParams()
  const visiteSelectionnee = Number(searchParams.get('visite'))
  const navigate = useNavigate()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [suppression, setSuppression] = useState<number | null>(null)
  const urlPatient = `/infirmerie/patients/${eleveId}`
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['infirmerie', 'patients', eleveId],
    queryFn: () => fetchPatientInfirmerie(eleveId),
    enabled: Number.isSafeInteger(eleveId) && eleveId > 0,
  })
  const retour = (
    <Link to="/infirmerie" className="flex w-fit items-center gap-2 text-sm font-medium text-navy-500 hover:text-navy-800">
      <ArrowLeft className="h-4 w-4" />{t('infirmerie.back_to_register')}
    </Link>
  )

  if (isLoading) return <div>{retour}<Spinner /></div>
  if (isError || !data) {
    return <div className="flex flex-col gap-5">{retour}<ErrorState message={(error as ApiError | null)?.message ?? t('infirmerie.patient_unavailable')} /></div>
  }

  const { patient, visites } = data
  const nonRenseigne = t('infirmerie.non_renseigne')
  const dateHeure = (valeur: string) => new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(valeur))
  const montant = (valeur: number) => new Intl.NumberFormat(i18n.language, { style: 'currency', currency: 'XAF', maximumFractionDigits: 0 }).format(valeur)

  const supprimer = async (id: number) => {
    if (!(await confirmerSuppression(t('infirmerie.delete_target', { eleve: patient.nom_complet })))) return
    setSuppression(id)
    try {
      await deleteVisiteInfirmerie(id)
      await queryClient.invalidateQueries({ queryKey: ['infirmerie'] })
      queryClient.invalidateQueries({ queryKey: ['infirmerie-visites'] })
      queryClient.invalidateQueries({ queryKey: ['inventaire'] })
      succes(t('infirmerie.deleted'))
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSuppression(null)
    }
  }

  return (
    <div className="flex min-w-0 flex-col gap-6">
      {retour}
      <header className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex min-w-0 max-w-full items-center gap-3">
          <Avatar url={patient.photo_url} nom={patient.nom_complet} />
          <div className="min-w-0">
            <p className="text-xs font-semibold text-navy-400">{t('infirmerie.patient_record')}</p>
            <h1 className="break-words font-display text-xl font-bold text-navy-900 sm:text-2xl">{patient.nom_complet}</h1>
            <p className="mt-1 break-words text-sm text-navy-500">{[patient.matricule, patient.classe?.nom, patient.school?.name].filter(Boolean).join(' · ')}</p>
          </div>
        </div>
        {can('infirmerie.create') && (
          <Button onClick={() => navigate(`/infirmerie/nouvelle?eleve_id=${eleveId}&retour=${encodeURIComponent(urlPatient)}`)}>
            <Plus className="h-4 w-4" />{t('infirmerie.add_visit')}
          </Button>
        )}
      </header>

      <dl className="grid gap-4 border-y border-navy-100 py-4 sm:grid-cols-3">
        <Champ label={t('infirmerie.visits_total')}><span className="text-lg font-bold tabular-nums">{visites.length}</span></Champ>
        <Champ label={t('infirmerie.care_cost_total')}><span className="text-lg font-bold tabular-nums">{montant(visites.reduce((total, visite) => total + visite.cout_total, 0))}</span></Champ>
        <Champ label={t('infirmerie.last_visit')}>{visites[0] ? dateHeure(visites[0].date_visite) : nonRenseigne}</Champ>
      </dl>

      <div className="grid gap-6 lg:grid-cols-2">
        <section className="min-w-0">
          <h2 className="mb-4 flex items-center gap-2 text-base font-bold text-navy-900"><HeartPulse className="h-4 w-4" />{t('infirmerie.fiche_sanitaire')}</h2>
          <dl className="grid gap-4 sm:grid-cols-2">
            <Champ label={t('eleves.date_naissance')}>{patient.date_naissance ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'long' }).format(new Date(`${patient.date_naissance}T12:00:00`)) : nonRenseigne}</Champ>
            <Champ label={t('infirmerie.groupe_sanguin')}>{patient.groupe_sanguin || nonRenseigne}</Champ>
            <Champ label={t('infirmerie.aptitude')}>{patient.aptitude ? t(`infirmerie.aptitude_${patient.aptitude}`) : nonRenseigne}</Champ>
            <Champ label={t('infirmerie.allergies')}>{patient.allergies ? <span className="font-semibold text-red-700">{patient.allergies}</span> : nonRenseigne}</Champ>
            <Champ label={t('infirmerie.situation_sanitaire')}>{patient.situation_sanitaire || nonRenseigne}</Champ>
            <Champ label={t('infirmerie.adresse_quartier')}>{patient.adresse || nonRenseigne}</Champ>
          </dl>
        </section>
        <section className="min-w-0">
          <h2 className="mb-4 flex items-center gap-2 text-base font-bold text-navy-900"><Phone className="h-4 w-4" />{t('infirmerie.contacts_parents')}</h2>
          {patient.tuteurs.length === 0 ? <p className="text-sm text-navy-400">{t('infirmerie.contacts_parents_empty')}</p> : (
            <div className="divide-y divide-navy-100">
              {patient.tuteurs.map((tuteur) => (
                <div key={tuteur.id} className="flex flex-wrap items-start justify-between gap-2 py-3 first:pt-0">
                  <div className="min-w-0">
                    <p className="break-words text-sm font-semibold text-navy-800">{tuteur.nom_complet}</p>
                    <p className="text-xs text-navy-400">{tuteur.lien_parente}</p>
                    {tuteur.is_principal && <Badge tone="neutral">{t('infirmerie.contact_principal')}</Badge>}
                  </div>
                  <div className="flex flex-wrap gap-3">
                    {telephonesTuteur(tuteur).map((numero) => <a key={numero} href={`tel:${numero}`} className="inline-flex items-center gap-1.5 text-sm text-navy-600 hover:underline" title={t('infirmerie.appeler')}><Phone className="h-3.5 w-3.5" />{numero}</a>)}
                    {tuteur.email && <a href={`mailto:${tuteur.email}`} className="break-all text-sm text-navy-600 hover:underline">{tuteur.email}</a>}
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>

      <section className="min-w-0 border-t border-navy-100 pt-5">
        <h2 className="mb-4 flex items-center gap-2 text-base font-bold text-navy-900"><History className="h-4 w-4" />{t('infirmerie.historique_label')}</h2>
        {visites.length === 0 ? <EmptyState label={t('infirmerie.empty')} /> : (
          <div className="flex flex-col gap-3">
            {visites.map((visite) => (
              <details key={visite.id} open={visite.id === visiteSelectionnee} className="group min-w-0 rounded-lg border border-navy-100 bg-white">
                <summary className="flex cursor-pointer list-none flex-wrap items-center gap-3 p-4 marker:content-none [&::-webkit-details-marker]:hidden">
                  <ChevronDown className="h-4 w-4 shrink-0 text-navy-400 transition-transform group-open:rotate-180" />
                  <div className="min-w-0 flex-1 basis-48">
                    <p className="text-xs text-navy-400">{dateHeure(visite.date_visite)}</p>
                    <h3 className="mt-1 break-words text-sm font-semibold text-navy-900">{visite.raison}</h3>
                  </div>
                  <Badge tone={visite.type_traitement === 'externe' ? 'red' : 'neutral'}>{t(`infirmerie.type_${visite.type_traitement}`)}</Badge>
                  <span className="text-sm font-semibold tabular-nums text-navy-800">{montant(visite.cout_total)}</span>
                </summary>
                <div className="border-t border-navy-50 p-4">
                  <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Champ label={t('infirmerie.soins_prodiges')}>{visite.soins_prodiges}</Champ>
                    <Champ label={t('infirmerie.malaises_label')}>{visite.malaises.map((malaise) => i18n.language.startsWith('en') ? malaise.label_en || malaise.label_fr : malaise.label_fr).join(', ') || nonRenseigne}</Champ>
                    <Champ label={t('infirmerie.structure_externe')}>{visite.structure_externe || nonRenseigne}</Champ>
                    <Champ label={t('infirmerie.observations')}>{visite.observations || nonRenseigne}</Champ>
                    <Champ label={t('infirmerie.recorded_by')}>{visite.enregistre_par || nonRenseigne}</Champ>
                    <Champ label={t('eleves.classe')}>{visite.classe?.nom || nonRenseigne}</Champ>
                    <Champ label={t('infirmerie.materiels_label')}>{visite.materiels.length ? visite.materiels.map((materiel) => <div key={materiel.id}>{materiel.nom} × {materiel.quantite} · {montant(materiel.cout)}</div>) : nonRenseigne}</Champ>
                    <Champ label={t('infirmerie.autre_materiel')}>{visite.autre_materiel || nonRenseigne}</Champ>
                  </dl>
                  <dl className="mt-4 grid gap-3 border-t border-navy-50 pt-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Champ label={t('infirmerie.cout_soins')}>{montant(visite.cout_soins)}</Champ>
                    <Champ label={t('infirmerie.cout_materiels')}>{montant(visite.cout_materiels)}</Champ>
                    <Champ label={t('infirmerie.cout_autre_materiel')}>{montant(visite.cout_autre_materiel)}</Champ>
                    <Champ label={t('infirmerie.cout_total')}><strong>{montant(visite.cout_total)}</strong></Champ>
                  </dl>
                  {can('infirmerie.update|infirmerie.delete') && (
                    <div className="mt-4 flex justify-end gap-2">
                      {can('infirmerie.update') && <Button variant="secondary" onClick={() => navigate(`/infirmerie/${visite.id}/edit?retour=${encodeURIComponent(`${urlPatient}?visite=${visite.id}`)}`)}><Pencil className="h-4 w-4" />{t('common.edit')}</Button>}
                      {can('infirmerie.delete') && <Button variant="danger" disabled={suppression !== null} onClick={() => supprimer(visite.id)}><Trash2 className="h-4 w-4" />{t('common.delete')}</Button>}
                    </div>
                  )}
                </div>
              </details>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

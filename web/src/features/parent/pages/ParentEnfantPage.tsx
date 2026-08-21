import { useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { JOURS } from '@/features/emploiDuTemps/api'
import {
  ArrowLeft,
  FileDown,
  Wallet,
  HeartPulse,
  UserRound,
  GitBranch,
  CalendarX,
  MessageSquare,
  ShieldCheck,
  ShieldAlert,
  Send,
  FilePlus2,
  Pencil,
  Clock,
  CalendarClock,
  Stethoscope,
  Gavel,
} from 'lucide-react'
import {
  fetchEnfant,
  fetchFinanceEnfant,
  fetchProgressionEnfant,
  fetchAbsencesEnfant,
  fetchJustificationsEnfant,
  soumettreJustification,
  fetchObservationsEnfant,
  soumettreObservation,
  fetchModificationEnAttente,
  soumettreModification,
  fetchEmploiDuTempsEnfant,
  fetchVisitesInfirmerieEnfant,
  fetchSanctionsEnfant,
  type MotifJustification,
  type EnfantDossier,
  type ModificationEnfantPayload,
} from '@/features/parent/api'
import { francs } from '@/features/finance/api'
import { ouvrirDocument } from '@/shared/lib/download'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const MOTIFS_JUSTIFICATION: { valeur: MotifJustification; libelle: string }[] = [
  { valeur: 'maladie', libelle: 'Maladie' },
  { valeur: 'scolarite', libelle: "Raison d'ordre scolaire" },
  { valeur: 'permission', libelle: 'Permission' },
]

function Champ({ label, valeur }: { label: string; valeur: string | null | undefined }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">{label}</span>
      <span className="text-sm font-medium text-navy-800">{valeur || '—'}</span>
    </div>
  )
}

/**
 * Tout ce qui concerne un enfant, sur un seul écran — pas d'onglets : le
 * parent doit pouvoir tout voir en défilant, sans naviguer d'écran en écran.
 */
export function ParentEnfantPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const eleveId = Number(id)
  const [modificationOuverte, setModificationOuverte] = useState(false)

  const { data: e, isLoading, isError } = useQuery({ queryKey: ['parent-enfant', eleveId], queryFn: () => fetchEnfant(eleveId) })
  const { data: modificationEnAttente } = useQuery({
    queryKey: ['parent-modification', eleveId],
    queryFn: () => fetchModificationEnAttente(eleveId),
  })

  if (isLoading) return <Spinner />
  if (isError || !e) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to="/parent" className="mb-2 flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          Mes enfants
        </Link>
        <div className="flex flex-wrap items-center gap-3">
          {e.photo_url ? (
            <img src={e.photo_url} alt={e.nom_complet} className="h-14 w-14 rounded-full object-cover ring-1 ring-navy-100" />
          ) : (
            <span className="flex h-14 w-14 items-center justify-center rounded-full bg-navy-700 text-lg font-bold text-cream-50">
              {e.nom_complet.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()}
            </span>
          )}
          <div className="min-w-0">
            <h1 className="break-words font-display text-2xl font-bold tracking-tight text-navy-900">{e.nom_complet}</h1>
            <p className="break-words text-sm text-navy-400">{[e.matricule, e.classe?.nom, e.school?.name].filter(Boolean).join(' · ') || '—'}</p>
          </div>
          <div className="flex w-full flex-wrap items-center gap-2 sm:ml-auto sm:w-auto">
            {e.sante.aptitude === 'inapte' && <Badge tone="red">Inapte au sport</Badge>}
            <Button className="flex-1 sm:flex-none" variant="secondary" onClick={() => ouvrirDocument(`/parent/enfants/${eleveId}/bulletin`)}>
              <FileDown className="h-4 w-4" />
              Bulletin
            </Button>
            {modificationEnAttente ? (
              <Badge tone="gold">
                <Clock className="h-3 w-3" />
                Modification en attente
              </Badge>
            ) : (
              <Button className="flex-1 sm:flex-none" variant="secondary" onClick={() => setModificationOuverte(true)}>
                <Pencil className="h-4 w-4" />
                Modifier mes informations
              </Button>
            )}
            <Button className="flex-1 sm:flex-none" onClick={() => navigate(`/parent/preinscription/existant/${eleveId}`)}>
              <FilePlus2 className="h-4 w-4" />
              Préinscription
            </Button>
          </div>
        </div>
      </div>

      {modificationEnAttente && (
        <p className="flex items-center gap-2 rounded-xl bg-gold-50 px-3.5 py-2.5 text-sm text-gold-800">
          <Clock className="h-4 w-4 flex-none" />
          Une modification de fiche est en attente de validation par l'établissement, déposée le{' '}
          {new Date(modificationEnAttente.created_at).toLocaleString('fr-FR')}.
        </p>
      )}

      <Card>
        <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <UserRound className="h-4 w-4" />
          Identité
        </h2>
        <div className="mb-4 flex items-center gap-4">
          {e.photo_url ? (
            <img src={e.photo_url} alt={e.nom_complet} className="h-24 w-24 flex-none rounded-xl object-cover ring-1 ring-navy-100" />
          ) : (
            <span className="flex h-24 w-24 flex-none items-center justify-center rounded-xl bg-cream-100 text-xs font-medium text-navy-300 ring-1 ring-navy-100">
              Aucune photo
            </span>
          )}
          <Champ label="Nom complet" valeur={e.nom_complet} />
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Champ label="Sexe" valeur={e.sexe === 'F' ? 'Féminin' : 'Masculin'} />
          <Champ label="Date de naissance" valeur={e.date_naissance} />
          <Champ label="Lieu de naissance" valeur={e.lieu_naissance} />
          <Champ label="Adresse" valeur={e.adresse} />
          <Champ label="N° acte de naissance" valeur={e.acte_naissance.numero} />
          <Champ label="Lieu de délivrance" valeur={e.acte_naissance.lieu_delivrance} />
          <Champ label="Officier d'état civil" valeur={e.acte_naissance.officier_etat_civil} />
        </div>
      </Card>

      <Card>
        <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <HeartPulse className="h-4 w-4 text-gold-500" />
          Situation sanitaire
        </h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Champ label="Groupe sanguin" valeur={e.sante.groupe_sanguin} />
          <Champ label="Aptitude" valeur={e.sante.aptitude === 'apte' ? 'Apte' : 'Inapte'} />
          <div className="sm:col-span-2 lg:col-span-2">
            <Champ label="Allergies" valeur={e.sante.allergies} />
          </div>
          <div className="sm:col-span-2 lg:col-span-4">
            <Champ label="Situation sanitaire" valeur={e.sante.situation_sanitaire} />
          </div>
        </div>
      </Card>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Tuteurs</h2>
        <div className="flex flex-col divide-y divide-navy-100">
          {e.tuteurs.map((t) => (
            <div key={t.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
              <div>
                <p className="text-sm font-semibold text-navy-800">
                  {t.nom_complet}
                  {t.is_principal && <span className="ml-2 text-xs font-medium text-gold-600">Principal</span>}
                </p>
                <p className="text-xs text-navy-400">{t.lien_parente || '—'}</p>
              </div>
              <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-navy-500">
                {t.telephone && <span>{t.telephone}</span>}
                {t.email && <span>{t.email}</span>}
                {t.lieu_service && <span>{t.lieu_service}</span>}
              </div>
            </div>
          ))}
        </div>
      </Card>

      <FinanceCard eleveId={eleveId} />
      <ProgressionCard eleveId={eleveId} />
      <EmploiDuTempsCard eleveId={eleveId} />
      <AssiduiteCard eleveId={eleveId} />
      <InfirmerieCard eleveId={eleveId} />
      <DisciplineCard eleveId={eleveId} />
      <ObservationsCard eleveId={eleveId} />

      {modificationOuverte && (
        <ModifierInfosModal
          eleveId={eleveId}
          enfant={e}
          onClose={() => setModificationOuverte(false)}
          onSubmitted={() => {
            queryClient.invalidateQueries({ queryKey: ['parent-modification', eleveId] })
            setModificationOuverte(false)
          }}
        />
      )}
    </div>
  )
}

function ModifierInfosModal({
  eleveId,
  enfant,
  onClose,
  onSubmitted,
}: {
  eleveId: number
  enfant: EnfantDossier
  onClose: () => void
  onSubmitted: () => void
}) {
  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<{
    nom_complet: string
    sexe: 'M' | 'F'
    date_naissance: string
    lieu_naissance: string
    adresse: string
    numero_acte_naissance: string
    lieu_delivrance_acte: string
    officier_etat_civil: string
    groupe_sanguin: string
    aptitude: 'apte' | 'inapte'
    allergies: string
    situation_sanitaire: string
  }>({
    defaultValues: {
      nom_complet: enfant.nom_complet,
      sexe: enfant.sexe,
      date_naissance: enfant.date_naissance ?? '',
      lieu_naissance: enfant.lieu_naissance ?? '',
      adresse: enfant.adresse ?? '',
      numero_acte_naissance: enfant.acte_naissance.numero ?? '',
      lieu_delivrance_acte: enfant.acte_naissance.lieu_delivrance ?? '',
      officier_etat_civil: enfant.acte_naissance.officier_etat_civil ?? '',
      groupe_sanguin: enfant.sante.groupe_sanguin ?? '',
      aptitude: enfant.sante.aptitude,
      allergies: enfant.sante.allergies ?? '',
      situation_sanitaire: enfant.sante.situation_sanitaire ?? '',
    },
  })

  const onSubmit = async (values: ModificationEnfantPayload) => {
    try {
      await soumettreModification(eleveId, values)
      succes("Modification transmise, en attente de validation par l'établissement.")
      onSubmitted()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  return (
    <Modal title="Modifier mes informations" onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <p className="rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-500">
          Ces modifications seront examinées par l'établissement avant d'être appliquées à la fiche de l'enfant.
        </p>

        <h3 className="text-sm font-bold uppercase tracking-wide text-navy-500">Identité</h3>
        <Input label="Nom complet" error={errors.nom_complet?.message} {...register('nom_complet', { required: 'Requis.' })} />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Select label="Sexe" error={errors.sexe?.message} {...register('sexe', { required: 'Requis.' })}>
            <option value="F">Féminin</option>
            <option value="M">Masculin</option>
          </Select>
          <Input
            label="Date de naissance"
            type="date"
            error={errors.date_naissance?.message}
            {...register('date_naissance', { required: 'Requis.' })}
          />
        </div>
        <Input label="Lieu de naissance" {...register('lieu_naissance')} />
        <Input label="Adresse" {...register('adresse')} />
        <Input label="N° acte de naissance" {...register('numero_acte_naissance')} />
        <Input label="Lieu de délivrance" {...register('lieu_delivrance_acte')} />
        <Input label="Nom de l'officier d'état civil" {...register('officier_etat_civil')} />

        <h3 className="mt-2 text-sm font-bold uppercase tracking-wide text-navy-500">Situation sanitaire</h3>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input label="Groupe sanguin" placeholder="Ex. O+" {...register('groupe_sanguin')} />
          <Select label="Aptitude" {...register('aptitude')}>
            <option value="apte">Apte</option>
            <option value="inapte">Inapte</option>
          </Select>
        </div>
        <Textarea label="Allergies" {...register('allergies')} />
        <Textarea label="Situation sanitaire" {...register('situation_sanitaire')} />

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Envoi…' : 'Transmettre pour validation'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function FinanceCard({ eleveId }: { eleveId: number }) {
  const { data: finance, isLoading } = useQuery({ queryKey: ['parent-finance', eleveId], queryFn: () => fetchFinanceEnfant(eleveId) })

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <Wallet className="h-4 w-4" />
        Situation financière
      </h2>
      {isLoading ? (
        <Spinner />
      ) : !finance ? (
        <p className="text-sm text-navy-400">Aucune année scolaire active pour l'instant.</p>
      ) : (
        <>
          <dl className="mb-4 grid grid-cols-2 gap-4 rounded-xl bg-cream-100 p-3 text-center sm:grid-cols-4">
            {[
              ['Total dû', francs(finance.total_du), 'text-navy-700'],
              ['Déjà versé', francs(finance.total_paye), 'text-green-600'],
              ['Reste à payer', francs(finance.reste_a_payer), 'text-red-500'],
              ['Statut', finance.statut_paiement, 'text-navy-700'],
            ].map(([libelle, valeur, couleur]) => (
              <div key={libelle}>
                <dt className="text-[11px] uppercase tracking-wide text-navy-400">{libelle}</dt>
                <dd className={`text-sm font-bold tabular-nums ${couleur}`}>{valeur}</dd>
              </div>
            ))}
          </dl>

          {(finance.date_limite_paiement || finance.moratoire || finance.date_exclusion_insolvables) && (
            <div className="mb-4 flex flex-col gap-1.5 text-xs">
              {finance.date_limite_paiement && (
                <p className="text-navy-500">
                  Date limite de paiement : <span className="font-semibold text-navy-800">{new Date(finance.date_limite_paiement).toLocaleDateString('fr-FR')}</span>
                </p>
              )}
              {/* Un moratoire valide concerne cette famille précisément — il prime sur
                  la date d'exclusion générale de l'école, qui ne s'applique plus à elle. */}
              {finance.moratoire ? (
                <p className="rounded-lg bg-gold-50 px-3 py-2 text-gold-800">
                  Moratoire accordé jusqu'au{' '}
                  <span className="font-semibold">{new Date(finance.moratoire.date_expiration).toLocaleDateString('fr-FR')}</span>
                  {finance.moratoire.motif ? ` · ${finance.moratoire.motif}` : ''}
                </p>
              ) : (
                finance.date_exclusion_insolvables && (
                  <p className="text-navy-500">
                    Date d'exclusion des insolvables :{' '}
                    <span className="font-semibold text-navy-800">{new Date(finance.date_exclusion_insolvables).toLocaleDateString('fr-FR')}</span>
                  </p>
                )
              )}
            </div>
          )}

          {/* Sous sm : liste empilée plutôt qu'un tableau à scroller horizontalement —
              le parent consulte surtout depuis son téléphone. */}
          <div className="flex flex-col divide-y divide-navy-50 rounded-xl border border-navy-100 text-sm sm:hidden">
            {finance.rubriques.map((r) => (
              <div key={`${r.cle}:${r.dossier_frais_annexe_id ?? ''}`} className="flex flex-col gap-1.5 px-3 py-2.5">
                <span className="font-medium text-navy-800">{r.libelle}</span>
                <div className="flex items-center justify-between text-xs tabular-nums">
                  <span className="text-navy-400">Dû : <span className="text-navy-700">{francs(r.montant_du)}</span></span>
                  <span className="text-navy-400">Payé : <span className="text-green-600">{francs(r.montant_paye)}</span></span>
                  <span className="text-navy-400">Reste : <span className="text-red-500">{francs(r.reste)}</span></span>
                </div>
              </div>
            ))}
          </div>

          <div className="hidden overflow-x-auto rounded-xl border border-navy-100 sm:block">
            <table className="w-full min-w-[420px] text-xs">
              <thead className="bg-cream-50 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                <tr>
                  <th className="px-2.5 py-2 text-left">Rubrique</th>
                  <th className="px-2.5 py-2 text-right">Dû</th>
                  <th className="px-2.5 py-2 text-right">Payé</th>
                  <th className="px-2.5 py-2 text-right">Reste</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-navy-50">
                {finance.rubriques.map((r) => (
                  <tr key={`${r.cle}:${r.dossier_frais_annexe_id ?? ''}`}>
                    <td className="px-2.5 py-1.5 font-medium text-navy-800">{r.libelle}</td>
                    <td className="px-2.5 py-1.5 text-right tabular-nums">{francs(r.montant_du)}</td>
                    <td className="px-2.5 py-1.5 text-right tabular-nums text-green-600">{francs(r.montant_paye)}</td>
                    <td className="px-2.5 py-1.5 text-right tabular-nums text-red-500">{francs(r.reste)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {finance.versements.length > 0 && (
            <div className="mt-4 flex flex-col divide-y divide-navy-50 text-sm">
              {finance.versements.map((v) => (
                <div key={v.numero_recu} className="flex items-center justify-between gap-2 py-2 first:pt-0">
                  <span className="text-navy-600">
                    {v.numero_recu} · {new Date(v.date_versement).toLocaleDateString('fr-FR')}
                  </span>
                  <span className="font-semibold tabular-nums text-navy-800">{francs(v.montant)}</span>
                </div>
              ))}
            </div>
          )}
        </>
      )}
    </Card>
  )
}

function ProgressionCard({ eleveId }: { eleveId: number }) {
  const { data: matieres, isLoading } = useQuery({
    queryKey: ['parent-progression', eleveId],
    queryFn: () => fetchProgressionEnfant(eleveId),
  })

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <GitBranch className="h-4 w-4" />
        Progression dans les matières
      </h2>
      {isLoading ? (
        <Spinner />
      ) : !matieres || matieres.length === 0 ? (
        <p className="text-sm text-navy-400">Aucun programme renseigné pour l'instant.</p>
      ) : (
        <div className="flex flex-col divide-y divide-navy-50">
          {matieres.map((m) => (
            <div key={m.classe_matiere_id} className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-navy-800">{m.matiere}</p>
                <p className="truncate text-xs text-navy-400">{m.enseignant || '—'}</p>
              </div>
              <div className="flex flex-none items-center gap-2">
                <div className="h-1.5 w-16 overflow-hidden rounded-full bg-navy-100 sm:w-24">
                  <div className="h-full rounded-full bg-gold-500" style={{ width: `${m.taux}%` }} />
                </div>
                <span className="w-10 text-right text-xs font-semibold tabular-nums text-navy-700">{m.taux}%</span>
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

function AssiduiteCard({ eleveId }: { eleveId: number }) {
  const queryClient = useQueryClient()
  const [modalOuvert, setModalOuvert] = useState(false)

  const { data: absences, isLoading } = useQuery({ queryKey: ['parent-absences', eleveId], queryFn: () => fetchAbsencesEnfant(eleveId) })
  const { data: justifications } = useQuery({
    queryKey: ['parent-justifications', eleveId],
    queryFn: () => fetchJustificationsEnfant(eleveId),
  })

  const nonJustifiees = absences?.filter((a) => a.statut === 'absent' && !a.justifie).length ?? 0

  return (
    <Card>
      <div className="mb-4 flex items-center justify-between gap-2">
        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <CalendarX className="h-4 w-4" />
          Assiduité et absences
        </h2>
        <Button size="sm" variant="secondary" onClick={() => setModalOuvert(true)}>
          Justifier une absence
        </Button>
      </div>

      {isLoading ? (
        <Spinner />
      ) : !absences || absences.length === 0 ? (
        <p className="text-sm text-navy-400">Aucune absence relevée.</p>
      ) : (
        <>
          {nonJustifiees > 0 && (
            <p className="mb-3 rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
              {nonJustifiees} absence(s) non justifiée(s).
            </p>
          )}
          <div className="flex flex-col divide-y divide-navy-50">
            {absences.map((a, i) => (
              <div key={i} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <span className="text-navy-700">
                  {new Date(a.date).toLocaleDateString('fr-FR')} — {a.statut === 'retard' ? 'Retard' : 'Absence'}
                  {a.remarque ? ` · ${a.remarque}` : ''}
                </span>
                <Badge tone={a.justifie ? 'green' : 'red'}>{a.justifie ? 'Justifiée' : 'Non justifiée'}</Badge>
              </div>
            ))}
          </div>
        </>
      )}

      {justifications && justifications.length > 0 && (
        <div className="mt-4 border-t border-navy-50 pt-4">
          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-navy-400">Justifications déposées</h3>
          <div className="flex flex-col divide-y divide-navy-50 text-sm">
            {justifications.map((j) => (
              <div key={j.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                <span className="text-navy-600">
                  {new Date(j.date_debut).toLocaleDateString('fr-FR')}
                  {j.date_fin !== j.date_debut ? ` – ${new Date(j.date_fin).toLocaleDateString('fr-FR')}` : ''} ·{' '}
                  {MOTIFS_JUSTIFICATION.find((m) => m.valeur === j.motif)?.libelle}
                </span>
                <Badge tone={j.statut === 'appliquee' ? 'green' : 'gold'}>
                  {j.statut === 'appliquee' ? 'Prise en compte' : 'En attente'}
                </Badge>
              </div>
            ))}
          </div>
        </div>
      )}

      {modalOuvert && (
        <JustifierAbsenceModal
          eleveId={eleveId}
          onClose={() => setModalOuvert(false)}
          onCreated={() => {
            queryClient.invalidateQueries({ queryKey: ['parent-justifications', eleveId] })
            queryClient.invalidateQueries({ queryKey: ['parent-absences', eleveId] })
          }}
        />
      )}
    </Card>
  )
}

function JustifierAbsenceModal({ eleveId, onClose, onCreated }: { eleveId: number; onClose: () => void; onCreated: () => void }) {
  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<{ date_debut: string; date_fin: string; motif: MotifJustification; description: string }>()

  const onSubmit = async (values: { date_debut: string; date_fin: string; motif: MotifJustification; description: string }) => {
    try {
      await soumettreJustification(eleveId, {
        date_debut: values.date_debut,
        date_fin: values.date_fin || undefined,
        motif: values.motif,
        description: values.description || undefined,
      })
      succes('Justification transmise à l\'établissement.')
      onCreated()
      onClose()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  return (
    <Modal title="Justifier une absence" onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <p className="text-xs text-navy-400">
          Dès que l'enseignant fera l'appel sur cette période, l'absence sera automatiquement marquée justifiée.
        </p>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Input
            label="Du"
            type="date"
            error={errors.date_debut?.message}
            {...register('date_debut', { required: 'Requis.' })}
          />
          <Input label="Au (optionnel)" type="date" {...register('date_fin')} />
        </div>
        <Select label="Motif" error={errors.motif?.message} {...register('motif', { required: 'Requis.' })}>
          <option value="">Sélectionner…</option>
          {MOTIFS_JUSTIFICATION.map((m) => (
            <option key={m.valeur} value={m.valeur}>
              {m.libelle}
            </option>
          ))}
        </Select>
        <Textarea label="Précisions (optionnel)" {...register('description')} />

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Envoi…' : 'Transmettre'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function ObservationsCard({ eleveId }: { eleveId: number }) {
  const queryClient = useQueryClient()
  const [contenu, setContenu] = useState('')
  const [envoi, setEnvoi] = useState(false)

  const { data: observations, isLoading } = useQuery({
    queryKey: ['parent-observations', eleveId],
    queryFn: () => fetchObservationsEnfant(eleveId),
  })

  const envoyer = async () => {
    if (!contenu.trim()) return
    setEnvoi(true)
    try {
      await soumettreObservation(eleveId, contenu.trim())
      setContenu('')
      queryClient.invalidateQueries({ queryKey: ['parent-observations', eleveId] })
      succes('Observation transmise.')
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <MessageSquare className="h-4 w-4" />
        Observations
      </h2>

      <div className="mb-4 flex flex-col gap-2.5 sm:flex-row sm:items-end">
        <div className="flex-1">
          <Textarea
            value={contenu}
            onChange={(e) => setContenu(e.target.value)}
            placeholder="Écrire une observation à l'attention de l'établissement…"
            rows={3}
          />
        </div>
        <Button onClick={envoyer} disabled={envoi || !contenu.trim()} className="w-full sm:w-auto sm:flex-none">
          <Send className="h-4 w-4" />
          Envoyer
        </Button>
      </div>

      {isLoading ? (
        <Spinner />
      ) : !observations || observations.length === 0 ? (
        <p className="px-1 text-sm text-navy-400">Aucune observation pour l'instant.</p>
      ) : (
        <div className="flex flex-col divide-y divide-navy-50">
          {observations.map((o) => (
            <div key={o.id} className="flex flex-col gap-1.5 py-3 first:pt-0 last:pb-0">
              <div className="flex flex-wrap items-center gap-2">
                <Badge tone={o.origine === 'ecole' ? 'blue' : 'gold'}>
                  {o.origine === 'ecole' ? <ShieldCheck className="h-3 w-3" /> : <ShieldAlert className="h-3 w-3" />}
                  {o.origine === 'ecole' ? "L'établissement" : 'Vous'}
                </Badge>
                <span className="text-xs text-navy-400">
                  {o.auteur} · {new Date(o.date).toLocaleString('fr-FR')}
                </span>
              </div>
              <p className="text-sm leading-relaxed text-navy-700">{o.contenu}</p>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

function EmploiDuTempsCard({ eleveId }: { eleveId: number }) {
  const { t } = useTranslation()
  const { data: creneaux, isLoading } = useQuery({
    queryKey: ['parent-emploi-du-temps', eleveId],
    queryFn: () => fetchEmploiDuTempsEnfant(eleveId),
  })

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <CalendarClock className="h-4 w-4" />
        Emploi du temps
      </h2>
      {isLoading ? (
        <Spinner />
      ) : !creneaux || creneaux.length === 0 ? (
        <p className="text-sm text-navy-400">Aucun emploi du temps renseigné pour l'instant.</p>
      ) : (
        <div className="flex flex-col divide-y divide-navy-50">
          {JOURS.filter((j) => creneaux.some((c) => c.jour === j.valeur)).map((j) => (
            <div key={j.valeur} className="py-2.5 first:pt-0 last:pb-0">
              <h3 className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-navy-400">
                {t(`emploiDuTemps.jours.${j.libelle}`)}
              </h3>
              <div className="flex flex-col gap-1.5">
                {creneaux
                  .filter((c) => c.jour === j.valeur)
                  .map((c) => (
                    <div key={c.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                      <span className="text-navy-700">
                        {c.heure_debut}–{c.heure_fin} · {c.matiere || '—'}
                        {c.salle ? ` · ${c.salle}` : ''}
                      </span>
                      <span className="text-xs text-navy-400">{c.enseignant || '—'}</span>
                    </div>
                  ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

function InfirmerieCard({ eleveId }: { eleveId: number }) {
  const { data: visites, isLoading } = useQuery({
    queryKey: ['parent-visites-infirmerie', eleveId],
    queryFn: () => fetchVisitesInfirmerieEnfant(eleveId),
  })

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <Stethoscope className="h-4 w-4 text-gold-500" />
        Passages à l'infirmerie
      </h2>
      {isLoading ? (
        <Spinner />
      ) : !visites || visites.length === 0 ? (
        <p className="text-sm text-navy-400">Aucun passage à l'infirmerie relevé.</p>
      ) : (
        <div className="flex flex-col divide-y divide-navy-50">
          {visites.map((v) => (
            <div key={v.id} className="flex flex-col gap-1 py-2.5 first:pt-0 last:pb-0">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-sm font-semibold text-navy-800">
                  {new Date(v.date_visite).toLocaleString('fr-FR')}
                </span>
                {v.raison && <Badge tone="gold">{v.raison}</Badge>}
              </div>
              {v.malaises.length > 0 && (
                <p className="text-xs text-navy-500">{v.malaises.map((m) => m.label_fr).join(', ')}</p>
              )}
              {v.soins_prodiges && <p className="text-sm text-navy-700">{v.soins_prodiges}</p>}
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

function DisciplineCard({ eleveId }: { eleveId: number }) {
  const { data: dossier, isLoading } = useQuery({
    queryKey: ['parent-sanctions', eleveId],
    queryFn: () => fetchSanctionsEnfant(eleveId),
  })

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <Gavel className="h-4 w-4" />
        Discipline
      </h2>
      {isLoading ? (
        <Spinner />
      ) : !dossier || dossier.sanctions.length === 0 ? (
        <p className="text-sm text-navy-400">Rien à signaler.</p>
      ) : (
        <>
          {dossier.est_exclu && (
            <p className="mb-3 flex items-center gap-2 rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
              <ShieldAlert className="h-4 w-4 flex-none" />
              Exclusion en cours{dossier.motif_exclusion ? ` — ${dossier.motif_exclusion}` : ''}
            </p>
          )}
          <div className="flex flex-col divide-y divide-navy-50">
            {dossier.sanctions.map((s) => (
              <div key={s.id} className="flex flex-col gap-1 py-2.5 first:pt-0 last:pb-0">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="text-sm font-semibold text-navy-800">
                    {new Date(s.date_sanction).toLocaleDateString('fr-FR')} — {s.type}
                  </span>
                  <Badge tone={s.statut === 'confirmee' ? 'red' : 'gold'}>{s.statut}</Badge>
                </div>
                <p className="text-sm text-navy-700">{s.motif}</p>
              </div>
            ))}
          </div>
        </>
      )}
    </Card>
  )
}

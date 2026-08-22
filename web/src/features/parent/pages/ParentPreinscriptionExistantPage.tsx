import { useEffect, useMemo, useState } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ArrowLeft, Plus, Trash2 } from 'lucide-react'
import { StepForm } from '@/shared/ui/StepForm'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { fetchEnfant, fetchFinanceEnfant, soumettrePreinscription, type TuteurPayload } from '@/features/parent/api'
import { francs, MODES, ventilerAutomatiquement, type ModePaiement } from '@/features/finance/api'
import { succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface FormValues {
  nom_complet: string
  sexe: 'M' | 'F' | ''
  date_naissance: string
  lieu_naissance: string
  adresse: string
  numero_acte_naissance: string
  lieu_delivrance_acte: string
  officier_etat_civil: string
  groupe_sanguin: string
  situation_sanitaire: string
  aptitude: 'apte' | 'inapte'
  allergies: string
  note_admin: string
  tuteurs: TuteurPayload[]
  montant_verser?: number
  mode_versement: ModePaiement
  reference_externe?: string
}

/** Préinscription d'un enfant déjà scolarisé : réviser sa fiche et, si on le souhaite, verser un acompte pour l'année à venir. */
export function ParentPreinscriptionExistantPage() {
  const navigate = useNavigate()
  const { eleveId: eleveIdParam } = useParams<{ eleveId: string }>()
  const eleveId = Number(eleveIdParam)

  const [currentStep, setCurrentStep] = useState(0)
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const { data: enfant, isLoading, isError } = useQuery({ queryKey: ['parent-enfant', eleveId], queryFn: () => fetchEnfant(eleveId) })
  const { data: finance } = useQuery({ queryKey: ['parent-finance', eleveId], queryFn: () => fetchFinanceEnfant(eleveId) })

  const {
    register,
    control,
    handleSubmit,
    reset,
    trigger,
    watch,
    formState: { errors },
  } = useForm<FormValues>({ defaultValues: { tuteurs: [], mode_versement: 'especes', aptitude: 'apte' } })

  useEffect(() => {
    if (!enfant) return
    reset({
      nom_complet: enfant.nom_complet,
      sexe: enfant.sexe,
      date_naissance: enfant.date_naissance ?? '',
      lieu_naissance: enfant.lieu_naissance ?? '',
      adresse: enfant.adresse ?? '',
      numero_acte_naissance: enfant.acte_naissance.numero ?? '',
      lieu_delivrance_acte: enfant.acte_naissance.lieu_delivrance ?? '',
      officier_etat_civil: enfant.acte_naissance.officier_etat_civil ?? '',
      groupe_sanguin: enfant.sante.groupe_sanguin ?? '',
      situation_sanitaire: enfant.sante.situation_sanitaire ?? '',
      aptitude: enfant.sante.aptitude,
      allergies: enfant.sante.allergies ?? '',
      note_admin: '',
      tuteurs: enfant.tuteurs.map((t) => ({
        nom_complet: t.nom_complet,
        telephone: t.telephone ?? '',
        email: t.email ?? '',
        profession: t.profession ?? '',
        lieu_service: t.lieu_service ?? '',
        adresse: t.adresse ?? '',
        lien_parente: t.lien_parente ?? '',
        is_principal: t.is_principal,
      })),
      mode_versement: 'especes',
    })
  }, [enfant, reset])

  const { fields, append, remove } = useFieldArray({ control, name: 'tuteurs' })
  const montant = Number(watch('montant_verser') || 0)
  const rubriquesApercu = useMemo(() => {
    if (!finance || montant <= 0) return []
    const allocations = ventilerAutomatiquement(finance.rubriques, montant)
    return finance.rubriques.map((r, i) => ({ ...r, alloue: allocations[i] || 0 }))
  }, [finance, montant])

  const steps = [
    { id: 'identite', label: 'Identité / Identity', description: 'Nom, naissance, adresse / Name, birth, address' },
    { id: 'acte_sante', label: 'État civil & santé / Civil status & health', description: "Acte de naissance, fiche sanitaire / Birth certificate, health record" },
    { id: 'tuteurs', label: 'Parents / Parents', description: 'Coordonnées des parents/tuteurs / Contact details of parents/guardians' },
    { id: 'paiement', label: 'Versement / Payment', description: 'Facultatif / Optional' },
    { id: 'confirmation', label: 'Confirmation / Confirmation', description: "Envoi à l'établissement / Sent to the school" },
  ]

  const handleNext = async (): Promise<boolean> => {
    if (steps[currentStep]?.id === 'identite') {
      return trigger(['nom_complet', 'sexe', 'date_naissance'])
    }
    return true
  }

  const onSubmit = async (values: FormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      await soumettrePreinscription({
        type: 'existant',
        eleve_id: eleveId,
        donnees_eleve: {
          nom_complet: values.nom_complet,
          sexe: values.sexe as 'M' | 'F',
          date_naissance: values.date_naissance,
          lieu_naissance: values.lieu_naissance || undefined,
          adresse: values.adresse || undefined,
          numero_acte_naissance: values.numero_acte_naissance || undefined,
          lieu_delivrance_acte: values.lieu_delivrance_acte || undefined,
          officier_etat_civil: values.officier_etat_civil || undefined,
          groupe_sanguin: values.groupe_sanguin || undefined,
          situation_sanitaire: values.situation_sanitaire || undefined,
          aptitude: values.aptitude,
          allergies: values.allergies || undefined,
        },
        donnees_tuteurs: values.tuteurs,
        note_admin: values.note_admin || undefined,
        montant_verser: montant > 0 ? montant : undefined,
        mode_versement: montant > 0 ? values.mode_versement : undefined,
        reference_externe: montant > 0 ? values.reference_externe || undefined : undefined,
      })
      succes("Préinscription transmise, en attente de validation par l'établissement. / Pre-registration submitted, awaiting school approval.")
      navigate('/parent/preinscriptions')
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  if (isLoading) return <Spinner />
  if (isError || !enfant) return <ErrorState />

  return (
    <div>
      <Link to={`/parent/enfants/${eleveId}`} className="mb-2 flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
        <ArrowLeft className="h-4 w-4" />
        {enfant.nom_complet}
      </Link>
      <PageHeader
        titre="Préinscription / Pre-registration"
        sousTitre={`Réviser la fiche de ${enfant.nom_complet} pour l'année à venir. / Update ${enfant.nom_complet}'s profile for the coming year.`}
      />

      <div className="mt-6">
        <form onSubmit={handleSubmit(onSubmit)}>
          <StepForm
            steps={steps}
            currentStep={currentStep}
            onStepChange={setCurrentStep}
            isLastStep={currentStep === steps.length - 1}
            isSubmitting={submitting}
            onSubmit={handleSubmit(onSubmit)}
            onCancel={() => navigate(`/parent/enfants/${eleveId}`)}
            onNext={handleNext}
          >
            {steps[currentStep]?.id === 'identite' && (
              <div className="space-y-4">
                <Input
                  label="Nom complet / Full name"
                  error={errors.nom_complet?.message}
                  {...register('nom_complet', { required: 'Requis. / Required.' })}
                />
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Select label="Sexe / Sex" error={errors.sexe?.message} {...register('sexe', { required: 'Requis. / Required.' })}>
                    <option value="">Sélectionner… / Select…</option>
                    <option value="F">Féminin / Female</option>
                    <option value="M">Masculin / Male</option>
                  </Select>
                  <Input
                    label="Date de naissance / Date of birth"
                    type="date"
                    error={errors.date_naissance?.message}
                    {...register('date_naissance', { required: 'Requis. / Required.' })}
                  />
                </div>
                <Input label="Lieu de naissance / Place of birth" {...register('lieu_naissance')} />
                <Input label="Adresse / Address" {...register('adresse')} />

                <div className="rounded-xl bg-cream-100 p-3.5">
                  <p className="text-xs font-semibold uppercase tracking-wide text-navy-500">Classe actuelle / Current class</p>
                  <p className="mt-1 text-sm font-semibold text-navy-800">{enfant.classe?.nom || '—'}</p>
                  <p className="mt-1 text-xs text-navy-400">
                    La classe ne peut pas être modifiée depuis ce formulaire. En cas d'erreur, signalez-la ci-dessous. / The
                    class cannot be changed from this form. If it's wrong, please mention it below.
                  </p>
                </div>
                <Textarea
                  label="Note à l'attention de l'établissement (optionnel) / Note for the school (optional)"
                  placeholder="Ex. mon enfant a été mal orienté, il devrait être en… / E.g. my child was placed in the wrong class, they should be in…"
                  {...register('note_admin')}
                />
              </div>
            )}

            {steps[currentStep]?.id === 'acte_sante' && (
              <div className="space-y-4">
                <h3 className="text-sm font-bold uppercase tracking-wide text-navy-500">Acte de naissance / Birth certificate</h3>
                <Input label="N° acte de naissance / Birth certificate no." {...register('numero_acte_naissance')} />
                <Input label="Lieu de délivrance / Place of issue" {...register('lieu_delivrance_acte')} />
                <Input label="Nom de l'officier d'état civil / Registrar's name" {...register('officier_etat_civil')} />

                <h3 className="mt-6 text-sm font-bold uppercase tracking-wide text-navy-500">Fiche sanitaire / Health record</h3>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Input label="Groupe sanguin / Blood type" placeholder="Ex. O+" {...register('groupe_sanguin')} />
                  <Select label="Aptitude / Fitness" {...register('aptitude')}>
                    <option value="apte">Apte / Fit</option>
                    <option value="inapte">Inapte / Unfit</option>
                  </Select>
                </div>
                <Textarea label="Allergies / Allergies" {...register('allergies')} />
                <Textarea
                  label="Situation sanitaire / Health situation"
                  placeholder="Toute information utile à l'infirmerie / Any information useful to the school nurse"
                  {...register('situation_sanitaire')}
                />
              </div>
            )}

            {steps[currentStep]?.id === 'tuteurs' && (
              <TuteursFieldArray fields={fields} append={append} remove={remove} register={register} errors={errors} />
            )}

            {steps[currentStep]?.id === 'paiement' && (
              <div className="space-y-4">
                <p className="text-sm text-navy-500">
                  Facultatif — vous pouvez verser un acompte dès maintenant, ou laisser ce champ vide et régler plus tard à
                  la caisse. / Optional — you can pay a deposit now, or leave this blank and pay later at the cash desk.
                </p>
                {finance && (
                  <dl className="grid grid-cols-3 gap-2 rounded-xl bg-cream-100 p-3 text-center">
                    {[
                      ['Total dû / Total due', francs(finance.total_du), 'text-navy-700'],
                      ['Déjà versé / Already paid', francs(finance.total_paye), 'text-green-600'],
                      ['Reste / Remaining', francs(finance.reste_a_payer), 'text-red-500'],
                    ].map(([libelle, valeur, couleur]) => (
                      <div key={libelle}>
                        <dt className="text-[11px] uppercase tracking-wide text-navy-400">{libelle}</dt>
                        <dd className={`text-sm font-bold tabular-nums ${couleur}`}>{valeur}</dd>
                      </div>
                    ))}
                  </dl>
                )}
                <Input label="Montant à verser (F CFA) / Amount to pay (F CFA)" type="number" min={0} {...register('montant_verser')} />

                {rubriquesApercu.length > 0 && (
                  <div className="overflow-x-auto rounded-xl border border-navy-100">
                    <table className="w-full min-w-[380px] text-xs">
                      <thead className="bg-cream-50 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                        <tr>
                          <th className="px-2.5 py-2 text-left">Rubrique / Item</th>
                          <th className="px-2.5 py-2 text-right">Sera affecté / Will be allocated</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-navy-50">
                        {rubriquesApercu.map((r) => (
                          <tr key={r.cle}>
                            <td className="px-2.5 py-1.5 font-medium text-navy-800">{r.libelle}</td>
                            <td className="px-2.5 py-1.5 text-right tabular-nums">{francs(r.alloue)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}

                {montant > 0 && (
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Select label="Mode de versement / Payment method" {...register('mode_versement')}>
                      {MODES.map((m) => (
                        <option key={m.valeur} value={m.valeur}>
                          {m.libelle}
                        </option>
                      ))}
                    </Select>
                    <Input label="Référence (Mobile Money…) / Reference (Mobile Money…)" placeholder="Facultatif / Optional" {...register('reference_externe')} />
                  </div>
                )}
                <p className="rounded-xl bg-cream-100 px-3.5 py-2.5 text-xs text-navy-500">
                  Ce versement n'est pas encaissé immédiatement : il attend la validation de l'établissement, qui délivrera
                  alors le reçu. / This payment isn't collected immediately: it awaits the school's approval, which will
                  then issue the receipt.
                </p>
              </div>
            )}

            {steps[currentStep]?.id === 'confirmation' && (
              <div className="space-y-4">
                {serverError && <p className="rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">{serverError}</p>}
                <p className="rounded-xl bg-green-50 px-3.5 py-2.5 text-sm text-green-800">
                  Votre demande sera transmise à l'établissement pour validation. Vous serez informé de la décision. / Your
                  request will be sent to the school for approval. You will be informed of the decision.
                </p>
              </div>
            )}
          </StepForm>
        </form>
      </div>
    </div>
  )
}

/** Éditeur de tuteurs partagé entre les deux formulaires de préinscription. */
export function TuteursFieldArray({
  fields,
  append,
  remove,
  register,
  errors,
}: {
  fields: { id: string }[]
  append: (v: TuteurPayload) => void
  remove: (i: number) => void
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  register: any
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  errors: any
}) {
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-bold uppercase tracking-wide text-navy-500">Parents / tuteurs — Parents / guardians</h3>
        <button
          type="button"
          onClick={() => append({ nom_complet: '', is_principal: fields.length === 0 })}
          className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold text-navy-600 transition-colors hover:bg-cream-100"
        >
          <Plus className="h-4 w-4" />
          Ajouter / Add
        </button>
      </div>

      {fields.length === 0 ? (
        <div className="rounded-xl border-2 border-dashed border-navy-200 p-6 text-center text-sm text-navy-400">
          Aucun tuteur — ajoutez-en au moins un. / No guardian — please add at least one.
        </div>
      ) : (
        fields.map((field, index) => (
          <Card key={field.id} className="bg-cream-50 p-4">
            <div className="grid grid-cols-1 gap-3">
              <Input
                label="Nom complet / Full name"
                error={errors.tuteurs?.[index]?.nom_complet?.message}
                {...register(`tuteurs.${index}.nom_complet` as const, { required: 'Requis. / Required.' })}
              />
              <Input
                label="Lien de parenté / Relationship"
                placeholder="Père, mère, tuteur… / Father, mother, guardian…"
                {...register(`tuteurs.${index}.lien_parente` as const)}
              />
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Input label="Téléphone / Phone" type="tel" {...register(`tuteurs.${index}.telephone` as const)} />
                <Input label="E-mail / Email" type="email" {...register(`tuteurs.${index}.email` as const)} />
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Input label="Profession / Occupation" {...register(`tuteurs.${index}.profession` as const)} />
                <Input label="Lieu de service / Workplace" {...register(`tuteurs.${index}.lieu_service` as const)} />
              </div>
              <Input label="Adresse / Address" {...register(`tuteurs.${index}.adresse` as const)} />

              <div className="flex items-center justify-between border-t border-navy-100 pt-2">
                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" {...register(`tuteurs.${index}.is_principal` as const)} className="rounded border-navy-300" />
                  <span className="text-navy-700">Tuteur principal / Primary guardian</span>
                </label>
                <button type="button" onClick={() => remove(index)} className="rounded-lg p-2 text-navy-400 transition-colors hover:bg-red-100 hover:text-red-500">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          </Card>
        ))
      )}
    </div>
  )
}
